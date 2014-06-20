<?php
namespace MSF;

class Service {
    public $endpoint;
    public $transport;
    public $encoder; // Can encode as JSON, or MsgPack, etc
    public $definition; // Interface definition for this service
    public static $clientClass = 'MSF\\Client';

    // Maybe it'd be better to accept a client class as a param,
    // that way we can override this method, pass our own to this parent method
    public function client() {
        $class = static::$clientClass;
        $client = new $class();
        $client->service = $this;
        return $client;
    }
}

