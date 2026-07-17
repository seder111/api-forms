<?php

declare(strict_types=1);

namespace App\Plunk;

final class Contacts
{
    /**
     * Creates the contact in Plunk only when the email is not already there.
     * Existing contacts are never modified: Plunk's POST /contacts upserts by
     * email, so an existence check runs first and, if the lookup fails, the
     * creation is skipped rather than risk overwriting a contact. Best-effort,
     * never blocks the caller.
     */
    public static function saveIfNew(string $email, string $name, bool $subscribed): void
    {
        $exists = self::exists($email);

        if ($exists === null) {
            error_log('Skipping Plunk contact creation for ' . $email . ': existence lookup failed.');
            return;
        }

        if ($exists) {
            return;
        }

        $payload = [
            'email' => $email,
            'subscribed' => $subscribed,
        ];

        if ($name !== '') {
            $payload['data'] = ['name' => $name];
        }

        Client::post('/contacts', $payload);
    }

    /**
     * The search parameter is a case-insensitive substring match, so the
     * results still need an exact email comparison. Returns null when the
     * lookup failed or the response shape is unrecognized.
     */
    private static function exists(string $email): ?bool
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
                return true;
            }
        }

        return false;
    }
}
