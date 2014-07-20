<?php
namespace MSF;

interface TransportInterface {
    public function read();
    public function write(\MSF\RequestResponse $request);
    public function newRequest();
    public function newResponse();
}
