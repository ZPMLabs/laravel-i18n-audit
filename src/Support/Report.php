<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Support;

final class Report
{
    /**
     * @param array<int, string> $usedKeys
        * @param array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>> $usedKeyLocations
     * @param array<int, array{file:string,line:int,expression:string,source:string}> $dynamicKeys
     * @param array<string, array<int, string>> $missingByLocale
     * @param array<string, array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>> $missingKeyLocationsByLocale
     * @param array<string, array<int, string>> $unusedByLocale
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly array $usedKeys,
        private readonly array $usedKeyLocations,
        private readonly array $dynamicKeys,
        private readonly array $missingByLocale,
        private readonly array $missingKeyLocationsByLocale,
        private readonly array $unusedByLocale,
        private readonly array $stats,
        private readonly array $meta,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'usedKeys' => $this->usedKeys,
            'usedKeyLocations' => $this->usedKeyLocations,
            'dynamicKeys' => $this->dynamicKeys,
            'missingByLocale' => $this->missingByLocale,
            'missingKeyLocationsByLocale' => $this->missingKeyLocationsByLocale,
            'unusedByLocale' => $this->unusedByLocale,
            'stats' => $this->stats,
            'meta' => $this->meta,
        ];
    }

    /** @return array<int, string> */
    public function usedKeys(): array
    {
        return $this->usedKeys;
    }

    /** @return array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>> */
    public function usedKeyLocations(): array
    {
        return $this->usedKeyLocations;
    }

    /**
     * @return array<int, array{file:string,line:int,expression:string,source:string}>
     */
    public function dynamicKeys(): array
    {
        return $this->dynamicKeys;
    }

    /** @return array<string, array<int, string>> */
    public function missingByLocale(): array
    {
        return $this->missingByLocale;
    }

    /** @return array<string, array<string, array<int, array{file:string,line:int,column:int,char:int,source:string}>>> */
    public function missingKeyLocationsByLocale(): array
    {
        return $this->missingKeyLocationsByLocale;
    }

    /** @return array<string, array<int, string>> */
    public function unusedByLocale(): array
    {
        return $this->unusedByLocale;
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return $this->stats;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
