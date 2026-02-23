<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Scanners;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;
use ZPMLabs\LaravelI18nAudit\Support\ScanResult;

final class BladeScanner implements SourceScannerInterface
{
    public function scan(array $paths, array $excludePaths, bool $followSymlinks = false): ScanResult
    {
        $result = new ScanResult();
        $normalizedExcludes = array_map(PathNormalizer::normalize(...), $excludePaths);

        foreach ($paths as $path) {
            $realPath = realpath($path);

            if ($realPath === false || !is_dir($realPath)) {
                continue;
            }

            $flags = RecursiveDirectoryIterator::SKIP_DOTS;

            if ($followSymlinks) {
                $flags |= RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realPath, $flags));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $normalizedPath = PathNormalizer::normalize($file->getPathname());

                if ($this->shouldExclude($normalizedPath, $normalizedExcludes)) {
                    continue;
                }

                if (!str_ends_with($normalizedPath, '.blade.php')) {
                    continue;
                }

                $this->scanBladeFile($normalizedPath, $result);
            }
        }

        return $result;
    }

    private function scanBladeFile(string $filePath, ScanResult $result): void
    {
        $content = file_get_contents($filePath);

        if ($content === false || $content === '') {
            return;
        }

        $literalPatterns = [
            ['pattern' => '/@lang\(\s*(["\'])((?:\\\\.|(?!\1).)*)\1(?=\s*[\),])/s', 'source' => '@lang()'],
            ['pattern' => '/__\(\s*(["\'])((?:\\\\.|(?!\1).)*)\1(?=\s*[\),])/s', 'source' => '__()'],
            ['pattern' => '/trans\(\s*(["\'])((?:\\\\.|(?!\1).)*)\1(?=\s*[\),])/s', 'source' => 'trans()'],
            ['pattern' => '/trans_choice\(\s*(["\'])((?:\\\\.|(?!\1).)*)\1(?=\s*[\),])/s', 'source' => 'trans_choice()'],
        ];

        foreach ($literalPatterns as $entry) {
            preg_match_all($entry['pattern'], $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[2] ?? [] as $match) {
                $offset = is_int($match[1]) ? $match[1] : 0;
                $line = $this->lineFromOffset($content, $offset);
                $column = $this->columnFromOffset($content, $offset);
                $char = $offset + 1;

                $result->addUsedKey(
                    stripslashes($match[0]),
                    $filePath,
                    $line,
                    $column,
                    $char,
                    $entry['source']
                );
            }
        }

        $dynamicPatterns = [
            ['pattern' => '/@lang\(\s*(?!["\'])([^\)]*)\)/s', 'source' => '@lang()'],
            ['pattern' => '/(?:__|trans|trans_choice)\(\s*(?!["\'])([^\)]*)\)/s', 'source' => 'blade-call'],
        ];

        foreach ($dynamicPatterns as $entry) {
            preg_match_all($entry['pattern'], $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] ?? [] as $match) {
                $expression = trim($match[0]);

                if ($expression === '') {
                    continue;
                }

                $offset = is_int($match[1]) ? $match[1] : 0;
                $line = $this->lineFromOffset($content, $offset);
                $result->addDynamicKey($filePath, $line, $expression, $entry['source']);
            }
        }
    }

    private function lineFromOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, max(0, $offset)), "\n") + 1;
    }

    private function columnFromOffset(string $content, int $offset): int
    {
        $prefix = substr($content, 0, max(0, $offset));

        if ($prefix === false || $prefix === '') {
            return 1;
        }

        $lastNewlinePos = strrpos($prefix, "\n");

        if ($lastNewlinePos === false) {
            return $offset + 1;
        }

        return $offset - $lastNewlinePos;
    }

    /**
     * @param array<int, string> $normalizedExcludes
     */
    private function shouldExclude(string $filePath, array $normalizedExcludes): bool
    {
        $normalizedFile = PathNormalizer::normalize($filePath);

        foreach ($normalizedExcludes as $exclude) {
            $normalizedExclude = trim(PathNormalizer::normalize($exclude));

            if ($normalizedExclude === '') {
                continue;
            }

            if (str_contains(strtolower($normalizedFile), strtolower($normalizedExclude))) {
                return true;
            }
        }

        return false;
    }
}
