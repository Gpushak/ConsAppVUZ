<?php

namespace App\Support;

class SearchText
{
    public static function fold(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function matches(string $haystack, string $query): bool
    {
        $tokens = self::words($query);
        if ($tokens === []) {
            return true;
        }

        $words = self::words($haystack);
        foreach ($tokens as $token) {
            $found = false;
            foreach ($words as $word) {
                if (str_starts_with($word, $token)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function words(string $value): array
    {
        $folded = self::fold($value);
        if ($folded === '') {
            return [];
        }

        return explode(' ', $folded);
    }
}
