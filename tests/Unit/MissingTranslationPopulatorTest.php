<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZPMLabs\LaravelI18nAudit\Support\MissingTranslationPopulator;

final class MissingTranslationPopulatorTest extends TestCase
{
    public function test_it_adds_missing_keys_to_existing_php_and_json_files(): void
    {
        $baseDir = $this->makeTempDir('i18n_populator_existing_');
        $langPath = $baseDir . DIRECTORY_SEPARATOR . 'lang';
        $enDir = $langPath . DIRECTORY_SEPARATOR . 'en';

        mkdir($enDir, 0777, true);

        file_put_contents($enDir . DIRECTORY_SEPARATOR . 'auth.php', <<<'PHP'
<?php

return [
    'failed' => 'Auth failed',
];
PHP);

        file_put_contents($langPath . DIRECTORY_SEPARATOR . 'en.json', <<<'JSON'
{
    "Existing": "Existing"
}
JSON);

        $populator = new MissingTranslationPopulator();
        $result = $populator->populate([
            'en' => ['auth.new_key', 'New Json Key'],
        ], $langPath, 'Miissing Translation for {$locale}');

        self::assertSame(2, $result['created']);
        self::assertSame(['en'], $result['updatedLocales']);

        /** @var mixed $loaded */
        $loaded = include $enDir . DIRECTORY_SEPARATOR . 'auth.php';
        self::assertIsArray($loaded);
        self::assertSame('Miissing Translation for en', $loaded['new_key']);

        $json = json_decode((string) file_get_contents($langPath . DIRECTORY_SEPARATOR . 'en.json'), true);
        self::assertIsArray($json);
        self::assertSame('Miissing Translation for en', $json['New Json Key']);

        $this->deleteDirectory($baseDir);
    }

    public function test_it_creates_locale_group_file_when_missing(): void
    {
        $baseDir = $this->makeTempDir('i18n_populator_create_');
        $langPath = $baseDir . DIRECTORY_SEPARATOR . 'lang';

        mkdir($langPath, 0777, true);

        $populator = new MissingTranslationPopulator();
        $result = $populator->populate([
            'de' => ['messages.welcome'],
        ], $langPath, 'Miissing Translation for {$locale}');

        self::assertSame(1, $result['created']);
        self::assertSame(['de'], $result['updatedLocales']);
        self::assertFileExists($langPath . DIRECTORY_SEPARATOR . 'de' . DIRECTORY_SEPARATOR . 'messages.php');

        /** @var mixed $loaded */
        $loaded = include $langPath . DIRECTORY_SEPARATOR . 'de' . DIRECTORY_SEPARATOR . 'messages.php';
        self::assertIsArray($loaded);
        self::assertSame('Miissing Translation for de', $loaded['welcome']);

        $this->deleteDirectory($baseDir);
    }

    public function test_it_stores_namespaced_keys_in_json_file(): void
    {
        $baseDir = $this->makeTempDir('i18n_populator_namespaced_');
        $langPath = $baseDir . DIRECTORY_SEPARATOR . 'lang';
        mkdir($langPath, 0777, true);

        $populator = new MissingTranslationPopulator();
        $key = 'filament-panels::auth/pages/edit-profile.form.actions.save.label';

        $result = $populator->populate([
            'en' => [$key],
        ], $langPath, 'Miissing Translation for {$locale}');

        self::assertSame(1, $result['created']);
        self::assertSame(['en'], $result['updatedLocales']);

        $jsonPath = $langPath . DIRECTORY_SEPARATOR . 'en.json';
        self::assertFileExists($jsonPath);

        $json = json_decode((string) file_get_contents($jsonPath), true);
        self::assertIsArray($json);
        self::assertSame('Miissing Translation for en', $json[$key]);

        self::assertDirectoryDoesNotExist($langPath . DIRECTORY_SEPARATOR . 'en' . DIRECTORY_SEPARATOR . 'filament-panels::auth');

        $this->deleteDirectory($baseDir);
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
