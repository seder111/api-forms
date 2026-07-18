<?php

declare(strict_types=1);

namespace App\Http;

use App\Mail\PlunkMailer;
use App\Plunk\Contacts;

/**
 * Accepts any form: the controller has no hardcoded field names. Which field
 * is the email, which checkbox means "subscribe me", which fields are
 * required and which are Sí/No checkboxes all come from the .env
 * (CONTACT_EMAIL_FIELD, CONTACT_NEWSLETTER_FIELD, CONTACT_REQUIRED_FIELDS,
 * CONTACT_CHECKBOX_FIELDS). Only the email field is always required.
 */
final class ContactController
{
    private const MAX_EMAIL_LENGTH = 254;

    /** Generic per-value cap so a bot can't inflate the notification email. */
    private const MAX_FIELD_LENGTH = 5000;

    public static function store(): void
    {
        $fields = FormRequest::fields();

        $email = self::stringField($fields, self::envName('CONTACT_EMAIL_FIELD', 'email'));
        $subscribed = ($fields[self::envName('CONTACT_NEWSLETTER_FIELD', 'newsletter')] ?? null) === '1';

        if (!self::isValid($email, $fields)) {
            self::redirect(self::redirectUrl('CONTACT_ERROR_URL', '/contacto/?error=validation'));
        }

        PlunkMailer::send(self::subject($fields, $email), self::humanizeCheckboxes($fields));

        Contacts::save($email, $subscribed, $fields);

        self::redirect(self::redirectUrl('CONTACT_SUCCESS_URL', '/gracias/'));
    }

    /**
     * CONTACT_EMAIL_SUBJECT may reference any submitted field as {campo},
     * e.g. "Nuevo mensaje de {nom}". Placeholders for fields that were not
     * submitted are left as-is, which makes a misconfigured name visible.
     *
     * @param array<string, string|array<int, string>> $fields
     */
    private static function subject(array $fields, string $email): string
    {
        $configured = trim((string) ($_ENV['CONTACT_EMAIL_SUBJECT'] ?? ''));

        if ($configured === '') {
            return 'New contact message from ' . $email;
        }

        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $configured = str_replace('{' . $key . '}', $value, $configured);
            }
        }

        return $configured;
    }

    /**
     * Translates the checkboxes listed in CONTACT_CHECKBOX_FIELDS to Sí/No
     * before handing the data to the generic mailer, which must not assume
     * "1" means "yes".
     *
     * @param array<string, string|array<int, string>> $fields
     * @return array<string, string|array<int, string>>
     */
    private static function humanizeCheckboxes(array $fields): array
    {
        foreach (self::envList('CONTACT_CHECKBOX_FIELDS') as $checkbox) {
            if (array_key_exists($checkbox, $fields)) {
                $fields[$checkbox] = $fields[$checkbox] === '1' ? 'Sí' : 'No';
            }
        }

        return $fields;
    }

    /**
     * The email field must hold a valid address, every field listed in
     * CONTACT_REQUIRED_FIELDS must have a value (a checked checkbox sends
     * "1", so required consent checkboxes work too), and no value may exceed
     * the generic length cap.
     *
     * @param array<string, string|array<int, string>> $fields
     */
    private static function isValid(string $email, array $fields): bool
    {
        if (
            $email === ''
            || strlen($email) > self::MAX_EMAIL_LENGTH
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return false;
        }

        foreach (self::envList('CONTACT_REQUIRED_FIELDS') as $required) {
            if (($fields[$required] ?? '') === '' || ($fields[$required] ?? []) === []) {
                return false;
            }
        }

        foreach ($fields as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (strlen($item) > self::MAX_FIELD_LENGTH) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * A field posted as an array (e.g. "email[]=x") reads as empty so it
     * fails validation instead of casting to the literal "Array".
     *
     * @param array<string, string|array<int, string>> $fields
     */
    private static function stringField(array $fields, string $key): string
    {
        $value = $fields[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** Env-configured field name with a default when unset or blank. */
    private static function envName(string $envKey, string $default): string
    {
        $name = trim((string) ($_ENV[$envKey] ?? ''));

        return $name !== '' ? $name : $default;
    }

    /**
     * Comma-separated env list, trimmed, empty entries ignored.
     *
     * @return array<int, string>
     */
    private static function envList(string $envKey): array
    {
        $entries = array_map('trim', explode(',', (string) ($_ENV[$envKey] ?? '')));

        return array_values(array_filter($entries, static fn (string $entry): bool => $entry !== ''));
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
