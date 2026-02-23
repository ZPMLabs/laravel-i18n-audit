<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class KeyFlattener
{
    /**
     * @param array<string, mixed> $items
     * @return array<int, string>
     */
    public static function flatten(array $items, string $prefix = ''): array
    {
        $keys = [];

        foreach ($items as $key => $value) {
            $composed = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $keys = array_merge($keys, self::flatten($value, $composed));
                continue;
            }

            $keys[] = $composed;
        }

        sort($keys);

        return $keys;
    }
}
