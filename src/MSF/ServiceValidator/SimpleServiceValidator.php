<?php
namespace MSF\ServiceValidator;

class SimpleServiceValidator {
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
                        $error = '"' . $key . '" should be one of: ';
                    }
                } elseif ($type == 'int32') {
                    if (is_int($val)) {
                        $error = false;
                        break;
                    } else {
                        $error = '"' . $key . '" should be one of: ';
                    }
                } elseif ($type == 'array') {
                    if (!is_array($val)) {
                        // Expected $i-th arg to be an integer
                        $error = '"' . $key . '" should be one of: ';
                    } else {
                        $error = false;
                        break;
                    }
                }
            }
            if (!is_bool($error)) {
                $errors[] = $error . implode(', ', $types);
            } elseif ($error) {
                // $error is true
                $errors[] = $key . ' is invalid';
            }
        }
        return $errors;
    }

}
