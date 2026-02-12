<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/error.log';
$rotatedLogFile = $logDir . '/error.log.1';
$maxLogSizeBytes = 2 * 1024 * 1024;
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
if (!file_exists($logFile)) {
    @file_put_contents($logFile, "");
}
if (file_exists($logFile) && @filesize($logFile) > $maxLogSizeBytes) {
    @unlink($rotatedLogFile);
    if (!@rename($logFile, $rotatedLogFile)) {
        @file_put_contents($logFile, "");
    } else {
        @file_put_contents($logFile, "");
    }
}

ini_set('error_log', $logFile);
error_reporting(E_ALL);
date_default_timezone_set('Africa/Nairobi');

set_error_handler(function ($severity, $message, $file, $line) use ($logFile) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $origin = $trace[1] ?? null;
    $originText = '';
    if ($origin) {
        $originText = sprintf(
            " | origin: %s%s() in %s:%s",
            $origin['class'] ?? '',
            $origin['function'] ?? 'unknown',
            $origin['file'] ?? 'unknown',
            $origin['line'] ?? '0'
        );
    }
    $entry = sprintf("[%s] PHP Error (%s) %s in %s:%d%s\n", date('c'), $severity, $message, $file, $line, $originText);
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    return false;
});

set_exception_handler(function ($exception) use ($logFile) {
    $entry = sprintf(
        "[%s] Uncaught Exception %s: %s in %s:%d\nStack: %s\n",
        date('c'),
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error']);
});

require __DIR__ . '/../../vendor/autoload.php';

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Auryn\Injector;

$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:5174"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    exit(0);
}

$injectorFactory = require __DIR__ . '/../../config/dependencies.php';
$injector = $injectorFactory();

$routes = require __DIR__ . '/routes.php';
$dispatcher = simpleDispatcher($routes);

$basePath = str_replace($_SERVER['SCRIPT_NAME'], '', dirname($_SERVER['SCRIPT_NAME']));
$uri = substr($_SERVER['REQUEST_URI'], strlen($basePath));
$uri = '/' . trim($uri, '/');

$uri = str_replace($basePath, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (strpos($uri, '/index.php') === 0) {
    $uri = substr($uri, strlen('/index.php'));
}
$uri = '/' . ltrim($uri, '/');
$httpMethod = $_SERVER['REQUEST_METHOD'];

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;

    case FastRoute\Dispatcher::FOUND:
        [$controllerName, $methodName] = explode('@', $routeInfo[1]);
        $controllerClass = "\\App\\Controllers\\$controllerName";

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Controller $controllerClass not found"]);
            exit;
        }

        try {
            $controller = $injector->make($controllerClass);
            if (!method_exists($controller, $methodName)) {
                throw new \Exception("Method $methodName not found");
            }
            call_user_func_array([$controller, $methodName], $routeInfo[2]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
