<?php

class BaseServer {
    public $filters = array();
    public $inTransport;
    public $outTransport;
    public static $protocol;
    public static $response;
    
    function run() {
        // Got request from transport
        $request = $this->inTransport->read(); // What about failure to read, would a future help us here?
        // Decode message body using this protocol
        $request->decodeUsing(static::$protocol);
        // Was there an error in decoding?
        
        // Pass request to all the chained filters
        foreach ($this->filters as $i => $filter) {
            $request = $filter->request($request);
            // Is $request an instance of an error? ... If so, don't pass to any more filters
            if ($request instanceof Exception) { // Filters shouldn't return Exceptions, this is just an example
                $response = $this->makeResponse(); // This would make a response according to $this->responseClass, right?
                // Unwind with a response
                break;
            }
        }
        
        if (!($request instanceof Exception)) {
            // Now dispatch?
            $destination = $request->rpc;
            $args = $request->args;
            $version = $request->version;

            $class = $destination[0];
            $method = $destination[1];
            // Does the request version match the version we have here?
            if ($version != $class::$version) {
                // Version mismatch error!
            }

            // Get the response ready, so we can annotate it before filling the value
            // Now wrap in the appropriate response
            $responseClass = $this->outTransport->responseClass;
            $response = new $responseClass();
            BaseServer::$response = $response;
            $response->rpc = $request->rpc;
            // Don't copy the request's args into the response
            $response->version = $version;
        
            // Dispatch to our implementation
            if (!is_array($args)) {
                $args = array();
            }
            $response->body = call_user_func_array(array(new $class(), $method), $args);

            // Who's in charge of encoding?
            $response->encodeUsing(static::$protocol);

        }
        
        // Use the $i from the above loop to loop backwards from where we left off
        // UNSURE ABOUT WHETHER WE SHOULD UNROLL IF WE GOT AN ERROR, OR RETURN STRAIGHT AWAY
        // MIGHT BE NICE TO GIVE FILTERS THE OPTION TO ANNOTATE
        for (; $i >=0; $i--) {
            $filter = $this->filters[$i];
            $response = $filter->response($response);
        }
        
//var_dump($response);
        // Just in case our transport is simply a buffer, we should return the body
        return $this->outTransport->write($response);
    }
}

/*
This doesn't encapsulate an actual protocol, I'm just mirroring thrift's terminology.
What I think this should do is do type checking for the RPC calls, but that requires us to define the service calls and types ...
so that'll be a work in progress.
*/
class BaseProtocol {
    // encode / decode
    public function encode($data) {
        return $data;
    }
    public function decode($data) {
        return $data;
    }
}
class JsonProtocol extends BaseProtocol {
    public function encode($data) {
        return json_encode($data);
    }
    public function decode($data) {
        return json_decode($data, true);
    }
}


class Client {
    // Haven't thought about the client yet
    // Pull version out of Service definition
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
    public function oobKeyValue($name, $value) {
        throw new Exception('This transport does not support out-of-band data');
    }
    public function read() {
    }
    public function write() {
    }
}
class HTTPTransport {
    public $headers = array();
    protected $socket;
    protected $data;
    public $responseClass = 'HTTPRequestResponse';
    
    public function socket($socket) {
        $this->socket = $socket;
    }
    public function data($data) {
        $this->data = $data;
    }
    public function oobKeyValue($name, $value) {
        // Add to headers
        $headers[ $key ] = $value;
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
                $r->annotations[ $key ] = $value;
                // Push up to Server too
                BaseServer::$response->annotations[ $key ] = $value;
            }
        } // need to convert to key=>value too
        return $r;
    }
    public function write($r) {
        // Write out $this->headers
        // Now write out the response annotations as headers
        foreach ($r->annotations as $key => $value) {
            // Do proper encoding, line-returning
            header('HTTP_Z_' . $key . ':' . $value);
        }
        
        // Now write the response body
        // fwrite($this->socket, $r->encoded);
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
        foreach ($r->annotations as $key => $value) {
            // Do proper encoding, line-returning
            header('HTTP_Z_' . $key . ':' . $value);
        }

        echo $r->encoded;
    }
}

class BaseRequestResponse {
    // RPC related
    protected $rpc; // An array with class and method
    protected $args;
    protected $version;
    // Body
    protected $body;
    protected $encoded; // Transports only read/write encoded values
    // Stuff to make Filters easier
    protected $parent;

    public function __construct($parent = null) {
        $this->parent = $parent;
    }

    // Encode/Decode this request/response using the specified protocol
    public function encodeUsing($protocol) {
        $data = array(
            'rpc' => $this->rpc,
            'args' => $this->args,
            'version' => $this->version,
            'body' => $this->body
        );
        $this->encoded = $protocol->encode($data);
    }
    public function decodeUsing($protocol) {
        $data = $protocol->decode($this->encoded);
        // Do we need to store the decoded body in $request->body?
        // Request values get annotated with the RPC call, arguments, etc
        $this->rpc = $data['rpc'];
        $this->args = $data['args'];
        $this->version = $data['version'];
        $this->body = $data['body'];
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
    // The idea is that we can add annotations, but not all transports have a way of supporting them, maybe?
    // We were going to encode some data in HTTP headers, so an HTTPTransport would take the annotations and convert them to headers
    public $annotations = array();
    // associative
    public $headers = array();
}


// IMPLEMENTATIONS

class MyServer extends BaseServer {
    // Nothing custom yet
}

class MsgPackProtocol extends BaseProtocol {
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
        $this->rpc = $request->rpc[1];
        $this->started = microtime(true);
        return $request;
    }
    public function response($response) {
        // Annotate $response with metric data ... from where?
        $response->annotations[ $this->rpc ] = number_format(microtime(true) - $this->started, 8);
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
        $response->annotations['OOB'] = 'oob';
        return $response;
    }
}

class MyService {
    public static $version = 1;
    
    public function childReverse($name) {
        $data = array(
            'rpc' => array('MyService', 'reverse'),
            'args' => array($name)
        );

        $ch = curl_init('http://localhost:9998/index.php');
        if(!$ch) {
            throw new Exception('Curl Error');
        }
        curl_setopt($ch, CURLOPT_HEADER, 1); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch); //execute post and get results

        if($response === false) {
            $e = curl_error($ch);
            throw new Exception($e);
        } else {
            $t = new HTTPTransport();
            $t->data($response);
            $httpResponse = $t->read();
            $httpResponse->decodeUsing(BaseServer::$protocol);
            return $httpResponse->body;
        }
    }

    // Need some type-checking on input params, right? or is that too tedious to do at this level?
    public function reverse($name) {
        return strrev($name);
    }
}

// Transport determines the type of request and response (BaseRequestResponse or other), though Filters can change this
$inTransport = $outTransport = new PartialHTTPTransport();

// Thinking MyServer::$response should be public static ... nested client call response annotations would get pushed up into that
$server = new MyServer();
// Always encoded in msgpack, for now. This could be a class that encoded/decoded and also verified that they RPC call data matched the definition
BaseServer::$protocol = new JsonProtocol();
$server->inTransport = $inTransport; // In transport determines the starting class of the request
$server->outTransport = $outTransport; // Out transport determines the starting class of the response, I think ...

$server->filters[] = new MyFilterDoesMetrics();
$server->filters[] = new MyFilterConvertsRequest();
$server->filters[] = new MyFilterAnnotatesResponseWithOOB();

$server->run();
