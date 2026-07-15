<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

Dotenv::createImmutable(__DIR__)->safeLoad();

ini_set('display_errors', ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/src/routes.php';

Flight::start();
