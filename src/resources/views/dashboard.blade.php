<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>I18n Audit Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<div class="max-w-7xl mx-auto p-6 space-y-6">
    <header class="space-y-2">
        <h1 class="text-3xl font-bold">I18n Audit Dashboard</h1>
        <p class="text-sm text-slate-600">Full inspection page for the latest audit payload loaded from detailed JSON log.</p>
        <p class="text-sm text-slate-600">Run <span class="font-mono bg-slate-100 px-1 rounded">php artisan i18n:audit</span> to refresh data.</p>
    </header>

    @if (is_string($statusMessage) && $statusMessage !== '')
        <div class="rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sky-900">
            {{ $statusMessage }}
        </div>
    @endif

    @if (!is_array($payload))
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
            No audit data found yet. Run <span class="font-mono">php artisan i18n:audit</span> first.
        </div>
    @else
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-lg bg-white border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Timestamp</p>
                <p class="font-mono text-sm mt-1">{{ (string) ($meta['timestamp'] ?? 'unknown') }}</p>
            </div>
            <div class="rounded-lg bg-white border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Dashboard URL</p>
                <p class="font-mono text-sm mt-1 break-all">{{ (string) ($meta['dashboardUrl'] ?? request()->fullUrl()) }}</p>
            </div>
            <div class="rounded-lg bg-white border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Detailed Log</p>
                <p class="font-mono text-sm mt-1 break-all">{{ (string) ($meta['detailedLogPath'] ?? 'n/a') }}</p>
            </div>
        </section>

        <section class="rounded-lg bg-white border border-slate-200 p-4">
            <h2 class="text-lg font-semibold mb-3">Actions</h2>
            <div class="flex flex-wrap gap-3">
                <form method="post" action="/{{ $routePath }}/fill-missing">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Auto Populate Missing</button>
                </form>
                <form method="post" action="/{{ $routePath }}/remove-unused" onsubmit="return confirm('Remove currently marked unused translation entries?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded bg-rose-600 text-white hover:bg-rose-700">Remove Unused</button>
                </form>
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
                    <tbody>
                    @foreach ($perLocaleRows as $locale => $row)
                        @php
                            $row = is_array($row) ? $row : [];
                        @endphp
                        <tr class="border-t border-slate-200">
                            <td class="px-3 py-2 font-medium">{{ (string) $locale }}</td>
                            <td class="px-3 py-2">{{ (int) ($row['used'] ?? 0) }}</td>
                            <td class="px-3 py-2">{{ (int) ($row['missing'] ?? 0) }}</td>
                            <td class="px-3 py-2">{{ (int) ($row['unused'] ?? 0) }}</td>
                            <td class="px-3 py-2">{{ (int) ($row['totalTranslations'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    @if ($perLocaleRows === [])
                        <tr class="border-t border-slate-200">
                            <td colspan="5" class="px-3 py-3 text-sm text-slate-500">No locale summary rows found in latest log payload.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </section>

        @php
            $localesList = is_array($locales) ? array_values($locales) : [];
            $activeLocale = $localesList[0] ?? null;
        @endphp

        <section class="rounded-lg bg-white border border-slate-200 p-4" x-data="{ active: '{{ (string) $activeLocale }}' }">
            <h2 class="text-lg font-semibold mb-3">Per-locale Details</h2>
            <p class="text-sm text-slate-600 mb-4">Each tab shows missing keys, missing locations, unused keys, and dynamic warnings for that locale.</p>

            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($localesList as $locale)
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded border text-sm"
                        :class="active === '{{ $locale }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300'"
                        @click="active = '{{ $locale }}'"
                    >
                        {{ $locale }}
                    </button>
                @endforeach
            </div>

            @foreach ($localesList as $locale)
                @php
                    $missingKeys = is_array($missingByLocale[$locale] ?? null) ? $missingByLocale[$locale] : [];
                    $unusedKeys = is_array($unusedByLocale[$locale] ?? null) ? $unusedByLocale[$locale] : [];
                    $missingLocations = is_array($missingLocationsByLocale[$locale] ?? null) ? $missingLocationsByLocale[$locale] : [];
                @endphp
                <div x-show="active === '{{ $locale }}'" class="space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="rounded border border-slate-200 p-3">
                            <h3 class="font-semibold mb-2">Missing Keys ({{ count($missingKeys) }})</h3>
                            <div class="max-h-72 overflow-auto">
                                @if ($missingKeys === [])
                                    <p class="text-sm text-slate-500">No missing keys.</p>
                                @else
                                    <ul class="list-disc pl-5 text-sm space-y-1">
                                        @foreach ($missingKeys as $key)
                                            <li>{{ (string) $key }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                        <div class="rounded border border-slate-200 p-3">
                            <h3 class="font-semibold mb-2">Unused Keys ({{ count($unusedKeys) }})</h3>
                            <div class="max-h-72 overflow-auto">
                                @if ($unusedKeys === [])
                                    <p class="text-sm text-slate-500">No unused keys.</p>
                                @else
                                    <ul class="list-disc pl-5 text-sm space-y-1">
                                        @foreach ($unusedKeys as $key)
                                            <li>{{ (string) $key }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="rounded border border-slate-200 p-3">
                        <h3 class="font-semibold mb-2">Missing Key Locations</h3>
                        @if ($missingLocations === [])
                            <p class="text-sm text-slate-500">No missing key locations.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($missingLocations as $key => $locations)
                                    @php
                                        $locations = is_array($locations) ? $locations : [];
                                    @endphp
                                    <div class="rounded border border-slate-200 bg-slate-50 p-2">
                                        <p class="font-mono text-sm font-semibold">{{ (string) $key }}</p>
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($locations as $location)
                                                @php
                                                    $location = is_array($location) ? $location : [];
                                                    $pointer = sprintf(
                                                        '%s:%d:%d',
                                                        (string) ($location['file'] ?? ''),
                                                        (int) ($location['line'] ?? 1),
                                                        (int) ($location['column'] ?? 1)
                                                    );
                                                @endphp
                                                <li class="flex items-center gap-2 text-sm">
                                                    <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ $pointer }}</code>
                                                    <button type="button" class="px-2 py-0.5 rounded border border-slate-300 text-xs hover:bg-slate-100" data-copy="{{ $pointer }}">Copy</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        <section class="rounded-lg bg-white border border-slate-200 p-4">
            <h2 class="text-lg font-semibold mb-2">Dynamic Key Warnings</h2>
            <p class="text-sm text-slate-600 mb-3">These calls use non-literal expressions and cannot be resolved to concrete translation keys at scan time.</p>
            @if (!is_array($dynamicKeys) || $dynamicKeys === [])
                <p class="text-sm text-slate-500">No dynamic warnings.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="bg-slate-100 text-slate-700">
                            <th class="text-left px-3 py-2">File</th>
                            <th class="text-left px-3 py-2">Line</th>
                            <th class="text-left px-3 py-2">Source</th>
                            <th class="text-left px-3 py-2">Expression</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($dynamicKeys as $warning)
                            @php
                                $warning = is_array($warning) ? $warning : [];
                            @endphp
                            <tr class="border-t border-slate-200">
                                <td class="px-3 py-2 font-mono">{{ (string) ($warning['file'] ?? '') }}</td>
                                <td class="px-3 py-2">{{ (int) ($warning['line'] ?? 0) }}</td>
                                <td class="px-3 py-2">{{ (string) ($warning['source'] ?? '') }}</td>
                                <td class="px-3 py-2 font-mono">{{ (string) ($warning['expression'] ?? '') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-lg bg-white border border-slate-200 p-4">
            <h2 class="text-lg font-semibold mb-2">Used Key Locations</h2>
            <p class="text-sm text-slate-600 mb-3">All detected literal translation usages with exact file, line, column, and char positions for easy copy-checking.</p>
            @if (!is_array($usedKeyLocations) || $usedKeyLocations === [])
                <p class="text-sm text-slate-500">No used key location entries found.</p>
            @else
                <div class="max-h-[40vh] overflow-auto space-y-3 pr-1">
                    @foreach ($usedKeyLocations as $key => $locations)
                        @php
                            $locations = is_array($locations) ? $locations : [];
                        @endphp
                        <div class="rounded border border-slate-200 bg-slate-50 p-2">
                            <p class="font-mono text-sm font-semibold">{{ (string) $key }}</p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($locations as $location)
                                    @php
                                        $location = is_array($location) ? $location : [];
                                        $pointer = sprintf(
                                            '%s:%d:%d',
                                            (string) ($location['file'] ?? ''),
                                            (int) ($location['line'] ?? 1),
                                            (int) ($location['column'] ?? 1)
                                        );
                                    @endphp
                                    <li class="flex flex-wrap items-center gap-2 text-sm">
                                        <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ $pointer }}</code>
                                        <span class="text-slate-500">char {{ (int) ($location['char'] ?? 1) }}</span>
                                        <span class="text-slate-500">source {{ (string) ($location['source'] ?? '') }}</span>
                                        <button type="button" class="px-2 py-0.5 rounded border border-slate-300 text-xs hover:bg-slate-100" data-copy="{{ $pointer }}">Copy</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-lg bg-white border border-slate-200 p-4">
            <h2 class="text-lg font-semibold mb-2">Raw JSON Payload (Full)</h2>
            <p class="text-sm text-slate-600 mb-3">Complete latest audit payload exactly as read from log for full traceability.</p>
            <pre class="bg-slate-900 text-slate-100 p-4 rounded overflow-auto max-h-[55vh] text-xs leading-relaxed">{{ (string) $rawJson }}</pre>
        </section>
    @endif
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    document.querySelectorAll('button[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.getAttribute('data-copy') || '';
            navigator.clipboard.writeText(value);
            const original = button.textContent;
            button.textContent = 'Copied';
            setTimeout(function () {
                button.textContent = original || 'Copy';
            }, 1200);
        });
    });
</script>
</body>
</html>
