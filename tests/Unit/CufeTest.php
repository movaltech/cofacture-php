<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Cufe\Cufe;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Party;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use PHPUnit\Framework\TestCase;

/**
 * Cross-language golden test: this exact CUFE was computed by running the real Go
 * implementation (cufe.Compute in github.com/diegofxm/cofacture) against the same inputs. If
 * this test passes, HashSeed's field concatenation is byte-for-byte identical to the Go
 * original for this case — not just "looks similar", actually the same output.
 */
final class CufeTest extends TestCase
{
    public function testComputeMatchesGoImplementation(): void
    {
        $inv = new Invoice(
            environmentCode: '2',
            prefix: 'SETP',
            number: '990000001',
            issueDate: '2026-01-15',
            issueTime: '10:15:00-05:00',
            supplier: new Party(identification: new Identification(number: '900123456')),
            customer: new Party(identification: new Identification(number: '222222222222')),
            headerTaxes: [new Tax(typeCode: '01', taxAmountCents: 19000)],
            totals: new Totals(payableCents: 119000),
        );

        $got = Cufe::compute($inv, 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c');

        self::assertSame(
            '8b5b4b91baa5bd95fe88aa09cf5b8bb09d3f50e8733514ce59cb65d64a9eb560e6fe95e3dbe860bd1b81784fbf5814e1',
            $got,
        );
    }
}
