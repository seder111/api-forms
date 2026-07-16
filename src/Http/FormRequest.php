<?php

declare(strict_types=1);

namespace App\Http;

final class FormRequest
{
    /**
     * Returns every submitted field, trimmed. Includes all keys present in
     * the source array, whatever they are called — no per-project field
     * knowledge lives here.
     *
     * @param array<string, mixed>|null $source Defaults to $_POST.
     * @return array<string, string|array<int, string>>
     */
    public static function fields(?array $source = null): array
    {
        $source ??= $_POST;

        $fields = [];

        foreach ($source as $key => $value) {
            $fields[(string) $key] = self::normalize($value);
        }

        return $fields;
    }

    /**
     * @return string|array<int, string>
     */
    private static function normalize(mixed $value): string|array
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): string => trim((string) $item), $value);
        }

        return trim((string) $value);
    }
}
