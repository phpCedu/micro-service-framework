<?php

include('../setup.php');

$server = ClientTestService2::server(new ClientTestServiceHandler2);
$server->run();
