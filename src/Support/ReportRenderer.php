<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

use Illuminate\Console\Command;

final class ReportRenderer
{
    public function renderTable(Command $command, Report $report, bool $onlyMissing, bool $onlyUnused): void
    {
        $stats = $report->stats();
        $meta = $report->meta();

        $command->info('I18n Audit Summary');
        $command->line('Used keys: ' . ($stats['usedKeysTotal'] ?? 0));
        $command->line('Dynamic warnings: ' . ($stats['dynamicKeysTotal'] ?? 0));
        $command->line('Locales scanned: ' . implode(', ', $meta['locales'] ?? []));
        $command->newLine();

        $headers = ['Locale'];
        $headers[] = 'Used';
        if (!$onlyUnused) {
            $headers[] = 'Missing';
        }
        if (!$onlyMissing) {
            $headers[] = 'Unused';
        }
        $headers[] = 'Total translations';

        $rows = [];
        /** @var array<string, array{totalTranslations:int,missing:int,unused:int}> $perLocale */
        $perLocale = $stats['perLocale'] ?? [];

        foreach ($meta['locales'] ?? [] as $locale) {
            $row = [$locale];
            $row[] = (string) ($perLocale[$locale]['used'] ?? 0);

            if (!$onlyUnused) {
                $row[] = (string) ($perLocale[$locale]['missing'] ?? count($report->missingByLocale()[$locale] ?? []));
            }

            if (!$onlyMissing) {
                $row[] = (string) ($perLocale[$locale]['unused'] ?? count($report->unusedByLocale()[$locale] ?? []));
            }

            $row[] = (string) ($perLocale[$locale]['totalTranslations'] ?? 0);
            $rows[] = $row;
        }

        if ($rows !== []) {
            $command->table($headers, $rows);
        }

        if (is_string($meta['detailedLogPath'] ?? null) && $meta['detailedLogPath'] !== '') {
            $command->line('Detailed report log: ' . $meta['detailedLogPath']);

            if (is_string($meta['dashboardUrl'] ?? null) && $meta['dashboardUrl'] !== '') {
                $command->line('Visit URL: ' . $meta['dashboardUrl']);
            }
        }

        if (($meta['warnings'] ?? []) !== []) {
            $command->newLine();
            $command->warn('Loader warnings:');
            foreach ($meta['warnings'] as $warning) {
                $command->line('- ' . $warning);
            }
        }
    }
}
