<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZPMLabs\LaravelI18nAudit\Scanners\BladeScanner;

final class BladeScannerTest extends TestCase
{
    public function test_it_detects_blade_literal_calls(): void
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'i18n_audit_blade_' . uniqid('', true);
        mkdir($tempDir, 0777, true);

        $file = $tempDir . DIRECTORY_SEPARATOR . 'view.blade.php';
        file_put_contents($file, <<<'BLADE'
@lang('a.b')
{{ __('x.y') }}
{!! trans_choice($choiceKey, 2) !!}
BLADE);

        $scanner = new BladeScanner();
        $result = $scanner->scan([$tempDir], []);

        self::assertSame(['a.b', 'x.y'], $result->getUsedKeys());
        $locations = $result->getUsedKeyLocations();
        self::assertArrayHasKey('a.b', $locations);
        self::assertSame(1, $locations['a.b'][0]['line']);
        self::assertGreaterThan(0, $locations['a.b'][0]['column']);
        self::assertGreaterThan(0, $locations['a.b'][0]['char']);
        self::assertCount(1, $result->getDynamicKeys());

        @unlink($file);
        @rmdir($tempDir);
    }
}
