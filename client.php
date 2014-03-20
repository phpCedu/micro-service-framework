<?php

$data = array(
    'rpc' => array('MyService', 'childReverse'),
    'args' => array('Alan')
);

$ch = curl_init('http://localhost:9999/index.php');
if(!$ch) {
    die('Curl Error');
}
curl_setopt($ch, CURLOPT_HEADER, 1); // set to 0 to eliminate header info from response
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch); //execute post and get results

$success = false;
if($response === false) {
    $e = curl_error($ch);
} else {
    var_dump($response);
}
