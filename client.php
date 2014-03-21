<?php

include('setup.php');

// Service knows where the endpoint is, which calls are available, which protocol, and which transport
$service = new MyService();
$client = $service->client();

$out = $client->reverse('Alan');

var_dump($out);
