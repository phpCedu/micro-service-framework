<?php
namespace MSF;

class Transport {
    public $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function read() {
    }
    public function write(\MSF\RequestResponse $r) {
    }

    public function newRequest() {
        return new \MSF\RequestResponse();
    }
    public function newResponse() {
        return new \MSF\RequestResponse();
    }
    public function oob($key = null, $value = null) {
        // BaseTransport doesn't support OOB
        return array();
    }
}

