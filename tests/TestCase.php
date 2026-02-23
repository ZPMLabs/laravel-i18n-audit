<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ZPMLabs\LaravelI18nAudit\TranslationScannerServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TranslationScannerServiceProvider::class,
        ];
    }
}
