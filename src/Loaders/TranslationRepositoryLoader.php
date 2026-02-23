<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Loaders;

use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;

final class TranslationRepositoryLoader
{
    public function __construct(
        private readonly PhpLangFileLoader $phpLoader = new PhpLangFileLoader(),
        private readonly JsonLangFileLoader $jsonLoader = new JsonLangFileLoader(),
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function detectLocales(string $langPath): array
    {
        if (!is_dir($langPath)) {
            return [];
        }

        $locales = [];

        $items = scandir($langPath) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $langPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                $locales[] = $item;
            }

            if (is_file($full) && str_ends_with($item, '.json')) {
                $locales[] = substr($item, 0, -5);
            }
        }

        $locales = array_values(array_unique($locales));
        sort($locales);

        return $locales;
    }

    /**
     * @param array<int, string>|null $locales
        * @param array<int, string> $skipTranslationFiles
        * @return array{locales:array<int, string>,repositories:array<string, array{phpKeys:array<int, string>,jsonKeys:array<int, string>,allKeys:array<int, string>,keySources:array<string, array<int, string>>}>,warnings:array<int, string>}
     */
        public function load(?array $locales, string $langPath, array $skipTranslationFiles = []): array
    {
        $detectedLocales = $locales === null || $locales === []
            ? $this->detectLocales($langPath)
            : array_values(array_filter(array_map('trim', $locales), static fn (string $locale): bool => $locale !== ''));

        sort($detectedLocales);

        $repositories = [];
        $warnings = [];

        foreach ($detectedLocales as $locale) {
            $php = $this->phpLoader->loadLocale($locale, $langPath, $skipTranslationFiles);
            $json = $this->jsonLoader->loadLocale($locale, $langPath, $skipTranslationFiles);

            $all = array_values(array_unique(array_merge($php['keys'], $json['keys'])));
            sort($all);

            $keySources = [];

            foreach ([$php['keySources'], $json['keySources']] as $sourceMap) {
                foreach ($sourceMap as $key => $sources) {
                    if (!isset($keySources[$key])) {
                        $keySources[$key] = [];
                    }

                    foreach ($sources as $source) {
                        if (!in_array($source, $keySources[$key], true)) {
                            $keySources[$key][] = PathNormalizer::normalize($source);
                        }
                    }
                }
            }

            foreach ($keySources as &$sources) {
                sort($sources);
            }
            unset($sources);

            $repositories[$locale] = [
                'phpKeys' => $php['keys'],
                'jsonKeys' => $json['keys'],
                'allKeys' => $all,
                'keySources' => $keySources,
            ];

            foreach (array_merge($php['warnings'], $json['warnings']) as $warning) {
                $warnings[] = PathNormalizer::normalize($warning);
            }
        }

        return [
            'locales' => $detectedLocales,
            'repositories' => $repositories,
            'warnings' => $warnings,
        ];
    }
}
