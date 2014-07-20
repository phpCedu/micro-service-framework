<?php

namespace MSF;

abstract class Server {
    public $filters = array();
    protected $service;
    protected $inTransport;
    protected $outTransport;
    protected $handler;

    protected static $instance;

    // So Client instances can check whether or not they're within a server context
    // or something
    public static function context() {
        return static::$instance;
    }

    public function __construct(\MSF\Service $service, \MSF\ServiceHandler $handler, \MSF\Transport $inTransport, \MSF\Transport $outTransport = null) {
        $this->service = $service;
        $this->handler = $handler;
        $this->inTransport = $this->outTransport = $inTransport;
        if ($outTransport) {
            $this->outTransport = $outTransport;
        }
    }

    public function run() {
        $request = $this->inTransport->readRequest();
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
        
        if ($request instanceof \MSF\Request) {
            // Get the response ready, so we can annotate it before filling the value
            //$response = $this->outTransport->newResponse();
            $rpc = $response->rpc = $request->rpc;
            $response->args = $request->args;
        
            // validateRequest() returns prepared args array for call_user_func_array()
            $args = $this->service->validateRequest($request, $response);
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
                    $response->body = $this->service->validateReturn($return_value, $request->rpc, $response);
                }
            }

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

