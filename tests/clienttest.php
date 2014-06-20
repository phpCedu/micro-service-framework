<?php

include('setup.php');

// Service knows where the endpoint is, which calls are available, which protocol, and which transport
$service = new MyService();
$client = $service->client();

//$out = $client->childReverse('Alan');
$out = $client->reverse('hey');

var_dump($out);
var_dump($client->response);
