<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Cufe\Cufe;
use Cofacture\Domain\Environment;
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
    /**
     * Uses the official example from section 11.2.1 of the Technical Annex 1.9 (DIAN
     * Resolución 000165/2023) — the same input values and the same expected hash DIAN itself
     * publishes, not a value cross-checked only against this project's own Go implementation
     * (see cofacture/cufe/cufe_test.go's TestCompute_AnexoTecnicoExample, which this mirrors).
     * Confirmed computationally correct here (this test passes) — added because previously
     * nothing in this repository verified Cufe::compute() against DIAN's own published number
     * independently of Go; testComputeMatchesGoImplementation below is circular (Go could have
     * the same bug and this would still pass).
     */
    public function testComputeAnexoTecnicoExample(): void
    {
        $inv = new Invoice(
            environmentCode: Environment::Produccion,
            number: '323200000129',
            issueDate: '2019-01-16',
            issueTime: '10:53:10-05:00',
            supplier: new Party(identification: new Identification(number: '700085371')),
            customer: new Party(identification: new Identification(number: '800199436')),
            headerTaxes: [new Tax(typeCode: '01', taxAmountCents: 28_500_000)], // IVA 285000.00
            totals: new Totals(lineExtensionCents: 150_000_000, payableCents: 178_500_000), // 1500000.00 / 1785000.00
        );

        $got = Cufe::compute($inv, '693ff6f2a553c3646a063436fd4dd9ded0311471');

        self::assertSame(
            '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
            $got,
        );
    }

    public function testComputeMatchesGoImplementation(): void
    {
        $inv = new Invoice(
            environmentCode: Environment::Habilitacion,
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
