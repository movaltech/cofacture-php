<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Domain\Format;
use PHPUnit\Framework\TestCase;

/**
 * Locks in the exact 2-decimal, no-thousands-separator format DIAN requires — this function is
 * shared by every XML serializer and every CUFE/CUDE/CUDS hash composer in the library, so a
 * regression here would silently corrupt both at once. Mirrors domain/format_test.go.
 */
final class FormatTest extends TestCase
{
    /** @return array<string,array{0:int,1:string}> */
    public static function cases(): array
    {
        return [
            'zero' => [0, '0.00'],
            'sub-peso' => [5, '0.05'],
            'one peso' => [100, '1.00'],
            'large' => [350_000_000, '3500000.00'],
            'negative peso' => [-500, '-5.00'],
            'negative sub-peso' => [-5, '-0.05'],
        ];
    }

    /** @dataProvider cases */
    public function testFormatCents(int $cents, string $want): void
    {
        self::assertSame($want, Format::formatCents($cents));
    }
}
