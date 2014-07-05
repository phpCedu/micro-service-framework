<?php

include('../setup.php');

$server = MyService::server(new MyServiceHandler);
$server->run();
