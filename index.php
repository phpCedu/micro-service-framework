<?php

class Server {
    public $filters = array();
    public $inTransport;
    public $outTransport;
    // Always encoded in msgpack, for now. This could be a class that encoded/decoded and also verified that they RPC call data matched the definition
    public $protocol = new MsgPack();
    
    function run() {
        // Got request from transport
        $request = $this->inTransport->read(); // What about failure to read, would a future help us here?
        // Decode message body using this protocol
        $request->body = $protocol->decode($request->body);
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
            $destination = $this->decodeRPCDestination($request);
            $params = $this->decodeRPCParams($request);
        
            $value = $this->dispatch($destination, $params);
            
            // Now wrap in the appropriate response
            $class = $this->responseClass;
            $response = new $class();
            $response->body = $protocol->encode($value);
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

class Client {
    // Haven't thought about the client yet
}

// Each filter sees the request coming in, and the response going out (in reverse order through the filter stack)
// BUT FILTERS SHOULDN'T BE ALLOWED TO MODIFY THE REQUEST OR RESPONSE BODY
interface Filter {
    // Should return some instance of BaseResponse
    public function request(BaseRequest $request) {
        return $request;
    }
    public function response(BaseResponse $response) {
        return $response;
    }
}

class BaseTransport {
    public function oobKeyValue($name, $value) {
        throw new Exception('This transport does not support out-of-band data');
    }
    public function read() {
        return 'Data that was read';
    }
    public function write() {
    }
}
class HTTPTransport {
    public $headers = array();
    
    public function __construct($
    public function oobKeyValue($name, $value) {
        // Add to headers
        $headers[ $key ] = $value;
    }
    public function read() {
        // Read full response from socket ... but in PHP land the headers are already read for us
        $request = file_get_contents($socket);
        // Split headers and body
        $headers = 'bla';
        $this->headers = explode('\r\n', $headers); // need to convert to key=>value too
        $body = 'bla';
    }
    public function write($response) {
        // Write out $this->headers
        // Now write out the response annotations as headers
        foreach ($response->annotations as $key => $value) {
            // Do proper encoding, line-returning
            header($key . ':' . $value);
        }
        
        // Now write the response body
    }
}
// This class is only concerned with reading the HTTP body ... it assumes the HTTP headers have already been parsed
// In the case of most PHP requests, this will be the case
class PartialHTTPTransport {
    public function read() {
        // headers should already be in $_SERVER, now just extract the ones that pertain to us
        // Loop through $_SERVER looking for headers with our special "HTTP_ABC123" prefix
        
        // just read the remainder of the body
        $body = file_get_contents('php://input');
        return $body;
    }
}

/*
Think it's desirable for request objects to be wrapped in layers like an onion.
Otherwise, when converting a BaseRequest to HTTPRequest, we'd have to clone all the data from Base into HTTP, which is error prone.
Better to simply wrap it, allow member variable accesses and method calls on HTTPRequest to take precedence, deferring to BaseRequest
as necessary.
*/
class BaseRequest {
    protected $parent;
    public $body;
    // See note in BaseResponse about annotations
    public $annotations = array();
    
    public function __construct($parent = null) {
        $this->parent = $parent;
    }
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
class HTTPRequest extends BaseRequest {
    // associative
    public $headers = array();
}

class BaseResponse {
    public $body;
    // The idea is that we can add annotations, but not all transports have a way of supporting them, maybe?
    // We were going to encode some data in HTTP headers, so an HTTPTransport would take the annotations and convert them to headers
    public $annotations = array();
}
class HTTPResponse extends BaseResponse {
    // associative
    public $headers = array();
}


// IMPLEMENTATIONS

class MyServer extends Server {
    // Nothing custom yet
}

class MyFilterDoesMetrics extends Filter {
    protected $started;
    public function request(BaseRequest $request) {
        $this->started = microtime();
        return $request;
    }
    public function response($response) {
        // Annotate $response with metric data ... from where?
        $response->annotations['metric-runtime'] = microtime() - $this->started;
        return $response;
    }
}

class MyFilterConvertsToHTTPRequest extends Filter {
    public function request(BaseRequest $request) {
        $req = new HTTPRequest();
        $req->body = $request->body;
        // Is there anything else to copy?
        return $req;
    }
}

class MyFilterConvertsToHTTPRequest2 extends Filter {
    // Returns an HTTPRequest2 instance
    function request(HTTPRequest $request) {
        // annotate $request, or do something
        $req2 = new HTTPRequest2();
        $req2->headers = $request->headers;
        $req2->body = $request->body;
        return $req2;
    }
}
class HTTPRequest2 extends HTTPRequest {
    // Nothing custom
}

class MyFilterAnnotatesResponseWithOOB extends Filter {
    public function response(HTTPResponse $response) {
    }
}

class RPCImplementation {
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
$server->inTransport = $inTransport;
$server->outTransport = $outTransport;
// We need to know exactly which type of output response type ...
$server->responseClass = 'HTTPResponse';

$server->filters[] = new MyFilterDoesMetrics();
$server->filters[] = new MyFilterConvertsToHTTPRequest(); // Convert the BaseRequest instance (which has RPC info) to an HTTPRequest
$server->filters[] = new MyFilterConvertsToHTTPRequest2();
$server->filters[] = new MyFilterAnnotatesResponseWithOOB();
