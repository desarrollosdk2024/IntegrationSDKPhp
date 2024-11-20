<?php


require __DIR__ . '/../vendor/autoload.php';

use IntegrationPos\IntegrationPos;

$integration = new IntegrationPos();
$integration->initialize('0.0.0.0', 5050, ["device001" => "192.168.1.181","device002"=>"172.20.0.1","device003"=>"192.168.1.1"]);
$integration->setToken("ANB7xKhiUZmwltVd3f1odcHHM9VAwg02kwmLwtZwHv3SxGCOWLUf5W4G7X22PRj");
$integration->setupLogging(__DIR__ . '/logs/app.log');
$integration->start();