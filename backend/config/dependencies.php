<?php
use Auryn\Injector;
use Medoo\Medoo;

return function (): Injector {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../app/logs/error.log');
    error_reporting(E_ALL);
    date_default_timezone_set('Africa/Nairobi');

    $injector = new Injector();

    $config   = require __DIR__ . '/config.php';
    $database = new Medoo($config['database']);

    try {
        $database->query("SET time_zone = 'Africa/Nairobi'");
    } catch (\Throwable $e) {
        try {
            $database->query("SET time_zone = '+03:00'");
        } catch (\Throwable $e2) {
            error_log('Failed to set MySQL time_zone: ' . $e->getMessage());
        }
    }

    $injector->share($database);
    return $injector;
};
