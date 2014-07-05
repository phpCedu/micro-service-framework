<?php

namespace MSF;

class Server {
    public $filters = array();
    protected $inTransport;
    protected $outTransport;
    protected $serviceClass; // Which Service is being served
    protected $handler;

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

    public function valid($rpc, $args, $response) {
        $serviceClass = $this->serviceClass;
        $definition = $serviceClass::definition();
        if (!isset($definition[$rpc])) {
            // Method doesn't exist
        }
        $method_params = $definition[$rpc][1];
        $method_types = $definition[$rpc][2];
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

    public function run() {
        $serviceClass = $this->serviceClass;
        $request = $this->inTransport->read();
        $request->decodeUsing($serviceClass::encoder());
        $response = $request->response;
        
        // Pass request to all the chained filters
        foreach ($this->filters as $i => $filter) {
            $request = $filter->request($request);
            // Is $request an instance of an error? ... If so, don't pass to any more filters
            if ($request instanceof Exception) { // Filters shouldn't return Exceptions, this is just an example
                //$response = $this->outTransport->newResponse();
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
            //$response = $this->outTransport->newResponse();
            $response->rpc = $request->rpc;
            $response->args = $request->args;
        
            // Dispatch to our implementation
            if (!is_array($args)) {
                $args = array();
            }
            // DO TYPE CHECKING - This needs to be handled by a Protocol-type class
            $definition = $serviceClass::definition();
        
            if ($this->valid($rpc, $args, $response)) {
                $return_value = call_user_func_array(array($this->handler, $rpc), $args);
                $return_type = $definition[$rpc][0];
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
            $response->encodeUsing($serviceClass::encoder());
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

