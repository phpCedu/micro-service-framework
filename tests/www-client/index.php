<?php

include('../setup.client-test.php');

$server = ClientTestService2::server(new ClientTestServiceHandler2);
$server->run();
