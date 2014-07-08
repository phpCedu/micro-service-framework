<?php

include('../setup.php');

$server = MyNewerService::server(new MyNewerServiceHandler);
$server->run();
