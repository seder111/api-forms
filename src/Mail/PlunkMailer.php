<?php

declare(strict_types=1);

namespace App\Mail;

use App\Plunk\Client;
use App\Support\Env;

final class PlunkMailer
{
    /**
     * Sends a best-effort HTML notification email listing every submitted
     * field. Never blocks the caller: missing config, curl errors, and
     * non-2xx API responses are logged via error_log() and swallowed.
     *
     * Enabled by default; a deployment that only saves contacts can opt out
     * with PLUNK_SEND_EMAIL=false (skips silently, no config check).
     *
     * @param array<string, string|array<int, string>> $fields Field name => value,
     *        already trimmed by the caller. Values are HTML-escaped here.
     */
    public static function send(string $subject, array $fields): void
    {
        if (!Env::bool('PLUNK_SEND_EMAIL', true)) {
            return;
        }

        $config = self::config();

        if ($config['from'] === '' || $config['to'] === '') {
            error_log('Plunk configuration is missing in environment variables.');
            return;
        }

        Client::post('/v1/send', [
            'to' => $config['to'],
            'subject' => $subject,
            'body' => self::buildHtmlBody($fields),
            'from' => $config['from'],
            'name' => $config['nameFrom'],
        ]);
    }

    /**
     * @param array<string, string|array<int, string>> $fields
     */
    private static function buildHtmlBody(array $fields): string
    {
        $rows = '';

        foreach ($fields as $key => $value) {
            $rows .= '<p><strong>' . htmlspecialchars(self::humanizeLabel((string) $key)) . ':</strong> '
                . self::formatValue($value) . '</p>';
        }

        return $rows;
    }

    private static function humanizeLabel(string $key): string
    {
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $key) ?? $key;
        $spaced = str_replace(['_', '-'], ' ', $spaced);

        return ucfirst(strtolower($spaced));
    }

    /**
     * @param string|array<int, string> $value
     */
    private static function formatValue(string|array $value): string
    {
        if (is_array($value)) {
            return htmlspecialchars(implode(', ', $value));
        }

        return htmlspecialchars($value);
    }

    /**
     * @return array{from: string, to: string, nameFrom: string}
     */
    private static function config(): array
    {
        return [
            'from' => Env::string('PLUNK_FROM'),
            'to' => Env::string('CONTACT_RECIPIENT'),
            'nameFrom' => Env::string('PLUNK_NAME_FROM'),
        ];
    }
}
