<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class ReportHtmlRenderer
{
    public function render(Report $report): string
    {
        $data = $report->toArray();
        $meta = $data['meta'] ?? [];
        $stats = $data['stats'] ?? [];
        $locales = $meta['locales'] ?? [];

        $summaryRows = '';
        foreach ($locales as $locale) {
            $row = $stats['perLocale'][$locale] ?? ['used' => 0, 'missing' => 0, 'unused' => 0, 'totalTranslations' => 0];
            $summaryRows .= sprintf(
                '<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td></tr>',
                $this->escape($locale),
                (int) $row['used'],
                (int) $row['missing'],
                (int) $row['unused'],
                (int) $row['totalTranslations']
            );
        }

        $missingSections = '';
        foreach (($data['missingKeyLocationsByLocale'] ?? []) as $locale => $byKey) {
            $missingSections .= '<section><h2>Missing in ' . $this->escape($locale) . '</h2>';

            if ($byKey === []) {
                $missingSections .= '<p>No missing keys.</p></section>';
                continue;
            }

            foreach ($byKey as $key => $locations) {
                $missingSections .= '<div class="card">';
                $missingSections .= '<h3>' . $this->escape($key) . '</h3>';
                $missingSections .= '<ul>';

                foreach ($locations as $location) {
                    $pointer = sprintf('%s:%d:%d', $location['file'], $location['line'], $location['column']);
                    $missingSections .= '<li>';
                    $missingSections .= '<code>' . $this->escape($pointer) . '</code> ';
                    $missingSections .= '<button data-copy="' . $this->escape($pointer) . '">Copy</button>';
                    $missingSections .= '</li>';
                }

                $missingSections .= '</ul></div>';
            }

            $missingSections .= '</section>';
        }

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>I18n Audit Report</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f8fa;color:#1f2328;margin:0;padding:20px}
.container{max-width:1100px;margin:0 auto}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{border:1px solid #d0d7de;padding:8px;text-align:left}
.card{background:#fff;border:1px solid #d0d7de;padding:12px;margin:10px 0}
code{background:#f0f3f6;padding:2px 4px;border-radius:4px}
button{margin-left:6px}
</style>
</head>
<body>
<div class="container">
<h1>I18n Audit Report</h1>
<p>Generated: ' . $this->escape((string) ($meta['timestamp'] ?? '')) . '</p>
<table>
<thead><tr><th>Locale</th><th>Used</th><th>Missing</th><th>Unused</th><th>Total</th></tr></thead>
<tbody>' . $summaryRows . '</tbody>
</table>
' . $missingSections . '
</div>
<script>
document.querySelectorAll("button[data-copy]").forEach(function(btn){btn.addEventListener("click", function(){navigator.clipboard.writeText(btn.getAttribute("data-copy") || ""); btn.textContent = "Copied"; setTimeout(function(){btn.textContent = "Copy";}, 1200);});});
</script>
</body>
</html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
