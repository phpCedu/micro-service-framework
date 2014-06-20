<?php
namespace MSF;

class Client {
    public $service;
    public $request;
    public $response;

    public function __call($name, $args) {
        $service = $this->service;
        $transport = $service->transport;
        $encoder = $service->encoder;
        
        $request = $transport->newRequest();
        $request->rpc = $name;
        // combine service's param names with the values into key/value pairs
        // make sure $args is same size as definition
        $names = $service->definition[ $name ][1];
        if (sizeof($args) < sizeof($names)) {
            $args = array_fill(
                sizeof($args), 
                sizeof($names) - sizeof($args),
                null
            );
        }
        //var_dump($names);var_dump($args);exit;
        $request->args = array_combine(
            $names,
            $args
        );
        // Don't encode empty body
        $request->encodeUsing($encoder, true);

        $this->preRequest($request);
        $transport->write($request);
        // Get response
        $response = $transport->read();
        $response->decodeUsing($encoder);
        $this->postResponse($response);

        // For posterity
        $this->request = $request;
        $this->response = $response;
        return $response->body;
    }

    protected function preRequest($request) {
    }
    protected function postResponse($response) {
    }
}

