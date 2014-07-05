<?php
namespace MSF;

abstract class Transport {
    protected $endpoint;

    public function __construct($endpoint = null) {
        $this->endpoint = $endpoint;
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

