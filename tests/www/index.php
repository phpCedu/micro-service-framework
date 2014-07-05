<?php

include('../setup.php');

/*
Still fuzzy on how to decouple the RPC call names from their implementing class ...
*/

// On the client side, it's easy for Service to define the transports and the protocol ... not so on the Server side
//$server->inTransport = $inTransport; // In transport determines the starting class of the request
//$server->outTransport = $outTransport; // Out transport determines the starting class of the response, I think ...
$server = MyService::server(new MyServiceHandler);

// Transport determines the type of request and response (BaseRequestResponse or other), though Filters can change this

$server->filters[] = new MyFilterDoesProfiling();
$server->filters[] = new MyFilterConvertsRequest();

$server->run();
