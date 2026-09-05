<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Domain\Environment;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Party;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use Cofacture\Internal\DianHash;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors cofacture/internal/dianhash/dianhash_test.go — the field-concatenation logic shared
 * by CUFE and CUDE, asserted directly on the seed string (not just the resulting hash) so a
 * wrong field order or separator is immediately diagnosable instead of an opaque hash mismatch.
 */
final class DianHashTest extends TestCase
{
    /**
     * Same official worked example from Technical Annex 1.9 section 11.2.1 that
     * CufeTest::testComputeAnexoTecnicoExample hashes — asserts the exact concatenated seed
     * string itself.
     */
    public function testSeedAnexoTecnicoExample(): void
    {
        $inv = new Invoice(
            environmentCode: Environment::Produccion,
            number: '323200000129',
            issueDate: '2019-01-16',
            issueTime: '10:53:10-05:00',
            supplier: new Party(identification: new Identification(number: '700085371')),
            customer: new Party(identification: new Identification(number: '800199436')),
            headerTaxes: [new Tax(typeCode: '01', taxAmountCents: 28_500_000)], // IVA 285000.00
            totals: new Totals(lineExtensionCents: 150_000_000, payableCents: 178_500_000),
        );

        $technicalKey = '693ff6f2a553c3646a063436fd4dd9ded0311471';
        $want = '323200000129' . '2019-01-16' . '10:53:10-05:00' . '1500000.00'
            . '01' . '285000.00' . '04' . '0.00' . '03' . '0.00'
            . '1785000.00' . '700085371' . '800199436' . $technicalKey . '1';

        self::assertSame($want, DianHash::seed($inv, $technicalKey));
    }

    /** Confirms NumFac is Prefix+Number with no separator. */
    public function testSeedPrefixNumberConcatenation(): void
    {
        $inv = new Invoice(prefix: 'SETP', number: '990000001');
        $got = DianHash::seed($inv, 'key');
        self::assertStringStartsWith('SETP990000001', $got);
    }

    /** Confirms two headerTaxes entries with the same typeCode sum into the same slot. */
    public function testSeedAccumulatesMultipleTaxesOfSameType(): void
    {
        $inv = new Invoice(headerTaxes: [
            new Tax(typeCode: '01', taxAmountCents: 100_00),
            new Tax(typeCode: '01', taxAmountCents: 50_00),
        ]);
        self::assertStringContainsString('01150.00', DianHash::seed($inv, 'key')); // 100.00 + 50.00
    }

    /** Confirms IVA/INC/ICA each land in their own fixed slot, in order, regardless of input order. */
    public function testSeedAllThreeTaxSlots(): void
    {
        $inv = new Invoice(headerTaxes: [
            new Tax(typeCode: '03', taxAmountCents: 300_00), // ICA, listed first
            new Tax(typeCode: '01', taxAmountCents: 100_00), // IVA
            new Tax(typeCode: '04', taxAmountCents: 200_00), // INC
        ]);
        self::assertStringContainsString('01100.00' . '04200.00' . '03300.00', DianHash::seed($inv, 'key'));
    }

    /** Confirms a tax type outside IVA/INC/ICA contributes to none of the three fixed slots. */
    public function testSeedIgnoresOtherTaxTypes(): void
    {
        $inv = new Invoice(headerTaxes: [
            new Tax(typeCode: '06', taxAmountCents: 999_00), // ReteRenta — not part of the formula
        ]);
        self::assertStringContainsString('010.00' . '040.00' . '030.00', DianHash::seed($inv, 'key'));
    }
}
