# Laravel I18n Audit

Cross-platform Laravel package for scanning translation usage in source code and comparing it against available keys in `lang/` files.

## Features

- Scans source usage for:
  - `__()`
  - `trans()`
  - `trans_choice()`
  - `Lang::get()`
  - `Lang::choice()`
  - Blade `@lang()`
  - Blade output calls such as `{{ __('...') }}`
- Loads translation repositories from:
  - `lang/{locale}/*.php` (flattened to dot notation)
  - `lang/{locale}.json` (JSON keys)
- Reports:
  - Missing keys per locale
  - Unused keys per locale
  - Dynamic translation key warnings (non-literal arguments)
  - Missing key locations with file, line, column, and char position
- No external binaries; uses native PHP iterators for Linux, Windows, and macOS.

## Installation

```bash
composer require zpmlabs/laravel-i18n-audit
```

Publish config:

```bash
php artisan vendor:publish --tag=i18n-audit-config
```

## Usage

Basic table output:

```bash
php artisan i18n:audit
```

The table output is intentionally compact (per-locale totals).
Detailed entries are appended to `storage/logs/i18n-audit.log` by default.

JSON output:

```bash
php artisan i18n:audit --format=json
```

Custom locales and paths:

```bash
php artisan i18n:audit --locales=sr,en,de --paths=app,resources/views,routes,config,database
```

Write JSON report to file:

```bash
php artisan i18n:audit --format=json --output=storage/app/i18n-audit.json
```

In local/dev environments, the package auto-registers a route to view the latest audit dashboard (Blade page loaded from latest JSON payload in audit log):

`/i18n-audit/latest`

The dashboard includes:

- locale summary table (used/missing/unused/total)
- tabs per locale for focused inspection
- missing key locations with copy buttons (`path:line:column`)
- dynamic warning table
- full raw JSON payload section (complete data visibility)
- action buttons:
  - auto populate missing translations
  - remove currently unused translations

Both dashboard actions automatically rerun `i18n:audit` so data refreshes immediately after changes.

Auto-populate missing translations using default template:

```bash
php artisan i18n:audit --fill-missing
```

Generate standalone HTML file report (optional):

```bash
php artisan i18n:audit --html --html-output=storage/app/i18n-audit-latest.html
```

Use a custom default fill template:

```bash
php artisan i18n:audit --fill-missing --fill-template="Miissing Translation for {$locale}"
```

Fail CI if missing or unused keys exist:

```bash
php artisan i18n:audit --fail-on-missing --fail-on-unused
```

## Command Options

- `--locales=sr,en,de` Comma-separated locales. If omitted, locales are auto-detected from `lang/`.
- `--paths=app,resources/views,routes,config,database` Comma-separated scan roots.
- `--exclude=vendor,node_modules,storage,bootstrap/cache` Comma-separated path fragments to exclude.
- `--format=table|json` Output format (`table` default).
- `--output=storage/app/i18n-audit.json` Optional path to write JSON report.
- `--only-missing` Show only missing keys in table output.
- `--only-unused` Show only unused keys in table output.
- `--html` Generate an HTML report file.
- `--html-output=storage/app/i18n-audit-latest.html` Custom HTML output path.
- `--fill-missing` Automatically create missing translation entries in locale files.
- `--fill-template="Miissing Translation for {$locale}"` Template for auto-filled values.
- `--fail-on-missing` Exit with code `1` if missing keys are found.
- `--fail-on-unused` Exit with code `1` if unused keys are found.

## JSON Output Shape

```json
{
  "usedKeys": ["auth.failed", "Reset Password"],
  "usedKeyLocations": {
    "auth.failed": [
      {
        "file": "app/Http/Controllers/AuthController.php",
        "line": 34,
        "column": 17,
        "char": 1024,
        "source": "__()"
      }
    ]
  },
  "dynamicKeys": [
    {
      "file": "resources/views/home.blade.php",
      "line": 12,
      "expression": "$prefix . $name",
      "source": "blade-call"
    }
  ],
  "missingByLocale": {
    "en": ["messages.missing"]
  },
  "missingKeyLocationsByLocale": {
    "en": {
      "messages.missing": [
        {
          "file": "resources/views/home.blade.php",
          "line": 12,
          "column": 9,
          "char": 421,
          "source": "@lang()"
        }
      ]
    }
  },
  "unusedByLocale": {
    "en": ["messages.unused"]
  },
  "stats": {
    "usedKeysTotal": 2,
    "dynamicKeysTotal": 1,
    "missingTotal": 1,
    "unusedTotal": 1,
    "perLocale": {
      "en": {
        "used": 2,
        "missing": 1,
        "unused": 1,
        "totalTranslations": 3
      }
    }
  },
  "meta": {
    "timestamp": "2026-02-23T10:00:00+00:00",
    "paths": ["app", "resources/views"],
    "exclude": ["vendor"],
    "locales": ["en"],
    "langPath": "lang",
    "warnings": [],
    "detailedLogPath": "storage/logs/i18n-audit.log",
    "dashboardUrl": "http://localhost/i18n-audit/latest"
  }
}
```

## Dynamic Key Limitations

Dynamic calls such as `__('messages.' . $name)` cannot be resolved to a concrete key statically. They are listed in `dynamicKeys` as warnings with file and line information.

## Configuration

Config file is publishable as `config/i18n-audit.php`.

Defaults include scan paths, excludes, language path, file extension filters, symlink behavior,
and detailed logging options:

- `include_tests` (default: `false`)
- `include_vendor` (default: `false`)
- `follow_symlinks` (default: `false`)
- `log_detailed_report` (default: `true`)
- `log_path` (default: `storage/logs/i18n-audit.log`)
- `html_output_path` (default: `storage/app/i18n-audit-latest.html`)
- `fill_template` (default: `Miissing Translation for {$locale}`)
- `register_dev_route` (default: `true`)
- `dev_route_path` (default: `i18n-audit/latest`)
- `dev_route_middleware` (default: `['web']`)

## Cross-platform Notes

- Uses `RecursiveDirectoryIterator` and `RecursiveIteratorIterator`
- Uses path normalization for stable output across separators
- Does not rely on `grep`, `find`, `awk`, or `sed`

## Testing

```bash
composer install
composer test
```

## License

MIT
