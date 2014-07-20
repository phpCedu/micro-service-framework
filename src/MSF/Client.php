<?php
namespace MSF;

abstract class Client {
    protected $serviceDefinition;
    protected $transport;
    protected $encoder;
    public $filters = array();
    public $response;

    public function __construct($serviceDefinition, \MSF\TransportInterface $transport, \MSF\EncoderInterface $encoder) {
        $this->serviceDefinition = $serviceDefinition;
        $this->transport = $transport;
        $this->encoder = $encoder;
    }

    public function __call($name, $args) {
        $definition = $this->serviceDefinition;
        
        $request = $this->transport->newRequest();
        $request->rpc = $name;

        // prepare args key/value struct, leaving out null values
        $mapped = array();
        if (sizeof($definition[$name]) > 1) {
            $names = $definition[ $name ][1];
            foreach ($args as $i => $value) {
                if (!is_null($value)) {
                    $mapped[ $names[$i] ] = $value;
                }
            }
        }
        $request->args = $mapped;

        // Don't encode empty body
        $request->encodeUsing($this->encoder, true);

        foreach ($this->filters as $filter) {
            $request = $filter->request($request);
        }
        try {
            $this->transport->writeRequest($request);
        } catch (\Exception $e) {
            throw $e;
        }
        // Get response
        try {
            $response = $this->transport->readResponse();
        } catch (\Exception $e) {
            throw $e;
        }
        $response->decodeUsing($this->encoder);

        // Filter response in reverse order because ... can't remember why
        foreach (array_reverse($this->filters) as $filter) {
            $response = $filter->response($response);
        }
        // Save response so we can get OOB data from it, but there's probably a better way
        $this->response = $response;

        // Errors
        // Guess we're throwing an exception here, because otherwise the request is expected to return the $response->body
        // hmm ...
        if ($response->errors) {
            $e = new \Exception('Errors with request');
            $e->more = $response;
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

