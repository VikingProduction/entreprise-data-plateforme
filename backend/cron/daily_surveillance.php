<?php
require __DIR__.'/../surveillance/SurveillanceSystem.php';
$sys = new SurveillanceSystem();
$results = $sys->checkAllSurveillances();
echo json_encode($results);
