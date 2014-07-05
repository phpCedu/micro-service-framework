<?php
namespace MSF;

abstract class Client {
    protected $serviceClass;
    protected $transport;
    protected $encoder;

    public function __construct($serviceClass, $transport, $encoder) {
        $this->serviceClass = $serviceClass;
        $this->transport = $transport;
        $this->encoder = $encoder;
    }

    public function __call($name, $args) {
        $serviceClass = $this->serviceClass;
        $definition = $serviceClass::$definition;
        
        $request = $this->transport->newRequest();
        $request->rpc = $name;
        // combine service's param names with the values into key/value pairs
        // make sure $args is same size as definition
        $names = $definition[ $name ][1];
        if (sizeof($args) < sizeof($names)) {
            $args = array_pad(
                $args,
                sizeof($names), 
                null
            );
        } elseif (sizeof($args) > sizeof($names)) {
            $args = array_slice($args, 0, sizeof($names));
        }
        $request->args = array_combine(
            $names,
            $args
        );

        // Don't encode empty body
        $request->encodeUsing($this->encoder, true);

        $this->preRequest($request);
        try {
            $this->transport->write($request);
        } catch (\Exception $e) {
            throw $e;
        }
        // Get response
        try {
            $response = $this->transport->read();
        } catch (\Exception $e) {
            throw $e;
        }
        $response->decodeUsing($this->encoder);
        $this->postResponse($response);
        // Errors
        if ($response->errors) {
            $e = new \Exception('Errors with request');
            $e->errors = $response->errors;
            throw $e;
        }

        return $response->body;
    }

    protected function preRequest($request) {
    }
    protected function postResponse($response) {
    }
}

