<?php

declare(strict_types=1);

namespace App\Http;

use App\Mail\PlunkMailer;

final class ContactController
{
    private const MAX_NAME_LENGTH = 100;
    private const MAX_EMAIL_LENGTH = 254;

    /** Checkbox fields whose raw "1"/"" value should read as Sí/No in the email. */
    private const CHECKBOX_FIELDS = ['privacitat', 'newsletter'];

    public static function store(): void
    {
        $fields = FormRequest::fields();

        $name = (string) ($fields['nom'] ?? '');
        $email = (string) ($fields['email'] ?? '');
        $privacyAccepted = ($fields['privacitat'] ?? null) === '1';

        if (!self::isValid($name, $email, $privacyAccepted)) {
            self::redirect(self::errorUrl());
        }

        PlunkMailer::send(self::subject($name), self::humanizeCheckboxes($fields));

        self::redirect(self::successUrl());
    }

    private static function subject(string $name): string
    {
        $configured = trim((string) ($_ENV['CONTACT_EMAIL_SUBJECT'] ?? ''));

        if ($configured !== '') {
            return str_replace('{nombre}', $name, $configured);
        }

        return $name !== ''
            ? 'New contact message from' . $name
            : 'New contact message';
    }

    /**
     * Translates this form's known checkbox fields to Sí/No before handing
     * the data to the generic mailer, which must not assume "1" means "yes".
     *
     * @param array<string, string|array<int, string>> $fields
     * @return array<string, string|array<int, string>>
     */
    private static function humanizeCheckboxes(array $fields): array
    {
        foreach (self::CHECKBOX_FIELDS as $checkbox) {
            if (array_key_exists($checkbox, $fields)) {
                $fields[$checkbox] = $fields[$checkbox] === '1' ? 'Sí' : 'No';
            }
        }

        return $fields;
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
