<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZPMLabs\LaravelI18nAudit\Loaders\TranslationRepositoryLoader;

final class TranslationRepositoryLoaderTest extends TestCase
{
    public function test_it_loads_php_and_json_translation_keys(): void
    {
        $fixtureLangPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'fake-app' . DIRECTORY_SEPARATOR . 'lang';

        $loader = new TranslationRepositoryLoader();
        $loaded = $loader->load(['en'], $fixtureLangPath);

        self::assertSame(['en'], $loaded['locales']);
        self::assertSame(['auth.failed', 'messages.unused', 'messages.welcome'], $loaded['repositories']['en']['phpKeys']);
        self::assertSame(['Reset Password'], $loaded['repositories']['en']['jsonKeys']);
        self::assertContains('auth.failed', $loaded['repositories']['en']['allKeys']);
        self::assertContains('Reset Password', $loaded['repositories']['en']['allKeys']);
    }
}
