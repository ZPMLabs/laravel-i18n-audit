<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class DetailedAuditLogReader
{
    /**
     * @return array<string, mixed>|null
     */
    public function readLatest(string $logPath): ?array
    {
        if (!is_file($logPath)) {
            return null;
        }

        $content = file_get_contents($logPath);

        if ($content === false || trim($content) === '') {
            return null;
        }

        $fromMarkedBlock = $this->readFromMarkedBlock($content);

        if ($fromMarkedBlock !== null) {
            return $fromMarkedBlock;
        }

        $fromLegacy = $this->readFromLegacyBlocks($content);

        if ($fromLegacy !== null) {
            return $fromLegacy;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFromMarkedBlock(string $content): ?array
    {
        $begin = '-----BEGIN I18N AUDIT JSON-----';
        $end = '-----END I18N AUDIT JSON-----';

        $beginPos = strrpos($content, $begin);

        if ($beginPos === false) {
            return null;
        }

        $jsonStart = $beginPos + strlen($begin);
        $endPos = strpos($content, $end, $jsonStart);

        if ($endPos === false) {
            return null;
        }

        $json = trim(substr($content, $jsonStart, $endPos - $jsonStart));
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFromLegacyBlocks(string $content): ?array
    {
        $blocks = preg_split('/\R{2,}/', trim($content));

        if (!is_array($blocks)) {
            return null;
        }

        for ($index = count($blocks) - 1; $index >= 0; $index--) {
            $block = trim($blocks[$index]);

            if ($block === '') {
                continue;
            }

            $firstNewLine = strpos($block, "\n");

            if ($firstNewLine === false) {
                continue;
            }

            $json = trim(substr($block, $firstNewLine + 1));
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
