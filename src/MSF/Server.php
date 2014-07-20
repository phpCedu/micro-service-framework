<?php

namespace MSF;

abstract class Server {
    public $filters = array();
    protected $inTransport;
    protected $outTransport;
    protected $serviceClass; // Which Service is being served
    protected $handler;

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

    // So Client instances can check whether or not they're within a server context
    // or something
    public static function context() {
        return static::$instance;
    }

    public function __construct($serviceClass, $handler) {
        $this->serviceClass = $serviceClass;
        $this->handler = $handler;

        $transportClass = static::$transport;
        $this->inTransport = new $transportClass();
        $this->outTransport = new $transportClass();
    }

    public function run() {
        $serviceClass = $this->serviceClass;
        $request = $this->inTransport->readRequest();
        $request->decodeUsing($serviceClass::encoder());
        $response = $request->response;
        
        // Pass request to all the chained filters
        foreach ($this->filters as $i => $filter) {
            $request = $filter->request($request);
            // Did the filter error (maybe it returns a Response to signal error)?
            // If so, break out and return asap
            if ($request instanceof \MSF\ResponseInterface) {
                //$response = $this->outTransport->newResponse();
                // Annotate with error info ... who knows yet
                // Unwind with a response
                break;
            }
        }
        
        if ($request instanceof \MSF\RequestInterface) {
            // Get the response ready, so we can annotate it before filling the value
            //$response = $this->outTransport->newResponse();
            $rpc = $response->rpc = $request->rpc;
            $response->args = $request->args;
        
            // validateRequest() returns prepared args array for call_user_func_array()
            $args = $serviceClass::validateRequest($request, $response);
            if ($args !== false) {
                try {
                    // call_user_func_array() can return false for errors,
                    // but that means RPC calls returning false would be ambiguous, so use exceptions
                    $return_value = call_user_func_array(array($this->handler, $rpc), $args);
                } catch (\Exception $e) {
                    $response->errors = array(
                        $e->getMessage()
                    );
                }
                if (!$response->errors) {
                    $response->body = $serviceClass::validateReturn($return_value, $request->rpc, $response);
                }
            }

            // Who's in charge of encoding?
            $response->encodeUsing($serviceClass::encoder());
        }
        
        // Use the $i from the above loop to loop backwards from where we left off,
        // so that we can trigger errors from within filters
        // UNSURE ABOUT WHETHER WE SHOULD UNROLL IF WE GOT AN ERROR, OR RETURN STRAIGHT AWAY
        // MIGHT BE NICE TO GIVE FILTERS THE OPTION TO ANNOTATE IN THE EVENT OF AN ERROR
        for (; $i >= 0; $i--) {
            $filter = $this->filters[ $i ] ;
            $response = $filter->response($response);
        }
        
        // This returns bytes written, but we don't need that here
        $this->outTransport->writeResponse($response);
        return true;
    }
}

