<?php

declare(strict_types=1);

namespace App\Plunk;

/**
 * Minimal HTTP client for the Plunk API, shared by every Plunk feature.
 *
 * Best-effort by design: a missing API key, curl failure, or non-2xx
 * response is logged via error_log() and reported as null — it never
 * throws, so callers can never block the user-facing redirect.
 */
final class Client
{
    private const BASE_URL = 'https://next-api.useplunk.com';
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const TIMEOUT_SECONDS = 5;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null Decoded JSON response, or null on any failure.
     */
    public static function post(string $path, array $payload): ?array
    {
        return self::request('POST', $path, $payload, []);
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>|null Decoded JSON response, or null on any failure.
     */
    public static function get(string $path, array $query = []): ?array
    {
        return self::request('GET', $path, null, $query);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $query
     * @return array<string, mixed>|null
     */
    private static function request(string $method, string $path, ?array $payload, array $query): ?array
    {
        $apiKey = $_ENV['PLUNK_API_KEY'] ?? '';

        if ($apiKey === '') {
            error_log('Plunk API key is missing in environment variables.');
            return null;
        }

        $url = self::BASE_URL . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Curl error calling Plunk ' . $path . ': ' . $curlError);
            return null;
        }

        if ($httpCode >= 400) {
            error_log('Plunk API error (' . $path . ', ' . $httpCode . '): ' . $response);
            return null;
        }

        $decoded = json_decode((string) $response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
