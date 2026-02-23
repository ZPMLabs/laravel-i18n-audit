<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests\Feature;

use ZPMLabs\LaravelI18nAudit\Tests\TestCase;

final class TranslationReportCommandTest extends TestCase
{
    public function test_command_generates_expected_json_output_file(): void
    {
        $fixtureBase = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'fake-app';
        $langPath = $fixtureBase . DIRECTORY_SEPARATOR . 'lang';

        config()->set('i18n-audit.lang_path', $langPath);

        $outputFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'i18n-audit-report-' . uniqid('', true) . '.json';

        $this->artisan('i18n:audit', [
            '--locales' => 'en',
            '--paths' => implode(',', [
                $fixtureBase . DIRECTORY_SEPARATOR . 'app',
                $fixtureBase . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
            ]),
            '--exclude' => 'vendor,node_modules,storage,bootstrap/cache',
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertExitCode(0);

        self::assertFileExists($outputFile);

        $decoded = json_decode((string) file_get_contents($outputFile), true);

        self::assertIsArray($decoded);
        self::assertSame(['en'], $decoded['meta']['locales']);
        self::assertContains('auth.failed', $decoded['usedKeys']);
        self::assertContains('messages.unused', $decoded['unusedByLocale']['en']);
        self::assertSame([], $decoded['missingByLocale']['en']);
        self::assertNotEmpty($decoded['dynamicKeys']);
        self::assertArrayHasKey('missingKeyLocationsByLocale', $decoded);
        self::assertSame(4, $decoded['stats']['perLocale']['en']['totalTranslations']);
        self::assertSame(0, $decoded['stats']['perLocale']['en']['missing']);

        @unlink($outputFile);
    }
}
