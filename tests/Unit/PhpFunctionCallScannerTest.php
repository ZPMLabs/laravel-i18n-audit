<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZPMLabs\LaravelI18nAudit\Scanners\PhpFunctionCallScanner;

final class PhpFunctionCallScannerTest extends TestCase
{
    public function test_it_detects_literal_keys_and_dynamic_keys(): void
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'i18n_audit_php_' . uniqid('', true);
        mkdir($tempDir, 0777, true);

        $file = $tempDir . DIRECTORY_SEPARATOR . 'sample.php';
        file_put_contents($file, <<<'PHP'
<?php

__('a.b');
trans('x.y');
__('a.' . $x);
PHP);

        $scanner = new PhpFunctionCallScanner();
        $result = $scanner->scan([$tempDir], []);

        self::assertSame(['a.b', 'x.y'], $result->getUsedKeys());
        $locations = $result->getUsedKeyLocations();
        self::assertArrayHasKey('a.b', $locations);
        self::assertSame(3, $locations['a.b'][0]['line']);
        self::assertGreaterThan(0, $locations['a.b'][0]['column']);
        self::assertGreaterThan(0, $locations['a.b'][0]['char']);
        self::assertCount(1, $result->getDynamicKeys());

        @unlink($file);
        @rmdir($tempDir);
    }
}
