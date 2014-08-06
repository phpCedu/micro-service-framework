<?php
namespace MSF\ServiceValidator;

class SimpleInputValidator {
    protected $definition;

    public function __construct($definition) {
        $this->definition = $definition;
    }

    // Simple merely validates types
    public function __call($name, $args) {
        if (!isset($this->definition[$name])) {
            // Invalid RPC call
        }
        $input = $args[0]; // This must be an object
        $params = $this->definition[$name];
        $errors = array();
        foreach ($params as $key => $types) {
            if (isset($input->$key)) {
                $val = $input->$key;
            } else {
                $val = null;
            }
            $error = true;
            foreach ($types as $type) {
                if ($type == 'null') {
                    // Uhh, i think this stuff is wrong
                    if (is_null($val)) {
                        $error = false;
                    }
                } elseif ($type == 'string') {
                    if (is_string($val)) {
                        $error = false;
                        break;
                    } else {
                        $error = 'Should be string: ' . $key;
                    }
                } elseif ($type == 'int32') {
                    if (is_int($val)) {
                        $error = false;
                        break;
                    } else {
                        $error = 'Should be int32: ' . $key;
                    }
                } elseif ($type == 'array') {
                    if (!is_array($val)) {
                        // Expected $i-th arg to be an integer
                        $error = 'Should be an array: ' . $key;
                    } else {
                        $error = false;
                        break;
                    }
                }
            }
            if (!is_bool($error)) {
                $errors[] = $error;
            } elseif ($error) {
                // $error is true
                $errors[] = $key . ' is invalid';
            }
        }
        return $errors;
    }

}
