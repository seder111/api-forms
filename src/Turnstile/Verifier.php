<?php

declare(strict_types=1);

namespace App\Turnstile;

use App\Support\Env;

/**
 * Server-side verification for Cloudflare Turnstile
 * (https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).
 *
 * Unlike the Plunk integrations, this is not best-effort: it is the only
 * server-side line of defense against a bot that submits the form while
 * ignoring the widget's JavaScript entirely, so any failure (missing token,
 * network error, non-2xx response, negative verdict) rejects the
 * submission instead of letting it through.
 *
 * Opt-in per deployment via TURNSTILE_SECRET_KEY: sites that don't embed
 * the widget leave it unset and verification is skipped.
 */
final class Verifier
{
    public const FIELD = 'cf-turnstile-response';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const TIMEOUT_SECONDS = 5;

    /** False when TURNSTILE_SECRET_KEY is unset: the deployment opted out. */
    public static function enabled(): bool
    {
        return Env::string('TURNSTILE_SECRET_KEY') !== '';
    }

    /**
     * Verifies the token submitted by the widget in the "cf-turnstile-response"
     * field against Cloudflare's siteverify endpoint.
     */
    public static function verify(string $token, ?string $remoteIp): bool
    {
        $secret = Env::string('TURNSTILE_SECRET_KEY');

        if ($secret === '' || $token === '') {
            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];

        if ($remoteIp !== null && $remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Curl error calling Turnstile siteverify: ' . $curlError);
            return false;
        }

        if ($httpCode >= 400) {
            error_log('Turnstile siteverify HTTP error (' . $httpCode . '): ' . $response);
            return false;
        }

        $decoded = json_decode((string) $response, true);

        if (!is_array($decoded) || ($decoded['success'] ?? false) !== true) {
            error_log('Turnstile verification failed: ' . (string) $response);
            return false;
        }

        return true;
    }
}
