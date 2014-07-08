<?php
namespace MSF;

abstract class Service {
    protected static $endpoint;
    protected static $transport;
    protected static $encoder; // Can encode as JSON, or MsgPack, etc
    protected static $definition; // Interface definition for this service
    protected static $clientClass; // = '\\MSF\\Client';
    protected static $serverClass; // = '\\MSF\\Client';

    // Maybe it'd be better to accept a client class as a param,
    // that way we can override this method, pass our own to this parent method
    public static function client() {
        $class = static::$clientClass;
        return new $class(
            get_called_class(),
            // TODO - This transport probably shouldn't be hard-coded
            new \MSF\Transport\CurlTransport(static::$endpoint),
            static::encoder()
        );
    }
    public static function server($handler) {
        $class = static::$serverClass;
        return new $class(get_called_class(), $handler);
    }
    public static function encoder() {
        $className = static::$encoder;
        return new $className();
    }
    public static function definition() {
        return static::$definition;
    }
    public static function clientClass() {
        return static::$clientClass;
    }

    /**
     * @param $request
     * @param $response
     * @return args array, ready for use in call_user_func_array()
     */
    public static function validateRequest($request, $response) {
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
            if (!array_key_exists($name, $request->args)) {
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

    /**
     * Torn about whether this should return the return value, or assign it into the response accordingly
     */
    public static function validateReturn($return_value, $rpc, $response) {
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

