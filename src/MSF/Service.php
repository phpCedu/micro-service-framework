<?php
namespace MSF;

abstract class Service {
    protected static $endpoint;
    protected static $definition; // Interface definition for this service
    protected static $transportClass = '\MSF\Transport\CurlTransport';
    protected static $encoderClass; // Can encode as JSON, or MsgPack, etc
    protected static $clientClass;
    protected static $serverClass;

    // Maybe it'd be better to accept a client class as a param,
    // that way we can override this method, pass our own to this parent method
    public static function client() {
        if (!static::$clientClass) {
            throw new \Exception('Please specify a client class in your Service class');
        }
        $clientClass = static::$clientClass;
        return new $clientClass(
            get_called_class(),
            static::transport(),
            static::encoder()
        );
    }
    public static function server($handler) {
        if (!static::$serverClass) {
            throw new \Exception('Please specify a server class in your Service class');
        }
        $class = static::$serverClass;
        return new $class(get_called_class(), $handler);
    }
    public static function endpoint() {
        if (!static::$endpoint) {
            throw new \Exception('Please specify an endpoint in your Service class');
        }
        return static::$endpoint;
    }
    public static function transport() {
        if (!static::$transportClass) {
            throw new \Exception('Please specify a transport class in your Service class');
        }
        $transportClass = static::$transportClass;
        return new $transportClass(static::endpoint());
    }
    public static function encoder() {
        if (!static::$encoderClass) {
            throw new \Exception('Please specify an encoder class in your Service class');
        }
        $className = static::$encoderClass;
        return new $className();
    }
    public static function definition() {
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

