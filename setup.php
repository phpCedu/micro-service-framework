<?php

class Logger {
    public static $fp;
    public static function notice($text) {
        if (!defined('STDERR')) {
            if (!static::$fp) {
                static::$fp = fopen('php://stderr', 'w');
            }
        } else {
            static::$fp = STDERR;
        }
        fwrite(static::$fp, $text);
    }
}

class BaseService {
    public $endpoint;
    public $transport;
    public $encoder; // Can encode as JSON, or MsgPack, etc
    public $definition; // Interface definition for this service
    public static $clientClass = 'BaseClient';

    // Maybe it'd be better to accept a client class as a param,
    // that way we can override this method, pass our own to this parent method
    public function client() {
        $class = static::$clientClass;
        $client = new $class();
        $client->service = $this;
        return $client;
    }
}

class BaseServer {
    public $filters = array();
    public $inTransport;
    public $outTransport;
    public $service; // Which Service is being served

    protected $_oob = array();
    protected static $instance;
    /*
    These are public and static because when a service call needs to make a nested
    RPC call, it needs to know which response to bubble up OOB-data/annotations into.
    Though, I guess the encoder doesn't need to be public, since that depends on the
    RPC call being made ... the client should know which encoding needs to be spoken.

    So is it the Service that defines the encoder? Or the server? Probably Service ...
    since Client and Server both use the same Service definition, that make sense.
    */
    public static $encoder; // why this?
    public static $response;

    public static function create($service) {
        // what's the right way to do this again?
        return new static($service);
    }

    // So Client instances can check whether or not they're within a server context
    // or something
    public static function context() {
        return static::$instance;
    }

    protected function __construct($service) {
        $this->service = $service;
        static::$instance = $this;
    }

    public function valid($rpc, $args, $response) {
        $service = $this->service;
        if (!isset($service->definition[$rpc])) {
            // Method doesn't exist
        }
        $method_params = $service->definition[$rpc][1];
        $method_types = $service->definition[$rpc][2];
        $errors = array();
        /*
        Keep it simple for now:
        - args are required
        - do simple type checking
        */
        foreach ($method_params as $i => $name) {
            if (!isset($args[ $name ])) {
                // FAIL - Log the error
                $errors[] = 'Param missing: ' . $name;
                continue;
            }
            $val = $args[ $name ];
            $type = $method_types[ $i ];
            if ($type == 'string') {
                if (!is_string($val)) {
                    // Expected $i-th arg to be a string
                    $errors[] = 'Should be string: ' . $name;
                }
            } elseif ($type == 'int32') {
                if (!is_int($val)) {
                    // Expected $i-th arg to be an integer
                    $errors[] = 'Should be int32: ' . $name;
                }
            }
        }

        if ($errors) {
            $response->errors = $errors;
            return false;
        }
        return true;
    }
    
    function run() {
        // Got request from transport
        $request = $this->inTransport->read(); // What about failure to read, would a future help us here?
        // Decode message body using this encoding 
        $request->decodeUsing($this->service->encoder);
        // Was there an error in decoding?
        
        // Pass request to all the chained filters
        foreach ($this->filters as $i => $filter) {
            $request = $filter->request($request);
            // Is $request an instance of an error? ... If so, don't pass to any more filters
            if ($request instanceof Exception) { // Filters shouldn't return Exceptions, this is just an example
                $response = $this->outTransport->newResponse();
                // Annotate with error info ... who knows yet
                // Unwind with a response
                break;
            }
        }
        
        if (!($request instanceof Exception)) {
            // Now dispatch?
            $rpc = $request->rpc;
            $args = $request->args;

            // Get the response ready, so we can annotate it before filling the value
            $response = $this->outTransport->newResponse();
            $response->rpc = $request->rpc;
            $response->args = $request->args;
        
            // Dispatch to our implementation
            if (!is_array($args)) {
                $args = array();
            }
            // DO TYPE CHECKING - This needs to be handled by a Protocol-type class
            if ($this->valid($rpc, $args, $response)) {
                $return_value = call_user_func_array(array($this->service->handler, $rpc), $args);
                $return_type = $this->service->definition[$rpc][0];
                if ($return_type == 'null') {
                    if (!is_null($return_value)) {
                        // FAIL
                    }
                } elseif ($return_type == 'string') {
                    if (!is_string($return_value)) {
                        // FAIL
                    }
                } elseif ($return_type == 'int32') {
                    if (!is_int($return_value)) {
                        // FAIL
                    }
                }
                $response->body = $return_value;
            }

            // Who's in charge of encoding?
            $response->encodeUsing($this->service->encoder);
        }
        
        // Use the $i from the above loop to loop backwards from where we left off
        // UNSURE ABOUT WHETHER WE SHOULD UNROLL IF WE GOT AN ERROR, OR RETURN STRAIGHT AWAY
        // MIGHT BE NICE TO GIVE FILTERS THE OPTION TO ANNOTATE IN THE EVENT OF AN ERROR
        for (; $i >=0; $i--) {
            $filter = $this->filters[$i];
            $response = $filter->response($response);
        }
        
        // Just in case our transport is simply a buffer, we should return the body
        // (yuck)
        return $this->outTransport->write($response);
    }

    public function oob($key=null, $value=null) {
        if ($key == null) {
            return $this->_oob;
        }
        if ($value == null) {
            return $this->_oob[$key];
        }
        $this->_oob[$key] = $value;
    }
}

/*
Perhaps this is where RPC type checking should take place, but that requires us to define the service calls and types ...
so that'll be a work in progress.
*/
interface EncoderInterface {
    public function encode($data);
    public function decode($data);
}
class BaseEncoder implements EncoderInterface {
    // encode / decode
    public function encode($data) {
        return $data;
    }
    public function decode($data) {
        return $data;
    }
}
class JsonEncoder extends BaseEncoder {
    public function encode($data) {
        return json_encode($data);
    }
    public function decode($data) {
        return json_decode($data, true);
    }
}

// No clue
class BaseClient {
    public $service;
    public $request;
    public $response;

    public function __call($name, $args) {
        $service = $this->service;
        $transport = $service->transport;
        $encoder = $service->encoder;
        
        $request = $transport->newRequest();
        $request->rpc = $name;
        // combine service's param names with the values into key/value pairs
        // make sure $args is same size as definition
        $names = $service->definition[ $name ][1];
        if (sizeof($args) < sizeof($names)) {
            $args = array_fill(
                sizeof($args), 
                sizeof($names) - sizeof($args),
                null
            );
        }
        //var_dump($names);var_dump($args);exit;
        $request->args = array_combine(
            $names,
            $args
        );
        // Don't encode empty body
        $request->encodeUsing($encoder, true);

        $this->preRequest($request);
        $transport->write($request);
        // Get response
        $response = $transport->read();
        $response->decodeUsing($encoder);
        $this->postResponse($response);

        // For posterity
        $this->request = $request;
        $this->response = $response;
        return $response->body;
    }

    protected function preRequest($request) {
    }
    protected function postResponse($response) {
    }
}

// Each filter sees the request coming in, and the response going out (in reverse order through the filter stack)
// BUT FILTERS SHOULDN'T BE ALLOWED TO MODIFY THE REQUEST OR RESPONSE BODY
class Filter {
    // Should return some instance of BaseResponse
    public function request(BaseRequestResponse $request) {
        return $request;
    }
    public function response(BaseRequestResponse $response) {
        return $response;
    }
}

class BaseTransport {
    public $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function read() {
    }
    public function write() {
    }

    public function newRequest() {
        return new BaseRequestResponse();
    }
    public function newResponse() {
        return new BaseRequestResponse();
    }
    public function oob($key = null, $value = null) {
        // BaseTransport doesn't support OOB
        return array();
    }
}
// Incomplete
class HTTPTransport extends BaseTransport {
    protected $socket;
    protected $data;
    
    public function socket($socket) {
        $this->socket = $socket;
    }
    public function data($data) {
        $this->data = $data;
    }
    public function read() {
        $r = new HTTPRequestResponse();
        if ($this->data) {
        } elseif ($this->socket) {
        }

        // Split headers and body
        list($headers, $body) = explode("\r\n\r\n", $this->data);
        $r->encoded = $body;
        foreach (explode("\r\n", $headers) as $header) {
            if (strncasecmp($header, 'HTTP_Z_', 7) == 0) {
                $colon = strpos($header, ':');
                $key = strtolower(trim(substr($header, 7, $colon-7)));
                $value = trim(substr($header, $colon+1));
                $r->oob($key, json_decode($value, true));
            }
        } // need to convert to key=>value too
        return $r;
    }

    public function write($r) {
        // Write out $this->headers
        // Now write out the response annotations as headers
        $this->writeOOB($r->oob());
       
        // Now write the response body
        // fwrite($this->socket, $r->encoded);
    }

    public function newRequest() {
        return new HTTPRequestResponse();
    }
    public function newResponse() {
        return new HTTPRequestResponse();
    }

    protected function writeOOB($oob) {
        foreach ($oob as $key => $value) {
            header('HTTP_Z_' . strtoupper($key) . ':' . json_encode($value));
        }
    }
}
// This class is only concerned with reading the HTTP body ... it assumes the HTTP headers have already been parsed
// In the case of most PHP requests, this will be the case
class PartialHTTPTransport extends HTTPTransport {
    public function read() {
        $r = new HTTPRequestResponse();
        // headers should already be in $_SERVER, now just extract the ones that pertain to us
        // Loop through $_SERVER looking for headers with our special "HTTP_ABC123" prefix
        // $r->headers['ONE'] = 'TWO';
        
        // just read the remainder of the body
        $r->encoded = file_get_contents('php://input');
        foreach ($_SERVER as $key => $value) {
            if (strncasecmp($key, 'HTTP_Z_', 7) == 0) {
                $key = strtolower(substr($key, 7));
                $r->oob($key, json_decode($value, true));
            }
        }
        return $r;
    }

    public function write($r) {
        // Write out non-annotation headers
        // then
        // Write out the response annotations as headers
        $this->writeOOB($r->oob());

        echo $r->encoded;
    }
}
class CurlTransport extends HTTPTransport {
    protected $response;

    public function read() {
        // read() makes no sense for Curl ... once we write, we automatially read too
        return $this->response->read();
    }
    public function write($r) {
        // Somehow need to get the service endpoint
        $url = $this->service->endpoint;
        $ch = curl_init($url);
        if(!$ch) {
            die('Curl Error');
        }
        curl_setopt($ch, CURLOPT_HEADER, 1); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $r->encoded);
        $headers = array();
        foreach ($r->oob() as $key => $value) {
            $headers[] = 'Z_' . $key . ':' . json_encode($value);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch); //execute post and get results

        if($response === false) {
            $e = curl_error($ch);
            // prepare error response
        } else {
            // Leverage the HTTPTransport to parse the HTTP response
            $t = new HTTPTransport($this->service);
            $t->data($response);
            $this->response = $t;
        }
    }
}

class BaseRequestResponse {
    // RPC related
    protected $rpc; // An array with class and method
    protected $args;
    protected $errors;
    // Body
    protected $body;
    protected $encoded; // Transports only read/write encoded values
    // Stuff to make Filters easier
    protected $parent;

    public function __construct($parent = null) {
        $this->parent = $parent;
    }

    // Encode/Decode this request/response using the specified encoder 
    public function encodeUsing($encoder, $request = false) {
        $data = array(
            'rpc' => $this->rpc,
            'args' => $this->args
        );
        if (!$request) {
            if ($this->errors) {
                $data['errors'] = $this->errors;
            } else {
                $data['body'] = $this->body;
            }
        }
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing($encoder) {
        $data = $encoder->decode($this->encoded);
        // Do we need to store the decoded body in $request->body?
        // Request values get annotated with the RPC call, arguments, etc
        $this->rpc = $data['rpc'];
        $this->args = $data['args'];
        if (array_key_exists('errors', $data)) {
            $this->errors = $data['errors'];
        } elseif (array_key_exists('body', $data)) {
            $this->body = $data['body'];
        }
    }

    public function oob($key = null, $value = null) {
        // BaseRequestResponse doesn't support OOB
        return array();
    }

    /*
    Think it's desirable for request objects to be wrapped in layers like an onion.
    Otherwise, when converting a BaseRequest to HTTPRequest, we'd have to clone all the data from Base into HTTP, which is error prone.
    Better to simply wrap it, allow member variable accesses and method calls on HTTPRequest to take precedence, deferring to BaseRequest
    as necessary.
    */
    public function __call($name, $args) {
        // Attempt to call this method on the parent
        if ($this->parent) {
            // call and return, bla
        }
    }
    public function __get($name) {
        // Proxy up to parent in same manner as __call
        if (isset($this->$name)) {
            return $this->$name;
        } elseif ($this->parent) {
            return $this->parent->$name;
        }
    }
    public function __set($name, $value) {
        // Need some checks here, to make sure we're only setting protected variables
        $this->$name = $value;
    }
}

class HTTPRequestResponse extends BaseRequestResponse {
    // associative
    public $headers = array();

    // The idea is that we can add annotations, but not all transports have a way of supporting them, maybe?
    // We were going to encode some data in HTTP headers, so an HTTPTransport would take the annotations and convert them to headers
    protected $_oob = array();

    public function oob($key=null, $value=null) {
        if (is_null($key)) {
            return $this->_oob;
        }
        if (is_null($value)) {
            return $this->_oob[$key];
        }
        $this->_oob[$key] = $value;
    }
}


// IMPLEMENTATIONS

class MyServer extends BaseServer {
    // Nothing custom yet
}

class MsgPackEncoder extends BaseEncoder {
    public function encode($data) {
        return msgpack_pack($data);
    }
    public function decode($data) {
        return msgpack_unpack($data);
    }
}

class MyFilterDoesProfiling extends Filter {
    protected $started;
    protected $rpc;
    protected $profile;
    public function request(BaseRequestResponse $request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
        $this->profile = $request->oob('profile');
        return $request;
    }
    public function response($response) {
        $this->profile['profile'] = array(
            'server.rpc' => $this->rpc,
            'start' => $this->started,
            'end' => microtime(true),
            'host' => gethostname()
        );
        $response->oob(
            'profile',
            $this->profile
        );
        return $response;
    }
}

class MyFilterConvertsRequest extends Filter {
    public function request(HTTPRequestResponse $request) {
        // Wrap it like an onion
        $req = new HTTPRequestResponse2($request);
        return $req;
    }
}
class HTTPRequestResponse2 extends HTTPRequestResponse {
}

class MyServiceHandler {
    
    public function childReverse($name) {
        $service = new MyService2();
        $client = $service->client();
        // 3 calls to make sure multiple OOB runtimes bubble up
        $name = $client->reverse($name);
        $name = $client->reverse($name);
        return $client->reverse($name);
    }

    // Need some type-checking on input params, right? or is that too tedious to do at this level?
    public function reverse($name) {
        return strrev($name);
    }
}

class MyService extends BaseService {
    public $endpoint = 'http://localhost:9999/index.php';
    public $transport;
    public $encoder;
    public static $clientClass = 'MyClient';

    public $definition = array(
        'reverse' => array(
            // return type
            'string',
            // param names
            array(
                'input'
            ),
            // associated param types
            array(
                'string'
            ),
        )
    );

    public function __construct() {
        $this->transport = new CurlTransport($this);
        // Always encoded in JSON, for now.
        $this->encoder = new JsonEncoder();

        // On the server side
        $this->handler = new MyServiceHandler();
    }
}
class MyService2 extends MyService {
    public $endpoint = 'http://localhost:9998/index.php';
}
class MyClient extends BaseClient {
    public function preRequest($request) {
        $oob = array(
            'client.rpc' => $request->rpc,
            'started' => microtime(true),
            'host' => gethostname()
        );
        $request->oob('profile', $oob);
    }
}
