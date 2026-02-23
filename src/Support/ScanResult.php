<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class ScanResult
{
    /** @var array<string, true> */
    private array $usedKeys = [];

    /**
     * @var array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>
     */
    private array $usedKeyLocations = [];

    /**
     * @var array<int, array{file:string,line:int,expression:string,source:string}>
     */
    private array $dynamicKeys = [];

    /** @var array<string, true> */
    private array $dynamicKeysIndex = [];

    public function addUsedKey(
        string $key,
        ?string $file = null,
        int $line = 0,
        int $column = 0,
        int $char = 0,
        string $source = 'unknown'
    ): void
    {
        $trimmed = trim($key);

        if ($trimmed === '') {
            return;
        }

        $this->usedKeys[$trimmed] = true;

        if ($file === null) {
            return;
        }

        $normalizedFile = PathNormalizer::normalize($file);

        $entry = [
            'file' => $normalizedFile,
            'line' => max(1, $line),
            'column' => max(1, $column),
            'char' => max(1, $char),
            'source' => $source,
        ];

        $signature = strtolower(sprintf(
            '%s|%d|%d|%d|%s|%s',
            $trimmed,
            $entry['line'],
            $entry['column'],
            $entry['char'],
            $entry['file'],
            $entry['source']
        ));

        if (!isset($this->usedKeyLocations[$trimmed])) {
            $this->usedKeyLocations[$trimmed] = [];
        }

        foreach ($this->usedKeyLocations[$trimmed] as $existing) {
            $existingSignature = strtolower(sprintf(
                '%s|%d|%d|%d|%s|%s',
                $trimmed,
                $existing['line'],
                $existing['column'],
                $existing['char'],
                $existing['file'],
                $existing['source']
            ));

            if ($existingSignature === $signature) {
                return;
            }
        }

        $this->usedKeyLocations[$trimmed][] = $entry;
    }

    public function addDynamicKey(string $file, int $line, string $expression, string $source): void
    {
        $entry = [
            'file' => PathNormalizer::normalize($file),
            'line' => $line,
            'expression' => $expression,
            'source' => $source,
        ];

        $signature = strtolower(sprintf('%s|%d|%s|%s', $entry['file'], $entry['line'], $entry['source'], $entry['expression']));

        if (isset($this->dynamicKeysIndex[$signature])) {
            return;
        }

        $this->dynamicKeysIndex[$signature] = true;
        $this->dynamicKeys[] = $entry;
    }

    public function merge(self $other): void
    {
        foreach ($other->getUsedKeys() as $key) {
            $this->usedKeys[$key] = true;
        }

        foreach ($other->getUsedKeyLocations() as $key => $locations) {
            foreach ($locations as $location) {
                $this->addUsedKey(
                    $key,
                    $location['file'],
                    $location['line'],
                    $location['column'],
                    $location['char'],
                    $location['source']
                );
            }
        }

        foreach ($other->getDynamicKeys() as $dynamic) {
            $this->addDynamicKey($dynamic['file'], $dynamic['line'], $dynamic['expression'], $dynamic['source']);
        }
    }

    /** @return array<int, string> */
    public function getUsedKeys(): array
    {
        $keys = array_keys($this->usedKeys);
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>
     */
    public function getUsedKeyLocations(): array
    {
        $output = $this->usedKeyLocations;

        foreach ($output as &$locations) {
            usort(
                $locations,
                static fn (array $left, array $right): int => [$left['file'], $left['line'], $left['column'], $left['char'], $left['source']]
                    <=> [$right['file'], $right['line'], $right['column'], $right['char'], $right['source']]
            );
        }
        unset($locations);

        ksort($output);

        return $output;
    }

    /**
     * @return array<int, array{file:string,line:int,expression:string,source:string}>
     */
    public function getDynamicKeys(): array
    {
        usort(
            $this->dynamicKeys,
            static fn (array $left, array $right): int => [$left['file'], $left['line']] <=> [$right['file'], $right['line']]
        );

        return $this->dynamicKeys;
    }
}
