<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class UnusedTranslationRemover
{
    /**
     * @param array<string, array<int, string>> $unusedByLocale
     * @return array{removed:int,updatedLocales:array<int, string>}
     */
    public function remove(array $unusedByLocale, string $langPath): array
    {
        $removed = 0;
        $updatedLocales = [];

        foreach ($unusedByLocale as $locale => $unusedKeys) {
            if ($unusedKeys === []) {
                continue;
            }

            $localeChanged = false;
            $phpBuckets = [];
            $jsonKeys = [];

            foreach ($unusedKeys as $key) {
                if ($this->isJsonLikeKey($key) || !str_contains($key, '.')) {
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
                $phpFilePath = $langPath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group . '.php';

                if (!is_file($phpFilePath)) {
                    continue;
                }

                /** @var mixed $loaded */
                $loaded = include $phpFilePath;

                if (!is_array($loaded)) {
                    continue;
                }

                foreach ($paths as $path) {
                    if ($this->removeNested($loaded, $path)) {
                        $removed++;
                        $localeChanged = true;
                    }
                }

                if ($localeChanged) {
                    $this->writePhpLangFile($phpFilePath, $loaded);
                }
            }

            if ($jsonKeys !== []) {
                $jsonFilePath = $langPath . DIRECTORY_SEPARATOR . $locale . '.json';

                if (is_file($jsonFilePath)) {
                    $decoded = json_decode((string) file_get_contents($jsonFilePath), true);

                    if (is_array($decoded)) {
                        foreach ($jsonKeys as $jsonKey) {
                            if (array_key_exists($jsonKey, $decoded)) {
                                unset($decoded[$jsonKey]);
                                $removed++;
                                $localeChanged = true;
                            }
                        }

                        if ($localeChanged) {
                            ksort($decoded);
                            file_put_contents(
                                $jsonFilePath,
                                json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
                            );
                        }
                    }
                }
            }

            if ($localeChanged) {
                $updatedLocales[] = $locale;
            }
        }

        sort($updatedLocales);

        return [
            'removed' => $removed,
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
    private function removeNested(array &$array, array $path): bool
    {
        $current = &$array;

        foreach ($path as $index => $segment) {
            $isLast = $index === array_key_last($path);

            if ($isLast) {
                if (!array_key_exists($segment, $current)) {
                    return false;
                }

                unset($current[$segment]);
                return true;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return false;
            }

            $current = &$current[$segment];
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

        $lines = ['['];

        foreach ($value as $key => $item) {
            $exportedKey = var_export((string) $key, true);
            $exportedValue = is_array($item)
                ? $this->exportArray($item, $indent + 1)
                : var_export((string) $item, true);

            $lines[] = $innerSpaces . $exportedKey . ' => ' . $exportedValue . ',';
        }

        $lines[] = $spaces . ']';

        return implode(PHP_EOL, $lines);
    }
}
