<?php
namespace MSF;

class Transport {
    public $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function read() {
    }
    public function write() {
    }

    public function newRequest() {
        return new RequestResponse();
    }
    public function newResponse() {
        return new RequestResponse();
    }
    public function oob($key = null, $value = null) {
        // BaseTransport doesn't support OOB
        return array();
    }
}

