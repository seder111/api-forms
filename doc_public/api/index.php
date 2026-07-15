<?php

declare(strict_types=1);

$parentDirectory = dirname(__DIR__, 2);
$bootstrap = $parentDirectory . '/api-forms/bootstrap.php';

// Permite probar doc_public dentro del proyecto en desarrollo.
if (!is_file($bootstrap)) {
    $bootstrap = $parentDirectory . '/bootstrap.php';
}

if (!is_file($bootstrap)) {
    http_response_code(500);
    exit('Application bootstrap not found.');
}

require $bootstrap;
