<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class MissingTranslationPopulator
{
    /**
     * @param array<string, array<int, string>> $missingByLocale
     * @return array{created:int,updatedLocales:array<int,string>}
     */
    public function populate(array $missingByLocale, string $langPath, string $template): array
    {
        $created = 0;
        $updatedLocales = [];

        foreach ($missingByLocale as $locale => $missingKeys) {
            if ($missingKeys === []) {
                continue;
            }

            $localeUpdated = false;
            $phpBuckets = [];
            $jsonKeys = [];

            foreach ($missingKeys as $key) {
                if ($this->isJsonLikeKey($key)) {
                    $jsonKeys[] = $key;
                    continue;
                }

                if (!str_contains($key, '.')) {
                    $jsonKeys[] = $key;
                    continue;
                }

                $parts = explode('.', $key);
                $group = array_shift($parts);

                if ($group === null || $group === '' || $parts === []) {
                    $jsonKeys[] = $key;
                    continue;
                }

                $phpBuckets[$group][] = $parts;
            }

            foreach ($phpBuckets as $group => $paths) {
                $filePath = $langPath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group . '.php';
                $existing = [];

                if (is_file($filePath)) {
                    /** @var mixed $loaded */
                    $loaded = include $filePath;
                    if (is_array($loaded)) {
                        $existing = $loaded;
                    }
                }

                foreach ($paths as $path) {
                    if ($this->setNestedIfMissing($existing, $path, str_replace('{$locale}', $locale, $template))) {
                        $created++;
                        $localeUpdated = true;
                    }
                }

                if ($localeUpdated) {
                    $this->writePhpLangFile($filePath, $existing);
                }
            }

            if ($jsonKeys !== []) {
                $jsonFilePath = $langPath . DIRECTORY_SEPARATOR . $locale . '.json';
                $existingJson = [];

                if (is_file($jsonFilePath)) {
                    $decoded = json_decode((string) file_get_contents($jsonFilePath), true);
                    if (is_array($decoded)) {
                        $existingJson = $decoded;
                    }
                }

                foreach ($jsonKeys as $jsonKey) {
                    if (!array_key_exists($jsonKey, $existingJson)) {
                        $existingJson[$jsonKey] = str_replace('{$locale}', $locale, $template);
                        $created++;
                        $localeUpdated = true;
                    }
                }

                if ($localeUpdated) {
                    ksort($existingJson);
                    if (!is_dir(dirname($jsonFilePath))) {
                        mkdir(dirname($jsonFilePath), 0777, true);
                    }
                    file_put_contents(
                        $jsonFilePath,
                        json_encode($existingJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
                    );
                }
            }

            if ($localeUpdated) {
                $updatedLocales[] = $locale;
            }
        }

        sort($updatedLocales);

        return [
            'created' => $created,
            'updatedLocales' => $updatedLocales,
        ];
    }

    private function isJsonLikeKey(string $key): bool
    {
        return str_contains($key, ' ');
    }

    /**
     * @param array<string, mixed> $array
     * @param array<int, string> $path
     */
    private function setNestedIfMissing(array &$array, array $path, string $value): bool
    {
        $cursor = &$array;

        foreach ($path as $index => $segment) {
            $isLast = $index === array_key_last($path);

            if ($isLast) {
                if (array_key_exists($segment, $cursor)) {
                    return false;
                }

                $cursor[$segment] = $value;
                return true;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePhpLangFile(string $filePath, array $data): void
    {
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }

        $code = "<?php\n\nreturn " . $this->exportArray($data) . ";\n";
        file_put_contents($filePath, $code);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function exportArray(array $value, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $innerSpaces = str_repeat('    ', $indent + 1);

        if ($value === []) {
            return '[]';
        }

        ksort($value);

        $lines = [
            '[',
        ];

        foreach ($value as $key => $item) {
            $exportedKey = var_export((string) $key, true);
            if (is_array($item)) {
                $exportedValue = $this->exportArray($item, $indent + 1);
            } else {
                $exportedValue = var_export((string) $item, true);
            }

            $lines[] = $innerSpaces . $exportedKey . ' => ' . $exportedValue . ',';
        }

        $lines[] = $spaces . ']';

        return implode(PHP_EOL, $lines);
    }
}
