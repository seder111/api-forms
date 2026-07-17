<?php

declare(strict_types=1);

namespace App\Plunk;

/**
 * Saves form submitters as Plunk contacts. Best-effort, never blocks the
 * caller. Opt-in per deployment via PLUNK_SAVE_CONTACTS.
 *
 * New contacts are created with their subscription state and the form fields
 * configured in PLUNK_CONTACT_FIELDS. Existing contacts are only ever
 * subscribed (when the submission asks for it): their data fields are never
 * modified and they are never unsubscribed from here.
 */
final class Contacts
{
    /**
     * @param array<string, string|array<int, string>> $fields Every submitted
     *        field, already trimmed. Only the ones listed in
     *        PLUNK_CONTACT_FIELDS are stored in the contact's data.
     */
    public static function save(string $email, bool $subscribed, array $fields): void
    {
        if (!self::enabled()) {
            return;
        }

        $contact = self::find($email);

        if ($contact === null) {
            error_log('Skipping Plunk contact save for ' . $email . ': existence lookup failed.');
            return;
        }

        if ($contact === false) {
            self::create($email, $subscribed, $fields);
            return;
        }

        if ($subscribed && ($contact['subscribed'] ?? false) !== true) {
            Client::post('/contacts/subscribe', ['email' => $email]);
        }
    }

    /**
     * Contact saving is opt-in per deployment: without PLUNK_SAVE_CONTACTS
     * set to true (or 1/on/yes) in the .env, submissions only trigger the
     * notification email.
     */
    private static function enabled(): bool
    {
        return filter_var($_ENV['PLUNK_SAVE_CONTACTS'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, string|array<int, string>> $fields
     */
    private static function create(string $email, bool $subscribed, array $fields): void
    {
        $payload = [
            'email' => $email,
            'subscribed' => $subscribed,
        ];

        $data = self::dataFromFields($fields);

        if ($data !== []) {
            $payload['data'] = $data;
        }

        Client::post('/contacts', $payload);
    }

    /**
     * The search parameter is a case-insensitive substring match, so the
     * results still need an exact email comparison. Returns the matching
     * contact, false when the email is not there, or null when the lookup
     * failed or the response shape is unrecognized.
     *
     * @return array<string, mixed>|false|null
     */
    private static function find(string $email): array|false|null
    {
        $result = Client::get('/contacts', ['search' => $email]);

        if ($result === null) {
            return null;
        }

        $contacts = array_is_list($result) ? $result : ($result['data'] ?? null);

        if (!is_array($contacts)) {
            return null;
        }

        foreach ($contacts as $contact) {
            if (is_array($contact) && strcasecmp((string) ($contact['email'] ?? ''), $email) === 0) {
                return $contact;
            }
        }

        return false;
    }

    /**
     * Picks the configured form fields out of the submission, skipping empty
     * values and joining multi-value fields (checkbox groups) with ", ".
     *
     * @param array<string, string|array<int, string>> $fields
     * @return array<string, string>
     */
    private static function dataFromFields(array $fields): array
    {
        $data = [];

        foreach (self::configuredFields() as $formField => $plunkKey) {
            $value = $fields[$formField] ?? '';

            if (is_array($value)) {
                $value = implode(', ', array_filter($value, static fn (string $item): bool => $item !== ''));
            }

            if ($value !== '') {
                $data[$plunkKey] = $value;
            }
        }

        return $data;
    }

    /**
     * Parses PLUNK_CONTACT_FIELDS, a comma-separated list of form fields to
     * store in the Plunk contact. Each entry is either "field" or
     * "field:plunkKey" to store it under a different name, e.g.
     * "nom:name,telefon". Empty or missing means no extra data is stored.
     *
     * @return array<string, string> Form field name => Plunk data key.
     */
    private static function configuredFields(): array
    {
        $configured = (string) ($_ENV['PLUNK_CONTACT_FIELDS'] ?? '');

        $map = [];

        foreach (explode(',', $configured) as $entry) {
            [$formField, $plunkKey] = array_pad(explode(':', $entry, 2), 2, null);

            $formField = trim((string) $formField);
            $plunkKey = trim((string) $plunkKey);

            if ($formField !== '') {
                $map[$formField] = $plunkKey !== '' ? $plunkKey : $formField;
            }
        }

        return $map;
    }
}
