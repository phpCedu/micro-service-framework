<?php
namespace MSF;

abstract class Service extends \MSF\Helper\Singletons {
    protected static $endpoint;
    protected static $definition; // Interface definition for this service
    protected static $transportClass = '\MSF\Transport\CurlTransport';
    protected static $encoderClass;
    protected static $clientClass;
    protected static $serverClass;

    public function definition() {
        if (!static::$definition) {
            throw new \Exception('Please create a service definition in your Service class');
        }
        return static::$definition;
    }
}

