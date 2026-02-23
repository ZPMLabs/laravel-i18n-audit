<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZPMLabs\LaravelI18nAudit\Loaders\TranslationRepositoryLoader;
use ZPMLabs\LaravelI18nAudit\Scanners\BladeScanner;
use ZPMLabs\LaravelI18nAudit\Scanners\PhpFunctionCallScanner;
use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;
use ZPMLabs\LaravelI18nAudit\Support\Report;
use ZPMLabs\LaravelI18nAudit\Support\ReportHtmlRenderer;
use ZPMLabs\LaravelI18nAudit\Support\MissingTranslationPopulator;
use ZPMLabs\LaravelI18nAudit\Support\ReportRenderer;
use ZPMLabs\LaravelI18nAudit\Support\ScanResult;

final class TranslationReportCommand extends Command
{
    protected $signature = 'i18n:audit
        {--locales= : Comma-separated locales (for example: en,sr,de)}
        {--paths= : Comma-separated scan paths}
        {--exclude= : Comma-separated excluded path fragments}
        {--format=table : Output format: table or json}
        {--output= : Optional output file path for JSON report}
        {--only-missing : Show only missing keys}
        {--only-unused : Show only unused keys}
        {--html : Generate HTML report for latest audit}
        {--html-output= : HTML report output path}
        {--fill-missing : Auto-populate missing translations}
        {--fill-template= : Default value template for auto-populated missing keys}
        {--fail-on-missing : Exit with status 1 when missing keys exist}
        {--fail-on-unused : Exit with status 1 when unused keys exist}';

    protected $description = 'Audit translation key usage against lang repository files.';

    public function __construct(
        private readonly PhpFunctionCallScanner $phpScanner = new PhpFunctionCallScanner(),
        private readonly BladeScanner $bladeScanner = new BladeScanner(),
        private readonly TranslationRepositoryLoader $repositoryLoader = new TranslationRepositoryLoader(),
        private readonly ReportRenderer $renderer = new ReportRenderer(),
        private readonly ReportHtmlRenderer $htmlRenderer = new ReportHtmlRenderer(),
        private readonly MissingTranslationPopulator $missingPopulator = new MissingTranslationPopulator(),
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $paths = $this->resolveScanPaths();
        $excludePaths = $this->resolveExcludePaths();
        $followSymlinks = (bool) config('i18n-audit.follow_symlinks', false);

        $scanResult = new ScanResult();
        $scanResult->merge($this->phpScanner->scan($paths, $excludePaths, $followSymlinks));
        $scanResult->merge($this->bladeScanner->scan($paths, $excludePaths, $followSymlinks));

        $usedKeys = $scanResult->getUsedKeys();

        $langPathConfig = (string) config('i18n-audit.lang_path', 'lang');
        $langPath = PathNormalizer::isAbsolute($langPathConfig)
            ? $langPathConfig
            : base_path($langPathConfig);

        $locales = $this->optionCsv('locales');

        $loaded = $this->repositoryLoader->load($locales, $langPath);
        $localesScanned = $loaded['locales'];
        $repositories = $loaded['repositories'];
        $usedKeyLocations = $scanResult->getUsedKeyLocations();

        $diff = $this->computeDiffData($usedKeys, $usedKeyLocations, $localesScanned, $repositories);

        if ((bool) $this->option('fill-missing')) {
            $fillTemplate = (string) ($this->option('fill-template') ?: config('i18n-audit.fill_template', 'Miissing Translation for {$locale}'));
            $fillResult = $this->missingPopulator->populate($diff['missingByLocale'], $langPath, $fillTemplate);

            if ($fillResult['created'] > 0) {
                $this->info(sprintf(
                    'Auto-populated %d missing translations for locales: %s',
                    $fillResult['created'],
                    implode(', ', $fillResult['updatedLocales'])
                ));
            } else {
                $this->line('No missing translations were auto-populated.');
            }

            $loaded = $this->repositoryLoader->load($localesScanned, $langPath);
            $repositories = $loaded['repositories'];
            $diff = $this->computeDiffData($usedKeys, $usedKeyLocations, $localesScanned, $repositories);
        }

        $detailedLogPath = $this->writeDetailedLog([
            'timestamp' => date(DATE_ATOM),
            'usedKeys' => $usedKeys,
            'usedKeyLocations' => $usedKeyLocations,
            'dynamicKeys' => $scanResult->getDynamicKeys(),
            'missingByLocale' => $diff['missingByLocale'],
            'missingKeyLocationsByLocale' => $diff['missingKeyLocationsByLocale'],
            'unusedByLocale' => $diff['unusedByLocale'],
            'paths' => array_map(static fn (string $path): string => PathNormalizer::relativeTo(base_path(), $path), $paths),
            'exclude' => $excludePaths,
            'locales' => $localesScanned,
            'langPath' => PathNormalizer::relativeTo(base_path(), $langPath),
            'warnings' => $loaded['warnings'],
        ]);

        $dashboardPath = '/' . ltrim((string) config('i18n-audit.dev_route_path', 'i18n-audit/latest'), '/');
        $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $dashboardUrl = $appUrl . $dashboardPath;

        $report = new Report(
            usedKeys: $usedKeys,
            usedKeyLocations: $usedKeyLocations,
            dynamicKeys: $scanResult->getDynamicKeys(),
            missingByLocale: $diff['missingByLocale'],
            missingKeyLocationsByLocale: $diff['missingKeyLocationsByLocale'],
            unusedByLocale: $diff['unusedByLocale'],
            stats: [
                'usedKeysTotal' => count($usedKeys),
                'dynamicKeysTotal' => count($scanResult->getDynamicKeys()),
                'missingTotal' => array_sum(array_map('count', $diff['missingByLocale'])),
                'unusedTotal' => array_sum(array_map('count', $diff['unusedByLocale'])),
                'perLocale' => $diff['perLocaleStats'],
            ],
            meta: [
                'timestamp' => date(DATE_ATOM),
                'paths' => array_map(static fn (string $path): string => PathNormalizer::relativeTo(base_path(), $path), $paths),
                'exclude' => $excludePaths,
                'locales' => $localesScanned,
                'langPath' => PathNormalizer::relativeTo(base_path(), $langPath),
                'warnings' => $loaded['warnings'],
                'detailedLogPath' => PathNormalizer::relativeTo(base_path(), $detailedLogPath),
                'dashboardUrl' => $dashboardUrl,
            ],
        );

        if ((bool) $this->option('html')) {
            $this->writeHtmlReport($report);
        }

        $format = strtolower((string) $this->option('format') ?: (string) config('i18n-audit.format', 'table'));
        $onlyMissing = (bool) $this->option('only-missing');
        $onlyUnused = (bool) $this->option('only-unused');

        if ($format === 'json') {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        } else {
            $this->renderer->renderTable($this, $report, $onlyMissing, $onlyUnused);
        }

        $outputPath = $this->option('output');

        if (is_string($outputPath) && trim($outputPath) !== '') {
            $target = PathNormalizer::isAbsolute($outputPath) ? $outputPath : base_path($outputPath);
            $directory = dirname($target);

            if (!is_dir($directory)) {
                File::makeDirectory($directory, 0777, true);
            }

            File::put($target, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('JSON report written to: ' . PathNormalizer::relativeTo(base_path(), $target));
        }

        $hasMissing = array_sum(array_map('count', $diff['missingByLocale'])) > 0;
        $hasUnused = array_sum(array_map('count', $diff['unusedByLocale'])) > 0;

        if ((bool) $this->option('fail-on-missing') && $hasMissing) {
            return self::FAILURE;
        }

        if ((bool) $this->option('fail-on-unused') && $hasUnused) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function resolveScanPaths(): array
    {
        $configuredPaths = $this->optionCsv('paths');

        if ($configuredPaths === []) {
            /** @var array<int, string> $fromConfig */
            $fromConfig = config('i18n-audit.scan_paths', ['app', 'resources/views', 'routes', 'config', 'database']);
            $configuredPaths = $fromConfig;
        }

        if ((bool) config('i18n-audit.include_tests', false)) {
            $configuredPaths[] = 'tests';
        }

        if ((bool) config('i18n-audit.include_vendor', false)) {
            $configuredPaths[] = 'vendor';
        }

        $resolved = [];

        foreach ($configuredPaths as $path) {
            $resolved[] = PathNormalizer::isAbsolute($path) ? $path : base_path($path);
        }

        return array_values(array_unique($resolved));
    }

    /** @return array<int, string> */
    private function resolveExcludePaths(): array
    {
        $configuredExcludes = $this->optionCsv('exclude');

        if ($configuredExcludes === []) {
            /** @var array<int, string> $fromConfig */
            $fromConfig = config('i18n-audit.exclude_paths', ['vendor', 'node_modules', 'storage', 'bootstrap/cache']);
            $configuredExcludes = $fromConfig;
        }

        if ((bool) config('i18n-audit.include_vendor', false)) {
            $configuredExcludes = array_values(array_filter(
                $configuredExcludes,
                static fn (string $entry): bool => strtolower(trim($entry)) !== 'vendor'
            ));
        }

        return array_values(array_unique(array_map(
            static fn (string $path): string => PathNormalizer::normalize($path),
            $configuredExcludes
        )));
    }

    /** @return array<int, string> */
    private function optionCsv(string $name): array
    {
        $value = $this->option($name);

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeDetailedLog(array $payload): string
    {
        if (!(bool) config('i18n-audit.log_detailed_report', true)) {
            return '';
        }

        $configuredPath = (string) config('i18n-audit.log_path', 'storage/logs/i18n-audit.log');
        $target = PathNormalizer::isAbsolute($configuredPath) ? $configuredPath : base_path($configuredPath);
        $directory = dirname($target);

        if (!is_dir($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
        $entry = sprintf(
            "[%s] i18n:audit report\n-----BEGIN I18N AUDIT JSON-----\n%s\n-----END I18N AUDIT JSON-----\n\n",
            date(DATE_ATOM),
            $encoded
        );
        File::append($target, $entry);

        return $target;
    }

    private function writeHtmlReport(Report $report): void
    {
        $configured = (string) ($this->option('html-output') ?: config('i18n-audit.html_output_path', 'storage/app/i18n-audit-latest.html'));
        $target = PathNormalizer::isAbsolute($configured) ? $configured : base_path($configured);
        $directory = dirname($target);

        if (!is_dir($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        File::put($target, $this->htmlRenderer->render($report));
        $this->info('HTML report written to: ' . PathNormalizer::relativeTo(base_path(), $target));
    }

    /**
     * @param array<int, string> $usedKeys
     * @param array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>> $usedKeyLocations
     * @param array<int, string> $localesScanned
     * @param array<string, array{phpKeys:array<int, string>,jsonKeys:array<int, string>,allKeys:array<int, string>}> $repositories
     * @return array{
     *   missingByLocale:array<string, array<int, string>>,
     *   missingKeyLocationsByLocale:array<string, array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>>,
     *   unusedByLocale:array<string, array<int, string>>,
     *   perLocaleStats:array<string, array{totalTranslations:int,used:int,missing:int,unused:int}>
     * }
     */
    private function computeDiffData(array $usedKeys, array $usedKeyLocations, array $localesScanned, array $repositories): array
    {
        $missingByLocale = [];
        $missingKeyLocationsByLocale = [];
        $unusedByLocale = [];
        $perLocaleStats = [];

        foreach ($localesScanned as $locale) {
            $allKeys = $repositories[$locale]['allKeys'] ?? [];

            $missing = array_values(array_diff($usedKeys, $allKeys));
            $unused = array_values(array_diff($allKeys, $usedKeys));
            $usedInLocale = array_values(array_intersect($usedKeys, $allKeys));
            sort($missing);
            sort($unused);
            sort($usedInLocale);

            $missingByLocale[$locale] = $missing;
            $missingKeyLocationsByLocale[$locale] = [];

            foreach ($missing as $missingKey) {
                $locations = $usedKeyLocations[$missingKey] ?? [];

                if ($locations === []) {
                    continue;
                }

                $missingKeyLocationsByLocale[$locale][$missingKey] = $locations;
            }

            $unusedByLocale[$locale] = $unused;
            $perLocaleStats[$locale] = [
                'totalTranslations' => count($allKeys),
                'used' => count($usedInLocale),
                'missing' => count($missing),
                'unused' => count($unused),
            ];
        }

        return [
            'missingByLocale' => $missingByLocale,
            'missingKeyLocationsByLocale' => $missingKeyLocationsByLocale,
            'unusedByLocale' => $unusedByLocale,
            'perLocaleStats' => $perLocaleStats,
        ];
    }
}
