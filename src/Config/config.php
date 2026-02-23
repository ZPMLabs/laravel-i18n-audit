<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Paths to Scan
    |--------------------------------------------------------------------------
    |
    | These directories will be scanned for translation calls.
    | They are relative to the Laravel base path.
    |
    */

    'scan_paths' => [
        'app',
        'resources/views',
        'routes',
        'config',
        'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths to Exclude
    |--------------------------------------------------------------------------
    |
    | These directories will be ignored during scanning.
    | Matching must be partial path match (normalized).
    |
    */

    'exclude_paths' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        '.git',
        '.idea',
        '.vscode',
        'public/build',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions
    |--------------------------------------------------------------------------
    |
    | Only these file extensions will be scanned.
    |
    */

    'file_extensions' => [
        'php',
        'blade.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Path
    |--------------------------------------------------------------------------
    |
    | Path to lang directory (relative to base_path).
    |
    */

    'lang_path' => 'lang',

    /*
    |--------------------------------------------------------------------------
    | Include Tests
    |--------------------------------------------------------------------------
    |
    | If true, also scan the tests/ directory.
    |
    */

    'include_tests' => false,

    /*
    |--------------------------------------------------------------------------
    | Include Vendor
    |--------------------------------------------------------------------------
    |
    | If true, vendor directory will also be scanned.
    | Default: false.
    |
    */

    'include_vendor' => false,

    /*
    |--------------------------------------------------------------------------
    | Follow Symlinks
    |--------------------------------------------------------------------------
    |
    | Whether to follow symbolic links while scanning.
    |
    */

    'follow_symlinks' => false,

    /*
    |--------------------------------------------------------------------------
    | Detailed Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, the full report payload (including missing key locations)
    | is appended to the configured log file.
    |
    */

    'log_detailed_report' => true,

    /*
    |--------------------------------------------------------------------------
    | Detailed Log Path
    |--------------------------------------------------------------------------
    |
    | File path for appended i18n audit logs. Relative paths are resolved
    | against the Laravel base path.
    |
    */

    'log_path' => 'storage/logs/i18n-audit.log',

    /*
    |--------------------------------------------------------------------------
    | HTML Reporting
    |--------------------------------------------------------------------------
    |
    | Default HTML output path for latest audit report.
    |
    */

    'html_output_path' => 'storage/app/i18n-audit-latest.html',

    /*
    |--------------------------------------------------------------------------
    | Missing Fill Template
    |--------------------------------------------------------------------------
    |
    | Template used when --fill-missing is enabled.
    | Placeholder {$locale} will be replaced with locale code.
    |
    */

    'fill_template' => 'Miissing Translation for {$locale}',

    /*
    |--------------------------------------------------------------------------
    | Dev Route Registration
    |--------------------------------------------------------------------------
    |
    | Automatically register a route in local/dev environments that serves
    | the latest generated HTML report.
    |
    */

    'register_dev_route' => true,

    /*
    |--------------------------------------------------------------------------
    | Dev Route Path
    |--------------------------------------------------------------------------
    |
    | URI path used by the auto-registered development route.
    |
    */

    'dev_route_path' => 'i18n-audit/latest',

    /*
    |--------------------------------------------------------------------------
    | Dev Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware stack applied to the auto-registered development route.
    |
    */

    'dev_route_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    |
    | Default report output format.
    |
    */

    'format' => 'table',
];
