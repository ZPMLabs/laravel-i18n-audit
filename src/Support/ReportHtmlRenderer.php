<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class ReportHtmlRenderer
{
    public function render(Report $report): string
    {
        $data = $report->toArray();

        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        /** @var array<string, mixed> $stats */
        $stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];

        /** @var array<string, array{used:int,missing:int,unused:int,totalTranslations:int}> $perLocale */
        $perLocale = is_array($stats['perLocale'] ?? null) ? $stats['perLocale'] : [];

        /** @var array<string, array<int, string>> $missingByLocale */
        $missingByLocale = is_array($data['missingByLocale'] ?? null) ? $data['missingByLocale'] : [];

        /** @var array<string, array<int, string>> $unusedByLocale */
        $unusedByLocale = is_array($data['unusedByLocale'] ?? null) ? $data['unusedByLocale'] : [];

        /** @var array<string, array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>> $missingLocationsByLocale */
        $missingLocationsByLocale = is_array($data['missingKeyLocationsByLocale'] ?? null)
            ? $data['missingKeyLocationsByLocale']
            : [];

        /** @var array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>> $usedKeyLocations */
        $usedKeyLocations = is_array($data['usedKeyLocations'] ?? null) ? $data['usedKeyLocations'] : [];

        /** @var array<int, array{file:string,line:int,expression:string,source:string}> $dynamicKeys */
        $dynamicKeys = is_array($data['dynamicKeys'] ?? null) ? $data['dynamicKeys'] : [];

        $locales = [];

        if (is_array($meta['locales'] ?? null)) {
            foreach ($meta['locales'] as $locale) {
                if (is_string($locale) && $locale !== '') {
                    $locales[] = $locale;
                }
            }
        }

        if ($locales === []) {
            $locales = array_values(array_unique(array_merge(array_keys($perLocale), array_keys($missingByLocale), array_keys($unusedByLocale))));
            sort($locales);
        }

        $summaryRows = '';

        foreach ($locales as $locale) {
            $row = $perLocale[$locale] ?? [
                'used' => 0,
                'missing' => is_array($missingByLocale[$locale] ?? null) ? count($missingByLocale[$locale]) : 0,
                'unused' => is_array($unusedByLocale[$locale] ?? null) ? count($unusedByLocale[$locale]) : 0,
                'totalTranslations' => 0,
            ];

            $summaryRows .= sprintf(
                '<tr class="border-t border-slate-200"><td class="px-3 py-2 font-medium">%s</td><td class="px-3 py-2">%d</td><td class="px-3 py-2">%d</td><td class="px-3 py-2">%d</td><td class="px-3 py-2">%d</td></tr>',
                $this->escape($locale),
                (int) ($row['used'] ?? 0),
                (int) ($row['missing'] ?? 0),
                (int) ($row['unused'] ?? 0),
                (int) ($row['totalTranslations'] ?? 0)
            );
        }

        if ($summaryRows === '') {
            $summaryRows = '<tr class="border-t border-slate-200"><td colspan="5" class="px-3 py-3 text-sm text-slate-500">No locale summary rows found.</td></tr>';
        }

        $tabs = '';
        $localePanels = '';
        $activeLocale = $locales[0] ?? '';

        foreach ($locales as $locale) {
            $tabs .= sprintf(
                '<button type="button" class="px-3 py-1.5 rounded border text-sm" :class="active === %s ? \"bg-indigo-600 text-white border-indigo-600\" : \"bg-white text-slate-700 border-slate-300\"" @click="active = %s">%s</button>',
                $this->quoteForJs($locale),
                $this->quoteForJs($locale),
                $this->escape($locale)
            );

            $missingList = '';
            foreach (($missingByLocale[$locale] ?? []) as $key) {
                $missingList .= '<li>' . $this->escape((string) $key) . '</li>';
            }
            if ($missingList === '') {
                $missingList = '<p class="text-sm text-slate-500">No missing keys.</p>';
            } else {
                $missingList = '<ul class="list-disc pl-5 text-sm space-y-1">' . $missingList . '</ul>';
            }

            $unusedList = '';
            foreach (($unusedByLocale[$locale] ?? []) as $key) {
                $unusedList .= '<li>' . $this->escape((string) $key) . '</li>';
            }
            if ($unusedList === '') {
                $unusedList = '<p class="text-sm text-slate-500">No unused keys.</p>';
            } else {
                $unusedList = '<ul class="list-disc pl-5 text-sm space-y-1">' . $unusedList . '</ul>';
            }

            $missingLocationsBlock = '';
            foreach (($missingLocationsByLocale[$locale] ?? []) as $key => $locations) {
                $missingLocationsBlock .= '<div class="rounded border border-slate-200 bg-slate-50 p-2">';
                $missingLocationsBlock .= '<p class="font-mono text-sm font-semibold">' . $this->escape((string) $key) . '</p>';
                $missingLocationsBlock .= '<ul class="mt-2 space-y-1">';

                foreach ($locations as $location) {
                    $pointer = sprintf(
                        '%s:%d:%d',
                        (string) ($location['file'] ?? ''),
                        (int) ($location['line'] ?? 1),
                        (int) ($location['column'] ?? 1)
                    );

                    $missingLocationsBlock .= '<li class="flex items-center gap-2 text-sm">';
                    $missingLocationsBlock .= '<code class="bg-white px-1.5 py-0.5 rounded border border-slate-200">' . $this->escape($pointer) . '</code>';
                    $missingLocationsBlock .= '<button type="button" class="px-2 py-0.5 rounded border border-slate-300 text-xs hover:bg-slate-100" data-copy="' . $this->escape($pointer) . '">Copy</button>';
                    $missingLocationsBlock .= '</li>';
                }

                $missingLocationsBlock .= '</ul></div>';
            }

            if ($missingLocationsBlock === '') {
                $missingLocationsBlock = '<p class="text-sm text-slate-500">No missing key locations.</p>';
            }

            $localePanels .= sprintf(
                '<div x-show="active === %s" class="space-y-4"><div class="grid grid-cols-1 lg:grid-cols-2 gap-4"><div class="rounded border border-slate-200 p-3"><h3 class="font-semibold mb-2">Missing Keys (%d)</h3><div class="max-h-72 overflow-auto">%s</div></div><div class="rounded border border-slate-200 p-3"><h3 class="font-semibold mb-2">Unused Keys (%d)</h3><div class="max-h-72 overflow-auto">%s</div></div></div><div class="rounded border border-slate-200 p-3"><h3 class="font-semibold mb-2">Missing Key Locations</h3>%s</div></div>',
                $this->quoteForJs($locale),
                is_array($missingByLocale[$locale] ?? null) ? count($missingByLocale[$locale]) : 0,
                $missingList,
                is_array($unusedByLocale[$locale] ?? null) ? count($unusedByLocale[$locale]) : 0,
                $unusedList,
                $missingLocationsBlock
            );
        }

        $usedLocationsBlock = '';
        foreach ($usedKeyLocations as $key => $locations) {
            $usedLocationsBlock .= '<div class="rounded border border-slate-200 bg-slate-50 p-2">';
            $usedLocationsBlock .= '<p class="font-mono text-sm font-semibold">' . $this->escape((string) $key) . '</p>';
            $usedLocationsBlock .= '<ul class="mt-2 space-y-1">';

            foreach ($locations as $location) {
                $pointer = sprintf(
                    '%s:%d:%d',
                    (string) ($location['file'] ?? ''),
                    (int) ($location['line'] ?? 1),
                    (int) ($location['column'] ?? 1)
                );

                $usedLocationsBlock .= '<li class="flex flex-wrap items-center gap-2 text-sm">';
                $usedLocationsBlock .= '<code class="bg-white px-1.5 py-0.5 rounded border border-slate-200">' . $this->escape($pointer) . '</code>';
                $usedLocationsBlock .= '<span class="text-slate-500">char ' . (int) ($location['char'] ?? 1) . '</span>';
                $usedLocationsBlock .= '<span class="text-slate-500">source ' . $this->escape((string) ($location['source'] ?? '')) . '</span>';
                $usedLocationsBlock .= '<button type="button" class="px-2 py-0.5 rounded border border-slate-300 text-xs hover:bg-slate-100" data-copy="' . $this->escape($pointer) . '">Copy</button>';
                $usedLocationsBlock .= '</li>';
            }

            $usedLocationsBlock .= '</ul></div>';
        }

        if ($usedLocationsBlock === '') {
            $usedLocationsBlock = '<p class="text-sm text-slate-500">No used key location entries found.</p>';
        }

        $dynamicRows = '';
        foreach ($dynamicKeys as $warning) {
            $dynamicRows .= sprintf(
                '<tr class="border-t border-slate-200"><td class="px-3 py-2 font-mono">%s</td><td class="px-3 py-2">%d</td><td class="px-3 py-2">%s</td><td class="px-3 py-2 font-mono">%s</td></tr>',
                $this->escape((string) ($warning['file'] ?? '')),
                (int) ($warning['line'] ?? 0),
                $this->escape((string) ($warning['source'] ?? '')),
                $this->escape((string) ($warning['expression'] ?? ''))
            );
        }

        $dynamicBlock = $dynamicRows === ''
            ? '<p class="text-sm text-slate-500">No dynamic warnings.</p>'
            : '<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-slate-100 text-slate-700"><th class="text-left px-3 py-2">File</th><th class="text-left px-3 py-2">Line</th><th class="text-left px-3 py-2">Source</th><th class="text-left px-3 py-2">Expression</th></tr></thead><tbody>' . $dynamicRows . '</tbody></table></div>';

        $rawJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>I18n Audit Report</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<div class="max-w-7xl mx-auto p-6 space-y-6">
    <header class="space-y-2">
        <h1 class="text-3xl font-bold">I18n Audit Report</h1>
        <p class="text-sm text-slate-600">Static HTML export styled to match the live Blade dashboard.</p>
    </header>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-lg bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Timestamp</p>
            <p class="font-mono text-sm mt-1">' . $this->escape((string) ($meta['timestamp'] ?? 'unknown')) . '</p>
        </div>
        <div class="rounded-lg bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Dashboard URL</p>
            <p class="font-mono text-sm mt-1 break-all">' . $this->escape((string) ($meta['dashboardUrl'] ?? '')) . '</p>
        </div>
        <div class="rounded-lg bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Detailed Log</p>
            <p class="font-mono text-sm mt-1 break-all">' . $this->escape((string) ($meta['detailedLogPath'] ?? 'n/a')) . '</p>
        </div>
    </section>

    <section class="rounded-lg bg-white border border-slate-200 p-4">
        <h2 class="text-lg font-semibold mb-3">Summary</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-100 text-slate-700">
                        <th class="text-left px-3 py-2">Locale</th>
                        <th class="text-left px-3 py-2">Used</th>
                        <th class="text-left px-3 py-2">Missing</th>
                        <th class="text-left px-3 py-2">Unused</th>
                        <th class="text-left px-3 py-2">Total</th>
                    </tr>
                </thead>
                <tbody>' . $summaryRows . '</tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg bg-white border border-slate-200 p-4" x-data="{ active: ' . $this->quoteForJs((string) $activeLocale) . ' }">
        <h2 class="text-lg font-semibold mb-3">Per-locale Details</h2>
        <p class="text-sm text-slate-600 mb-4">Each tab shows missing keys, missing locations, and unused keys for that locale.</p>

        <div class="flex flex-wrap gap-2 mb-4">' . $tabs . '</div>

        ' . $localePanels . '
    </section>

    <section class="rounded-lg bg-white border border-slate-200 p-4">
        <h2 class="text-lg font-semibold mb-2">Dynamic Key Warnings</h2>
        <p class="text-sm text-slate-600 mb-3">These calls use non-literal expressions and cannot be resolved to concrete translation keys at scan time.</p>
        ' . $dynamicBlock . '
    </section>

    <section class="rounded-lg bg-white border border-slate-200 p-4">
        <h2 class="text-lg font-semibold mb-2">Used Key Locations</h2>
        <p class="text-sm text-slate-600 mb-3">All detected literal translation usages with exact file, line, column, and char positions.</p>
        <div class="max-h-[40vh] overflow-auto space-y-3 pr-1">' . $usedLocationsBlock . '</div>
    </section>

    <section class="rounded-lg bg-white border border-slate-200 p-4">
        <h2 class="text-lg font-semibold mb-2">Raw JSON Payload (Full)</h2>
        <p class="text-sm text-slate-600 mb-3">Complete payload used to build this static report.</p>
        <pre class="bg-slate-900 text-slate-100 p-4 rounded overflow-auto max-h-[55vh] text-xs leading-relaxed">' . $this->escape($rawJson) . '</pre>
    </section>
</div>

<script>
document.querySelectorAll("button[data-copy]").forEach(function (button) {
    button.addEventListener("click", function () {
        const value = button.getAttribute("data-copy") || "";
        navigator.clipboard.writeText(value);
        const original = button.textContent;
        button.textContent = "Copied";
        setTimeout(function () {
            button.textContent = original || "Copy";
        }, 1200);
    });
});
</script>
</body>
</html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function quoteForJs(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
    }
}
