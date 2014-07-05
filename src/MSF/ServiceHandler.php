<?php
namespace MSF;

abstract class ServiceHandler {
    public function __call($name, $args) {
        throw new \Exception($name . ' RPC method not implemented');
    }
}
