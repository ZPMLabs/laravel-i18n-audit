<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Scanners;

use ZPMLabs\LaravelI18nAudit\Support\ScanResult;

interface SourceScannerInterface
{
    /**
     * @param array<int, string> $paths
     * @param array<int, string> $excludePaths
     */
    public function scan(array $paths, array $excludePaths, bool $followSymlinks = false): ScanResult;
}
