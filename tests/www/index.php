<?php

include('../setup.php');

/*
Still fuzzy on how to decouple the RPC call names from their implementing class ...
*/


$service = new MyService();
// Thinking MyServer::$response should be public static ... nested client call response annotations would get pushed up into that
$server = MyServer::create($service);
// Transport determines the type of request and response (BaseRequestResponse or other), though Filters can change this
$inTransport = $outTransport = new \MSF\Transport\PartialHTTPTransport($service);

// On the client side, it's easy for Service to define the transports and the protocol ... not so on the Server side
$server->inTransport = $inTransport; // In transport determines the starting class of the request
$server->outTransport = $outTransport; // Out transport determines the starting class of the response, I think ...

$server->filters[] = new MyFilterDoesProfiling();
$server->filters[] = new MyFilterConvertsRequest();

$server->run();
