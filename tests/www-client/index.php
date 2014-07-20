<?php

include('../setup.client-test.php');

$server = ClientTestService2::getInstance()->server();
$server->run();
