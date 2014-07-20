<?php
namespace MSF;

interface TransportInterface {
    public function readRequest();
    public function readResponse();
    public function writeRequest(\MSF\Request $request);
    public function writeResponse(\MSF\Response $response);
    public function newRequest();
    public function newResponse();
}
