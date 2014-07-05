<?php

namespace MSF;

class Server {
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

    public function validRequest($request, $response) {
        $serviceClass = $this->serviceClass;
        $definition = $serviceClass::definition();
        $rpc = $request->rpc;
        $errors = array();
        if (!array_key_exists($rpc, $definition)) {
            // Method doesn't exist
            $errors[] = $rpc . ' RPC method does not exist';
            $response->errors = $errors;
            return false;
        }
        $method_params = $definition[$rpc][1];
        $method_types = $definition[$rpc][2];
        $args = array();
        /*
        Keep it simple for now:
        - args are required. later will default to null if not sent
        - do simple type checking
        */
        foreach ($method_params as $i => $name) {
            // Default to null?
            if (!array_key_exists($name, $request->args)) {
                // FAIL - Log the error
                $errors[] = 'Param missing: ' . $name;
                $args[] = null;
                continue;
            }
            $val = $request->args[ $name ];
            $type = $method_types[ $i ];
            if ($type == 'string') {
                if (!is_string($val)) {
                    // Expected $i-th arg to be a string
                    $errors[] = 'Should be string: ' . $name;
                    $args[] = null;
                } else {
                    $args[] = $val;
                }
            } elseif ($type == 'int32') {
                if (!is_int($val)) {
                    // Expected $i-th arg to be an integer
                    $errors[] = 'Should be int32: ' . $name;
                    $args[] = null;
                } else {
                    $args[] = $val;
                }
            } elseif ($type == 'array') {
                if (!is_array($val)) {
                    // Expected $i-th arg to be an integer
                    $errors[] = 'Should be an array: ' . $name;
                    $args[] = null;
                } else {
                    $args[] = $val;
                }
            }
        }

        if ($errors) {
            $response->errors = $errors;
            return false;
        }
        return $args;
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
            // Get the response ready, so we can annotate it before filling the value
            //$response = $this->outTransport->newResponse();
            $rpc = $response->rpc = $request->rpc;
            $response->args = $request->args;
        
            // DO TYPE CHECKING - This needs to be handled by a Protocol-type class
            $definition = $serviceClass::definition();
        
            if ($args = $this->validRequest($request, $response)) {
                try {
                    $return_value = call_user_func_array(array($this->handler, $rpc), $args);
                    // if $return_value is false, might mean an error, but that sucks, so use exceptions
                } catch (\Exception $e) {
                    $response->errors = array(
                        $e->getMessage()
                    );
                }
                if (!$response->errors) {
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
        
        // This returns bytes written, but we don't need that here
        $this->outTransport->write($response);
        return true;
    }
}

