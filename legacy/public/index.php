<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ModuleManager.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Kernel.php';

$config = require __DIR__ . '/../config/config.php';

$kernel = new \CRMMarketing\Kernel($config);
$kernel->boot();

$request = new \CRMMarketing\Request();
$response = new \CRMMarketing\Response();
$router = $kernel->getRouter();

try {
    $result = $router->dispatch($request->getMethod(), $request->getPath());
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'status' => 500,
        'error' => $e->getMessage(),
    ]);
}