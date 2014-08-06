<?php
namespace MSF;

// Uhh, the interface?
abstract class ServiceHandler {
    protected $service;

    public function __construct(\MSF\Service $service) {
        $this->service = $service;
    }

    public function __call($name, $args) {
        throw new \Exception($name . ' RPC method not implemented');
    }

}
