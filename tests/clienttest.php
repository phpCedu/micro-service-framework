<?php
error_reporting(E_ALL);

include('setup.php');

// Service knows where the endpoint is, which calls are available, which protocol, and which transport
$client = MyService::client();

try {
    $out = $client->reverse('hey', 1);
    // Our custome MyClient class stores profiling data in the response, and makes the response publicly available
    var_dump($client->response->oob('profile'));
    var_dump($out);
} catch (\Exception $e) {
    var_dump($e);
}


// More complex data types
/*
try {
    $data = array(
        'name' => 'Name',
        'ids' => array(1,5,34,77)
    );
    $out = $client->yessir($data);
    var_dump($out);
} catch (\Exception $e) {
    var_dump($e);
}
*/
