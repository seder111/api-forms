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

        // En el siguiente paso se guardará también el consentimiento opcional.
        unset($newsletterAccepted);

        self::redirect(self::successUrl());
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
        return self::localUrl($_ENV['CONTACT_SUCCESS_URL'] ?? '/gracies/', '/gracies/');
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
