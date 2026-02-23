<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZPMLabs\LaravelI18nAudit\Support\KeyFlattener;

final class KeyFlattenerTest extends TestCase
{
    public function test_it_flattens_nested_arrays(): void
    {
        $input = [
            'failed' => 'Auth failed',
            'validation' => [
                'custom' => [
                    'email' => [
                        'required' => 'Email required',
                    ],
                ],
            ],
        ];

        $flattened = KeyFlattener::flatten($input);

        self::assertSame([
            'failed',
            'validation.custom.email.required',
        ], $flattened);
    }
}
