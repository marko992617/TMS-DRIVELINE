<?php
require __DIR__ . '/../vendor/autoload.php';
use App\Controller\PayrollController;

$controller = new PayrollController();
$controller->render();
