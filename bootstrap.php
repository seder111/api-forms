<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// The .env can live next to bootstrap.php or one level above (recommended:
// keeping it outside api-forms/ lets a deploy replace the whole folder).
// The first file found wins, so an .env inside the app dir takes priority.
Dotenv::createImmutable([__DIR__, dirname(__DIR__)])->safeLoad();

ini_set('display_errors', ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/src/routes.php';

Flight::start();
