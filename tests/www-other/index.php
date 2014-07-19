<?php

include('../setup.php');

$server = OtherTestService::server(new OtherTestServiceHandler);
$server->run();
