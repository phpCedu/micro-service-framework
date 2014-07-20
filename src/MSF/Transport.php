<?php
namespace MSF;

abstract class Transport implements \MSF\TransportInterface {
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
     * @return int - bytes written
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

