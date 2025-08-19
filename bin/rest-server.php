#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FSM\Infrastructure\Transport\Rest\RestServer;

// Get configuration from environment
$host = getenv('REST_HOST') ?: '0.0.0.0';
$port = (int)(getenv('REST_PORT') ?: 8080);

try {
    $server = new RestServer($host, $port);
    $server->start();
} catch (\Exception $e) {
    echo "Failed to start REST server: {$e->getMessage()}\n";
    exit(1);
}