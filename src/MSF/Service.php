<?php
namespace MSF;

abstract class Service {
    protected static $endpoint;
    protected static $transport;
    protected static $encoder; // Can encode as JSON, or MsgPack, etc
    protected static $definition; // Interface definition for this service
    protected static $clientClass; // = '\\MSF\\Client';
    protected static $serverClass; // = '\\MSF\\Client';

    // Maybe it'd be better to accept a client class as a param,
    // that way we can override this method, pass our own to this parent method
    public static function client() {
        $class = static::$clientClass;
        return new $class(
            get_called_class(),
            new \MSF\Transport\CurlTransport(static::$endpoint),
            static::encoder()
        );
    }
    public static function server($handler) {
        $class = static::$serverClass;
        return new $class(get_called_class(), $handler);
    }

    public static function transport() {
        $className = static::$transport;
        return new $className(static::$endpoint);
    }
    public static function encoder() {
        $className = static::$encoder;
        return new $className();
    }
    public static function definition() {
        return static::$definition;
    }
    public static function clientClass() {
        return static::$clientClass;
    }

}

