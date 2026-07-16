<?php

declare(strict_types=1);

namespace App\Http;

final class ContactController
{
    private const MAX_NAME_LENGTH = 100;
    private const MAX_EMAIL_LENGTH = 254;

    public static function store(): void
    {
        $name = trim((string) ($_POST['nom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $privacyAccepted = ($_POST['privacitat'] ?? null) === '1';
        $newsletterAccepted = ($_POST['newsletter'] ?? null) === '1';

        if (!self::isValid($name, $email, $privacyAccepted)) {
            self::redirect(self::errorUrl());
        }

        self::sendContactEmail($name, $email, $newsletterAccepted);

        // En el siguiente paso se guardará también el consentimiento opcional.
        unset($newsletterAccepted);

        self::redirect(self::successUrl());
    }

    private static function sendContactEmail(string $name, string $email, bool $newsletterAccepted): void
    {
        $apiKey = $_ENV['PLUNK_API_KEY'] ?? '';
        $from = $_ENV['PLUNK_FROM'] ?? '';
        $to = $_ENV['CONTACT_RECIPIENT'] ?? '';
        $nameFrom = $_ENV['PLUNK_NAME_FROM'] ?? '';

        if ($apiKey === '' || $from === '' || $to === '') {
            error_log('Plunk configuration is missing in environment variables.');
            return;
        }

        $newsletterStatus = $newsletterAccepted ? 'Sí' : 'No';

        $subject = "Nuevo mensaje de contacto de " . $name;
        $body = "<p><strong>Nombre:</strong> " . htmlspecialchars($name) . "</p>" .
            "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>" .
            "<p><strong>Acepta Newsletter:</strong> " . $newsletterStatus . "</p>";

        $ch = curl_init("https://next-api.useplunk.com/v1/send");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "to" => $to,
                "subject" => $subject,
                "body" => $body,
                "from" => $from,
                "name" => $nameFrom,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            error_log('Curl error sending email via Plunk: ' . curl_error($ch));
        } elseif ($httpCode >= 400) {
            error_log('Plunk API error (' . $httpCode . '): ' . $response);
        }

        curl_close($ch);
    }

    private static function isValid(
        string $name,
        string $email,
        bool $privacyAccepted
    ): bool {
        return $name !== ''
            && strlen($name) <= self::MAX_NAME_LENGTH
            && $email !== ''
            && strlen($email) <= self::MAX_EMAIL_LENGTH
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && $privacyAccepted;
    }

    private static function successUrl(): string
    {
        return self::localUrl($_ENV['CONTACT_SUCCESS_URL'] ?? '/gracias/', '/gracias/');
    }

    private static function errorUrl(): string
    {
        return self::localUrl(
            $_ENV['CONTACT_ERROR_URL'] ?? '/contacto/?error=validation',
            '/contacto/?error=validation'
        );
    }

    private static function localUrl(string $url, string $fallback): string
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//')
            ? $url
            : $fallback;
    }

    private static function redirect(string $url): never
    {
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 303);
        exit;
    }
}
