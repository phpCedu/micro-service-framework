<?php

class Server {
    public $filters = array();
    public $inTransport;
    public $outTransport;
    // Always encoded in msgpack, for now. This could be a class that encoded/decoded and also verified that they RPC call data matched the definition
    public $protocol = new MsgPackProtocol();
    
    function run() {
        // Got request from transport
        $request = $this->inTransport->read(); // What about failure to read, would a future help us here?
        // Decode message body using this protocol
        $request->decodeUsing($protocol);
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
            $destination = $request->rpcCall;
            $args = $request->rpcArgs;
            $version = $request->rpcVersion;
            // Does the request version match the version we have here?
            if ($version != $destination::$version) {
                // Version mismatch error!
            }
        
            $value = $this->dispatch($destination, $params);
            
            // Now wrap in the appropriate response
            $class = $this->outTransport->responseClass;
            $response = new $class();
            $response->rpc = $request->rpc;
            // Don't copy the request's args into the response
            $response->version = $version;
            $response->body = $value;
            // Who's in charge of encoding?
            $response->encodeUsing($protocol);
        }
        
        // Use the $i from the above loop to loop backwards from where we left off
        // UNSURE ABOUT WHETHER WE SHOULD UNROLL IF WE GOT AN ERROR, OR RETURN STRAIGHT AWAY
        // MIGHT BE NICE TO GIVE FILTERS THE OPTION TO ANNOTATE
        for (; $i >=0; $i--) {
            $filter = $this->filters[$i];
            $response = $filter->response($response);
        }
        
        // Just in case our transport is simply a buffer, we should return the body
        return $responseTransport->write($response);
    }
}

class BaseProtocol {
    // encode / decode
}


class Client {
    // Haven't thought about the client yet
    // Pull version out of Service definition
}

// Each filter sees the request coming in, and the response going out (in reverse order through the filter stack)
// BUT FILTERS SHOULDN'T BE ALLOWED TO MODIFY THE REQUEST OR RESPONSE BODY
interface Filter {
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
    public $responseClass = 'HTTPRequestResponse';
    
    public function __construct($socket = null) {
        $this->socket = $socket;
    }
    public function oobKeyValue($name, $value) {
        // Add to headers
        $headers[ $key ] = $value;
    }
    public function read() {
        $r = new HTTPRequestResponse();
        // Read full response from socket ... but in PHP land the headers are already read for us
        //$request = file_get_contents($this->socket);
        // Split headers and body
        $headers = 'ONE: TWO';
        $r->headers = explode('\r\n', $headers); // need to convert to key=>value too
        $r->encoded = 'bla';
        return $r;
    }
    public function write($r) {
        // Write out $this->headers
        // Now write out the response annotations as headers
        foreach ($r->annotations as $key => $value) {
            // Do proper encoding, line-returning
            header($key . ':' . $value);
        }
        
        // Now write the response body
        // fwrite($this->socket, $r->encoded);
    }
}
// This class is only concerned with reading the HTTP body ... it assumes the HTTP headers have already been parsed
// In the case of most PHP requests, this will be the case
class PartialHTTPTransport {
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
            header($key . ':' . $value);
        }

        echo $r->encoded;
    }
}

class BaseRequestResponse {
    // RPC related
    public $rpc; // An array with class and method
    public $args = array();
    public $version = 0;
    // Body
    public $body;
    public $encoded; // Transports only read/write encoded values
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

class MyServer extends Server {
    // Nothing custom yet
}

public function MsgPackProtocol extends BaseProtocol {
    public function encode($data) {
        return msgpack_pack($data);
    }
    public function decode($data) {
        return msgpack_unpack($data);
    }
}

class MyFilterDoesMetrics extends Filter {
    protected $started;
    public function request(BaseRequestResponse $request) {
        $this->started = microtime();
        return $request;
    }
    public function response($response) {
        // Annotate $response with metric data ... from where?
        $response->annotations['metric-runtime'] = microtime() - $this->started;
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
    public function response(HTTPResponse $response) {
    }
}

class RPCImplementation {
    public $version = 1;
    
    // Need some type-checking on input params, right? or is that too tedious to do at this level?
    public function method(\Tool\Types\Int $in1, \Tool\Types\CustomValidatedType $in2) {
        return 'Value';
    }
}

/*
In my mind you need separate transports for input and output so out-of-band data isn't squashed or duplicated.
*/
$inTransport = new HTTPTransport();
// Maybe this should be a pass-through so we can echo our output like the test_service.php example
$outTransport = new HTTPTransport();

$server = new MyServer();
$server->inTransport = $inTransport; // In transport determines the starting class of the request
$server->outTransport = $outTransport; // Out transport determines the starting class of the response, I think ...
// We need to know exactly which type of output response type ...
$server->responseClass = 'HTTPResponse';

$server->filters[] = new MyFilterDoesMetrics();
$server->filters[] = new MyFilterConvertsRequest();
$server->filters[] = new MyFilterAnnotatesResponseWithOOB();
