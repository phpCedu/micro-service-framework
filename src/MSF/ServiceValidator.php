<?php
namespace MSF;

/*
Needs methods for validating incoming service calls, and perhaps the return values
*/
class ServiceValidator {
    protected $service;

    public function __construct(\MSF\Service $service) {
        $this->service = $service;
    }

    // Default is to NOT validate, allow everything through
    public function __call($name, $args) {
        return true;
    }

    /*
    When you implement a child of this class, do it like so:

    // Expects: int, string
    public function rpcMethod1($arg1, $arg2) {
        return is_int($arg1) && is_string($arg2);
    }

    // Expects: int, string
    // Additional input checks
    public function rpcMethod2($arg1, $arg2) {
        $check1 = is_int($arg1) && is_string($arg2);
        return $check1 && (1 < $arg1 && $arg1 < 100);
    }

    // THIS IS PROBABLY A BAD IDEA
    // Expects: variable number of integers
    // But new version of service only accepts the first 10 params
    // Old version accepted up to 20, so old clients may send too much data
    public function rpcMethod3() {
        $ints = func_get_args();
        $ints = array_slice($ints, 0, 10);
        // Should also make sure each element is an int

        // Return an array of data to be passed to the ServiceHandler instance
        return $ints;
    }
    */

}
