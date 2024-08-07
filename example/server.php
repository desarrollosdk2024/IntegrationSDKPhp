<?php


require __DIR__ . '/../vendor/autoload.php';

use IntegrationPos\IntegrationPos;

$integration = new IntegrationPos();
$integration->initialize('0.0.0.0', 5050, ["device001" => "192.168.1.181"  ]);
$integration->setToken("ANB7xKhiUZmwltVd3f1odcHHM9VAwg02kwmLwtZwHv3SxGCOWLUf5W4G7X22PRj");
$integration->start();