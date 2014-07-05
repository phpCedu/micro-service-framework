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
            $args = array_fill(
                sizeof($args), 
                sizeof($names) - sizeof($args),
                null
            );
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
            die($e->getMessage());
        }
        // Get response
        $response = $this->transport->read();
        $response->decodeUsing($this->encoder);
        $this->postResponse($response);

        return $response->body;
    }

    protected function preRequest($request) {
    }
    protected function postResponse($response) {
    }
}

