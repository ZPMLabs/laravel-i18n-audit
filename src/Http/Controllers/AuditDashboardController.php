<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use ZPMLabs\LaravelI18nAudit\Support\DetailedAuditLogReader;
use ZPMLabs\LaravelI18nAudit\Support\MissingTranslationPopulator;
use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;
use ZPMLabs\LaravelI18nAudit\Support\UnusedTranslationRemover;

final class AuditDashboardController
{
    public function __construct(
        private readonly DetailedAuditLogReader $logReader = new DetailedAuditLogReader(),
        private readonly MissingTranslationPopulator $missingPopulator = new MissingTranslationPopulator(),
        private readonly UnusedTranslationRemover $unusedRemover = new UnusedTranslationRemover(),
    ) {
    }

    public function show(): View
    {
        $latest = $this->readLatestPayload();

        $meta = is_array($latest['meta'] ?? null) ? $latest['meta'] : [];
        $stats = is_array($latest['stats'] ?? null) ? $latest['stats'] : [];
        $perLocale = is_array($stats['perLocale'] ?? null) ? $stats['perLocale'] : [];
        $missingByLocale = is_array($latest['missingByLocale'] ?? null) ? $latest['missingByLocale'] : [];
        $unusedByLocale = is_array($latest['unusedByLocale'] ?? null) ? $latest['unusedByLocale'] : [];
        $usedKeyLocations = is_array($latest['usedKeyLocations'] ?? null) ? $latest['usedKeyLocations'] : [];

        $locales = [];

        if (is_array($meta['locales'] ?? null)) {
            foreach ($meta['locales'] as $locale) {
                if (is_string($locale) && $locale !== '') {
                    $locales[] = $locale;
                }
            }
        }

        if ($locales === []) {
            $locales = array_values(array_unique(array_merge(
                array_keys($perLocale),
                array_keys($missingByLocale),
                array_keys($unusedByLocale)
            )));
            sort($locales);
        }

        $usedKeysCount = is_array($latest['usedKeys'] ?? null) ? count($latest['usedKeys']) : 0;
        $perLocaleRows = $this->buildPerLocaleRows($locales, $perLocale, $missingByLocale, $unusedByLocale, $usedKeysCount);

        $missingLocationsByLocale = is_array($latest['missingKeyLocationsByLocale'] ?? null)
            ? $latest['missingKeyLocationsByLocale']
            : [];

        if ($missingLocationsByLocale === []) {
            $missingLocationsByLocale = $this->buildMissingLocationsFromUsed($missingByLocale, $usedKeyLocations);
        }

        return view('i18n-audit::dashboard', [
            'payload' => $latest,
            'meta' => $meta,
            'stats' => $stats,
            'perLocale' => $perLocale,
            'perLocaleRows' => $perLocaleRows,
            'locales' => $locales,
            'missingByLocale' => $missingByLocale,
            'unusedByLocale' => $unusedByLocale,
            'missingLocationsByLocale' => $missingLocationsByLocale,
            'usedKeyLocations' => $usedKeyLocations,
            'dynamicKeys' => is_array($latest['dynamicKeys'] ?? null) ? $latest['dynamicKeys'] : [],
            'routePath' => trim((string) config('i18n-audit.dev_route_path', 'i18n-audit/latest'), '/'),
            'statusMessage' => session('i18n_audit_status'),
            'rawJson' => $latest !== null
                ? (json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
                : null,
        ]);
    }

    public function fillMissing(Request $request): RedirectResponse
    {
        $latest = $this->readLatestPayload();

        if ($latest === null) {
            return redirect()->route('i18n-audit.latest')->with('i18n_audit_status', 'No audit log payload found. Run i18n:audit first.');
        }

        $template = (string) config('i18n-audit.fill_template', 'Miissing Translation for {$locale}');
        $langPath = $this->resolveLangPath();

        $missingByLocale = $latest['missingByLocale'] ?? [];
        if (!is_array($missingByLocale)) {
            $missingByLocale = [];
        }

        try {
            $result = $this->missingPopulator->populate($missingByLocale, $langPath, $template);
            $auditExitCode = Artisan::call('i18n:audit');
        } catch (\Throwable $throwable) {
            return redirect()->route('i18n-audit.latest')->with(
                'i18n_audit_status',
                'Auto populate failed: ' . $throwable->getMessage()
            );
        }

        return redirect()->route('i18n-audit.latest')->with(
            'i18n_audit_status',
            sprintf(
                'Created %d missing translations. Updated locales: %s. Audit refresh %s.',
                $result['created'],
                implode(', ', $result['updatedLocales']),
                $auditExitCode === 0 ? 'completed' : 'failed'
            )
        );
    }

    public function removeUnused(Request $request): RedirectResponse
    {
        $latest = $this->readLatestPayload();

        if ($latest === null) {
            return redirect()->route('i18n-audit.latest')->with('i18n_audit_status', 'No audit log payload found. Run i18n:audit first.');
        }

        $langPath = $this->resolveLangPath();

        $unusedByLocale = $latest['unusedByLocale'] ?? [];
        if (!is_array($unusedByLocale)) {
            $unusedByLocale = [];
        }

        try {
            $result = $this->unusedRemover->remove($unusedByLocale, $langPath);
            $auditExitCode = Artisan::call('i18n:audit');
        } catch (\Throwable $throwable) {
            return redirect()->route('i18n-audit.latest')->with(
                'i18n_audit_status',
                'Remove unused failed: ' . $throwable->getMessage()
            );
        }

        return redirect()->route('i18n-audit.latest')->with(
            'i18n_audit_status',
            sprintf(
                'Removed %d unused translations. Updated locales: %s. Audit refresh %s.',
                $result['removed'],
                implode(', ', $result['updatedLocales']),
                $auditExitCode === 0 ? 'completed' : 'failed'
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readLatestPayload(): ?array
    {
        $logPathConfig = (string) config('i18n-audit.log_path', 'storage/logs/i18n-audit.log');
        $logPath = PathNormalizer::isAbsolute($logPathConfig) ? $logPathConfig : base_path($logPathConfig);

        return $this->logReader->readLatest($logPath);
    }

    private function resolveLangPath(): string
    {
        $langPathConfig = (string) config('i18n-audit.lang_path', 'lang');

        return PathNormalizer::isAbsolute($langPathConfig) ? $langPathConfig : base_path($langPathConfig);
    }

    /**
     * @param array<int, string> $locales
     * @param array<string, mixed> $perLocale
     * @param array<string, mixed> $missingByLocale
     * @param array<string, mixed> $unusedByLocale
     * @return array<string, array{used:int,missing:int,unused:int,totalTranslations:int}>
     */
    private function buildPerLocaleRows(array $locales, array $perLocale, array $missingByLocale, array $unusedByLocale, int $usedKeysCount): array
    {
        $rows = [];

        foreach ($locales as $locale) {
            $precomputed = $perLocale[$locale] ?? null;

            if (is_array($precomputed)) {
                $rows[$locale] = [
                    'used' => (int) ($precomputed['used'] ?? 0),
                    'missing' => (int) ($precomputed['missing'] ?? 0),
                    'unused' => (int) ($precomputed['unused'] ?? 0),
                    'totalTranslations' => (int) ($precomputed['totalTranslations'] ?? 0),
                ];
                continue;
            }

            $missing = is_array($missingByLocale[$locale] ?? null) ? count($missingByLocale[$locale]) : 0;
            $unused = is_array($unusedByLocale[$locale] ?? null) ? count($unusedByLocale[$locale]) : 0;
            $used = max(0, $usedKeysCount - $missing);
            $total = $used + $unused;

            $rows[$locale] = [
                'used' => $used,
                'missing' => $missing,
                'unused' => $unused,
                'totalTranslations' => $total,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $missingByLocale
     * @param array<string, mixed> $usedKeyLocations
     * @return array<string, array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>>
     */
    private function buildMissingLocationsFromUsed(array $missingByLocale, array $usedKeyLocations): array
    {
        $output = [];

        foreach ($missingByLocale as $locale => $missingKeys) {
            if (!is_string($locale)) {
                continue;
            }

            if (!is_array($missingKeys)) {
                $output[$locale] = [];
                continue;
            }

            $output[$locale] = [];

            foreach ($missingKeys as $missingKey) {
                if (!is_string($missingKey)) {
                    continue;
                }

                $locations = $usedKeyLocations[$missingKey] ?? null;

                if (!is_array($locations)) {
                    continue;
                }

                $normalized = [];

                foreach ($locations as $location) {
                    if (!is_array($location)) {
                        continue;
                    }

                    $normalized[] = [
                        'file' => (string) ($location['file'] ?? ''),
                        'line' => (int) ($location['line'] ?? 1),
                        'column' => (int) ($location['column'] ?? 1),
                        'char' => (int) ($location['char'] ?? 1),
                        'source' => (string) ($location['source'] ?? ''),
                    ];
                }

                if ($normalized !== []) {
                    $output[$locale][$missingKey] = $normalized;
                }
            }
        }

        return $output;
    }
}
