<?php

declare(strict_types=1);

Flight::route('GET /', static function (): void {
    Flight::json([
        'status' => 'ok',
        'service' => 'api-forms',
    ]);
});
