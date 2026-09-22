<?php

declare(strict_types=1);

use App\Promoting\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$environment = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: 'dev');
$debug = filter_var($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? (getenv('APP_DEBUG') ?: '0'), FILTER_VALIDATE_BOOL);

$kernel = new Kernel($environment, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();

if (method_exists($kernel, 'terminate')) {
    $kernel->terminate($request, $response);
}
