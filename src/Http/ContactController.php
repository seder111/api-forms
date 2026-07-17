<?php

declare(strict_types=1);

namespace App\Http;

use App\Mail\PlunkMailer;
use App\Plunk\Contacts;

final class ContactController
{
    private const MAX_NAME_LENGTH = 100;
    private const MAX_EMAIL_LENGTH = 254;

    /** Checkbox fields whose raw "1"/"" value should read as Sí/No in the email. */
    private const CHECKBOX_FIELDS = ['privacitat', 'newsletter'];

    public static function store(): void
    {
        $fields = FormRequest::fields();

        $name = self::stringField($fields, 'nom');
        $email = self::stringField($fields, 'email');
        $privacyAccepted = ($fields['privacitat'] ?? null) === '1';
        $newsletterAccepted = ($fields['newsletter'] ?? null) === '1';

        if (!self::isValid($name, $email, $privacyAccepted)) {
            self::redirect(self::redirectUrl('CONTACT_ERROR_URL', '/contacto/?error=validation'));
        }

        PlunkMailer::send(self::subject($name), self::humanizeCheckboxes($fields));

        Contacts::save($email, $newsletterAccepted, $fields);

        self::redirect(self::redirectUrl('CONTACT_SUCCESS_URL', '/gracias/'));
    }

    /**
     * A field posted as an array (e.g. "nom[]=x") reads as empty so it fails
     * validation instead of casting to the literal "Array".
     *
     * @param array<string, string|array<int, string>> $fields
     */
    private static function stringField(array $fields, string $key): string
    {
        $value = $fields[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private static function subject(string $name): string
    {
        $configured = trim((string) ($_ENV['CONTACT_EMAIL_SUBJECT'] ?? ''));

        if ($configured !== '') {
            return str_replace('{nombre}', $name, $configured);
        }

        return $name !== ''
            ? 'New contact message from ' . $name
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

    /**
     * Only same-site local paths are allowed as redirect targets: anything
     * else in the env var (external, protocol-relative or empty) falls back,
     * so a misconfigured .env can never become an open redirect.
     */
    private static function redirectUrl(string $envKey, string $fallback): string
    {
        $url = (string) ($_ENV[$envKey] ?? '');

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
