<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Loaders;

use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;

final class JsonLangFileLoader
{
    /**
     * @param array<int, string> $skipFiles
     * @return array{keys:array<int, string>,keySources:array<string, array<int, string>>,warnings:array<int, string>}
     */
    public function loadLocale(string $locale, string $langPath, array $skipFiles = []): array
    {
        $file = rtrim($langPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $locale . '.json';

        $normalizedSkip = array_map(
            static fn (string $path): string => PathNormalizer::normalize($path),
            array_values(array_filter($skipFiles, static fn (string $entry): bool => trim($entry) !== ''))
        );

        if (!is_file($file)) {
            return ['keys' => [], 'keySources' => [], 'warnings' => []];
        }

        if ($this->shouldSkipFile($file, $langPath, $normalizedSkip)) {
            return ['keys' => [], 'keySources' => [], 'warnings' => []];
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return ['keys' => [], 'keySources' => [], 'warnings' => [sprintf('Failed to read JSON lang file: %s', $file)]];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return ['keys' => [], 'keySources' => [], 'warnings' => [sprintf('Invalid JSON lang file: %s', $file)]];
        }

        $keys = array_map(static fn (string $key): string => $key, array_keys($decoded));
        sort($keys);

        $normalizedFile = PathNormalizer::normalize((string) realpath($file) ?: $file);
        $keySources = [];

        foreach ($keys as $key) {
            $keySources[$key] = [$normalizedFile];
        }

        return [
            'keys' => $keys,
            'keySources' => $keySources,
            'warnings' => [],
        ];
    }

    /**
     * @param array<int, string> $normalizedSkip
     */
    private function shouldSkipFile(string $filePath, string $langPath, array $normalizedSkip): bool
    {
        if ($normalizedSkip === []) {
            return false;
        }

        $normalizedAbsolute = PathNormalizer::normalize((string) realpath($filePath) ?: $filePath);
        $normalizedRelative = PathNormalizer::relativeTo($langPath, $filePath);
        $normalizedRelative = PathNormalizer::normalize($normalizedRelative);

        foreach ($normalizedSkip as $skip) {
            $candidate = trim($skip);

            if ($candidate === '') {
                continue;
            }

            if (str_contains(strtolower($normalizedAbsolute), strtolower($candidate))) {
                return true;
            }

            if (str_contains(strtolower($normalizedRelative), strtolower($candidate))) {
                return true;
            }
        }

        return false;
    }
}
