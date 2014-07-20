<?php
namespace MSF;

abstract class Transport {
    protected $endpoint;
    protected $encoder;

    public function __construct($endpoint, $encoder) {
        $this->endpoint = $endpoint;
        $this->encoder = $encoder;
    }

    public function readRequest() {
        throw new \Exception('Not implemented');
    }
    public function readResponse() {
        throw new \Exception('Not implemented');
    }
    public function writeRequest(\MSF\Request $request) {
        throw new \Exception('Not implemented');
    }
    public function writeResponse(\MSF\Response $response) {
        throw new \Exception('Not implemented');
    }

    public function newRequest() {
        return new \MSF\Request();
    }
    public function newResponse() {
        return new \MSF\Response();
    }
    public function oob($key = null, $value = null) {
        // BaseTransport doesn't support OOB
        return array();
    }
}

