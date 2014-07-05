<?php
error_reporting(E_ALL);

include('setup.php');

// Service knows where the endpoint is, which calls are available, which protocol, and which transport
$client = MyService::client();

//$out = $client->childReverse('Alan');
$out = $client->reverse('hey');

var_dump($out);
