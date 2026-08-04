<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single access point for environment configuration. Values are read from
 * $_ENV on every call — nothing is cached or injected — so each request
 * sees the deployment's current .env as loaded by bootstrap.php.
 */
final class Env
{
    /** Trimmed value, falling back to the default when unset or blank. */
    public static function string(string $key, string $default = ''): string
    {
        $value = trim((string) ($_ENV[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }

    /**
     * Comma-separated list: entries trimmed, empty entries ignored.
     *
     * @return array<int, string>
     */
    public static function list(string $key): array
    {
        $entries = array_map('trim', explode(',', (string) ($_ENV[$key] ?? '')));

        return array_values(array_filter($entries, static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * True for "1", "true", "on" or "yes"; false for "0", "false", "off" or
     * "no". Unset, blank or unrecognized values fall back to the default.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $raw = trim((string) ($_ENV[$key] ?? ''));

        if ($raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
