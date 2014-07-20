<?php
namespace MSF;

abstract class Client {
    protected $service;
    protected $transport;
    public $filters = array();
    public $response;

    public function __construct(\MSF\Service $service, \MSF\Transport $transport) {
        $this->service = $service;
        $this->transport = $transport;
        $this->setup();
    }
    public function setup() {
    }

    public function __call($name, $args) {
        $definition = $this->service->definition();
        $transport = $this->transport;
        
        $request = $transport->newRequest();
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

        foreach ($this->filters as $filter) {
            $request = $filter->request($request);
        }
        try {
            $transport->writeRequest($request);
        } catch (\Exception $e) {
            throw $e;
        }
        // Get response
        try {
            $response = $transport->readResponse();
        } catch (\Exception $e) {
            throw $e;
        }

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

