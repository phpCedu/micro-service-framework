<?php
namespace MSF;

abstract class Service extends \MSF\Helper\Singletons {
    protected static $endpoint;
    protected static $definition; // Interface definition for this service
    protected static $transportClass = '\MSF\Transport\CurlTransport';
    protected static $encoderClass;
    protected static $clientClass;
    protected static $serverClass;

    public function definition() {
        if (!static::$definition) {
            throw new \Exception('Please create a service definition in your Service class');
        }
        return static::$definition;
    }

    /**
     * @param $request
     * @param $response
     * @return args array, ready for use in call_user_func_array()
     */
    public function validateRequest($request, $response) {
        $definition = static::definition();
        $rpc = $request->rpc;
        $errors = array();
        if (!array_key_exists($rpc, $definition)) {
            // Method doesn't exist
            $errors[] = $rpc . ' RPC method does not exist';
            $response->errors = $errors;
            return false;
        }
        $args = array();
        if (!array_key_exists(1, $definition[$rpc])) {
            return $args;
        }
        if (!array_key_exists(2, $definition[$rpc])) {
            $definition[$rpc][2] = array();
        }
        $method_params = $definition[$rpc][1];
        $method_types = $definition[$rpc][2];
        // Do simple type-checking for sent params ... all others default to null
        // It's up to the service handler to error out if it really requires a param ...
        // idea is to force developers to make their services flexible, and only error on unexpected input when absolute necessary
        foreach ($method_params as $i => $name) {
            // Default to null?
            if (array_key_exists($name, $request->args)) {
                $val = $request->args[ $name ];
            } else {
                $val = null;
            }
            $type = $method_types[ $i ];
            if ($type == 'string') {
                if (is_string($val)) {
                    $args[] = $val;
                } else {
                    $errors[] = 'Should be string: ' . $name;
                    $args[] = null;
                }
            } elseif ($type == 'null-string') {
                if (is_string($val) || is_null($value)) {
                    $args[] = $val;
                } else {
                    $errors[] = 'Should be string or null: ' . $name;
                    $args[] = null;
                }
            } elseif ($type == 'int32') {
                if (is_int($val)) {
                    $args[] = $val;
                } else {
                    $errors[] = 'Should be int32: ' . $name;
                    $args[] = null;
                }
            } elseif ($type == 'null-int32') {
                if (is_int($val) || is_null($val)) {
                    $args[] = $val;
                } else {
                    $errors[] = 'Should be int32 or null: ' . $name;
                    $args[] = null;
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

    /**
     * Torn about whether this should return the return value, or assign it into the response accordingly
     */
    public function validateReturn($return_value, $rpc, $response) {
        $definition = static::definition();
        $return_type = $definition[$rpc][0];
        if ($return_type === 'null') {
            if (!is_null($return_value)) {
                // FAIL
                $response->addError('RPC call was expected to return null');
                return null;
            }
        } elseif ($return_type === 'string') {
            if (!is_string($return_value)) {
                // FAIL
                $response->addError('RPC call was expected to return a string');
                return null;
            }
        } elseif ($return_type === 'int32') {
            if (!is_int($return_value)) {
                // FAIL
                $response->addError('RPC call was expected to return an int');
                return null;
            }
        }
        return $return_value;
    }
}

