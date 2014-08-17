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
        $this->setup();
    }
    public function setup() {
    }

    public function run() {
        $request = $this->inTransport->readRequest();
        $response = $request->response;
        
        // Pass request through the filters
        $i = -1;
        foreach ($this->filters as $i => $filter) {
            $request = $filter->request($request);
            // Request filters can return the $request->response object to signal an error
            // The filter is in charge of annotating the Response with the appropriate error message
            if ($request instanceof \MSF\Response) {
                break;
            }
        }
        
        if ($request instanceof \MSF\Request) {
            $rpc = $request->rpc;
            $validator = $this->service->validator();
            $errors = $validator->$rpc($request->args);
            if (!$errors) {
                try {
                    $response->body = $this->handler->$rpc($request->args);
                } catch (\Exception $e) {
                    $response->errors = array(
                        $e->getMessage()
                    );
                }
            } else {
                $response->errors = $errors;
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

