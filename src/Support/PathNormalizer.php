<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class PathNormalizer
{
    public static function normalize(string $path): string
    {
        $normalized = str_replace(['\\', '/'], '/', $path);
        $normalized = preg_replace('~/+~', '/', $normalized) ?? $normalized;

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    public static function relativeTo(string $basePath, string $targetPath): string
    {
        $base = self::normalize((string) realpath($basePath) ?: $basePath);
        $target = self::normalize((string) realpath($targetPath) ?: $targetPath);

        if (str_starts_with(strtolower($target), strtolower($base . '/'))) {
            return substr($target, strlen($base) + 1);
        }

        return $target;
    }

    public static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
        }

        return str_starts_with($path, '/');
    }
}
