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
        
            // Dispatch to our implementation
            if (!is_array($args)) {
                $args = array();
            }
            // DO TYPE CHECKING - This needs to be handled by a Protocol-type class
            if (!isset($this->definition['methods'][$rpc])) {
                // Method doesn't exist
            }
            $method_definition = $this->definition['methods'][$rpc];
            /*
            Keep it simple for now:
            - args are required
            - do simple type checking
            */
            foreach ($method_definition[1] as $i => $type) {
                $val = $args[$i];
                if (is_null($val)) {
                    // FAIL
                    // Log the error
                    continue;
                }
                if ($type == 'string') {
                    if (!is_string($val)) {
                        // Expected $i-th arg to be a string
                    }
                } elseif ($type == 'int32') {
                    if (!is_int($val)) {
                        // Expected $i-th arg to be an integer
                    }
                }
            }
            $return_value = call_user_func_array(array($this->service->handler, $rpc), $args);
            if ($method_definition[0] == 'null') {
                if (!is_null($return_value)) {
                    // FAIL
                }
            } elseif ($method_definition[0] == 'string') {
                if (!is_string($return_value)) {
                    // FAIL
                }
            } elseif ($method_definition[0] == 'int32') {
                if (!is_int($return_value)) {
                    // FAIL
                }
            }
            $response->body = $return_value;

            // Who's in charge of encoding?
            $response->encodeUsing($this->service->encoder);

            // Merge in OOB data ... but maybe a Filter should be in charge of this ...
            // Perhaps it should copy OOB from request, and amend to response on the way out.
            // Nevermind, that would take care of nested RPC calls
            foreach ($this->oob() as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $response->oob($key, $v);
                    }
                } else {
                    $response->oob($key, $value);
                }
            }
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

    // Don't like this being here, maybe it can be done better
    public function clientResponse($response) {
        // Bubble up some things
        foreach ($response->oob() as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $this->oob($key, $v);
                }
            } else {
                $this->oob($key, $value);
            }
        }
    }

    public function oob($key=null, $value=null) {
        if ($key == null) {
            return $this->_oob;
        }
        if ($value == null) {
            return $this->_oob[$key];
        }
        if (isset($this->_oob[$key])) {
            if (!is_array($this->_oob[$key])) {
                $this->_oob[$key] = array(
                    $this->_oob[$key]
                );
            }
            $this->_oob[$key][] = $value;
        } else {
            $this->_oob[$key] = $value;
        }
    }
}

/*
Perhaps this is where RPC type checking should take place, but that requires us to define the service calls and types ...
so that'll be a work in progress.
*/
interface Encoder {
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

    // If this client is running within a server instance, tell that server
    // bubble up stuff
    protected function gotResponse($response) {
        $server = BaseServer::context();
        if ($server) {
            // A Server instance exists, tell it about our response so it can pull in the OOB data
            $server->clientResponse($response);
        }
    }

    public function __call($name, $args) {
        $service = $this->service;
        $transport = $service->transport;
        $encoder = $service->encoder;
        
        $request = $transport->newRequest();
        $request->rpc = $name;
        $request->args = $args;
        $request->encodeUsing($encoder);

        $transport->write($request);
        // Get response
        $response = $transport->read();
        $response->decodeUsing($encoder);
        $this->gotResponse($response);

        // For posterity
        $this->request = $request;
        $this->response = $response;
        return $response->body;
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
                $key = trim(substr($header, 7, $colon-7));
                $value = trim(substr($header, $colon+1));
                $r->oob($key, $value);
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
            if (is_array($value)) {
                foreach ($value as $v) {
                    header('HTTP_Z_' . $key . ':' . $v, false);
                }
            } else {
                header('HTTP_Z_' . $key . ':' . $value, false);
            }
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
        $url = $r->service->endpoint;
        $ch = curl_init($url);
        if(!$ch) {
            die('Curl Error');
        }
        curl_setopt($ch, CURLOPT_HEADER, 1); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $r->encoded);
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
    // Body
    protected $body;
    protected $encoded; // Transports only read/write encoded values
    // Stuff to make Filters easier
    protected $parent;

    public function __construct($parent = null) {
        $this->parent = $parent;
    }

    // Encode/Decode this request/response using the specified encoder 
    public function encodeUsing($encoder) {
        $data = array(
            'rpc' => $this->rpc,
            'args' => $this->args,
            'body' => $this->body
        );
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing($encoder) {
        $data = $encoder->decode($this->encoded);
        // Do we need to store the decoded body in $request->body?
        // Request values get annotated with the RPC call, arguments, etc
        $this->rpc = $data['rpc'];
        $this->args = $data['args'];
        $this->body = $data['body'];
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
        if ($key == null) {
            return $this->_oob;
        }
        if ($value == null) {
            return $this->_oob[$key];
        }
        if (isset($this->_oob[$key])) {
            if (!is_array($this->_oob[$key])) {
                $this->_oob[$key] = array(
                    $this->_oob[$key]
                );
            }
            $this->_oob[$key][] = $value;
        } else {
            $this->_oob[$key] = $value;
        }
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

class MyFilterDoesMetrics extends Filter {
    protected $started;
    protected $rpc;
    public function request(BaseRequestResponse $request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
        return $request;
    }
    public function response($response) {
        $data = array(
            'start' => $this->started,
            'end' => microtime(true),
            'host' => gethostname()
        );
        $response->oob($this->rpc, json_encode($data));
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

class MyFilterAnnotatesResponseWithOOB extends Filter {
    public function response(HTTPRequestResponse $response) {
        $response->oob('OOB', 'oob');
        return $response;
    }
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

    public $definition = array(
        'types' => array(
        ),
        'methods' => array(
            'reverse' => array(
                // return type
                'string',
                // param types
                array(
                    'string'
                ),
            )
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

