<?php

declare(strict_types=1);

namespace App\Mail;

final class PlunkMailer
{
    /**
     * Sends a best-effort HTML notification email listing every submitted
     * field. Never blocks the caller: missing config, curl errors, and
     * non-2xx API responses are logged via error_log() and swallowed.
     *
     * @param array<string, string|array<int, string>> $fields Field name => value,
     *        already trimmed by the caller. Values are HTML-escaped here.
     */
    public static function send(string $subject, array $fields): void
    {
        $config = self::config();

        if ($config['apiKey'] === '' || $config['from'] === '' || $config['to'] === '') {
            error_log('Plunk configuration is missing in environment variables.');
            return;
        }

        $ch = curl_init('https://next-api.useplunk.com/v1/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['apiKey'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'to' => $config['to'],
                'subject' => $subject,
                'body' => self::buildHtmlBody($fields),
                'from' => $config['from'],
                'name' => $config['nameFrom'],
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
     * @return array{apiKey: string, from: string, to: string, nameFrom: string}
     */
    private static function config(): array
    {
        return [
            'apiKey' => $_ENV['PLUNK_API_KEY'] ?? '',
            'from' => $_ENV['PLUNK_FROM'] ?? '',
            'to' => $_ENV['CONTACT_RECIPIENT'] ?? '',
            'nameFrom' => $_ENV['PLUNK_NAME_FROM'] ?? '',
        ];
    }
}
