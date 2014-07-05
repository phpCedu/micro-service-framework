<?php
namespace MSF;

abstract class Transport {
    protected $endpoint;

    public function __construct($endpoint = null) {
        $this->endpoint = $endpoint;
    }

    /**
     * @return \MSF\RequestResponse instance
     */
    public function read() {
    }
    /**
     * @param \MSF\RequestResponse $request
     * @return null
     * @throws \Exception
     */
    public function write(\MSF\RequestResponse $request) {
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

