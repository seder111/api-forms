<?php

declare(strict_types=1);

use App\Http\ContactController;

Flight::route('GET /', static function (): void {
    Flight::json([
        'status' => 'ok',
        'service' => 'api-forms',
    ]);
});

Flight::route('POST /contact', [ContactController::class, 'store']);
