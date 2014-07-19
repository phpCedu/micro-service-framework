<?php

include('../setup.other-test.php');

$server = OtherTestService::server(new OtherTestServiceHandler);
$server->run();
