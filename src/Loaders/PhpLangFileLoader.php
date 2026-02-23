<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Loaders;

use ZPMLabs\LaravelI18nAudit\Support\KeyFlattener;
use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;

final class PhpLangFileLoader
{
    /**
     * @param array<int, string> $skipFiles
     * @return array{keys:array<int, string>,keySources:array<string, array<int, string>>,warnings:array<int, string>}
     */
    public function loadLocale(string $locale, string $langPath, array $skipFiles = []): array
    {
        $directory = rtrim($langPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $locale;
        $keys = [];
        $keySources = [];
        $warnings = [];

        if (!is_dir($directory)) {
            return ['keys' => [], 'keySources' => [], 'warnings' => []];
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $normalizedSkip = array_map(
            static fn (string $path): string => PathNormalizer::normalize($path),
            array_values(array_filter($skipFiles, static fn (string $entry): bool => trim($entry) !== ''))
        );

        foreach ($files as $file) {
            if ($this->shouldSkipFile($file, $langPath, $normalizedSkip)) {
                continue;
            }

            $basename = pathinfo($file, PATHINFO_FILENAME);
            $normalizedFile = PathNormalizer::normalize((string) realpath($file) ?: $file);

            try {
                /** @var mixed $loaded */
                $loaded = include $file;
            } catch (\Throwable $throwable) {
                $warnings[] = sprintf('Failed to load PHP lang file %s: %s', $file, $throwable->getMessage());
                continue;
            }

            if (!is_array($loaded)) {
                $warnings[] = sprintf('Lang PHP file does not return array: %s', $file);
                continue;
            }

            foreach (KeyFlattener::flatten($loaded) as $flattened) {
                $key = $basename . '.' . $flattened;
                $keys[] = $key;
                $keySources[$key] ??= [];
                if (!in_array($normalizedFile, $keySources[$key], true)) {
                    $keySources[$key][] = $normalizedFile;
                }
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        foreach ($keySources as &$sources) {
            sort($sources);
        }
        unset($sources);

        return [
            'keys' => $keys,
            'keySources' => $keySources,
            'warnings' => $warnings,
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
