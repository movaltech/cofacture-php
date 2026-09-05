<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Cuds\Cuds;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Party;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use PHPUnit\Framework\TestCase;

/** Mirrors cuds/cuds_test.go. */
final class CudsTest extends TestCase
{
    /** Regression guard for the formula (same input as CudeTest's Credit Note example, but
     *  through the CUDS single-tax-slot formula instead of CUFE/CUDE's three fixed slots). */
    public function testCompute(): void
    {
        $doc = new Invoice(
            environmentCode: '1',
            number: '8110007871',
            issueDate: '2019-01-12',
            issueTime: '07:00:00-05:00',
            // In DS: Supplier = non-obligated third party (supplier), Customer = issuer.
            supplier: new Party(identification: new Identification(number: '900373076')),
            customer: new Party(identification: new Identification(number: '8355990')),
            headerTaxes: [new Tax(taxAmountCents: 95_000, typeCode: '01')],
            totals: new Totals(lineExtensionCents: 500_000, payableCents: 595_000),
        );

        $got = Cuds::compute($doc, '12301');

        self::assertSame('3f71b4f6e9b334028ca4cb00aaf4d523e2551bce3175564fdfb53ff90f105c63cf45bef801b0258ce146a526a0da0fef', $got);
        self::assertSame(96, strlen($got), 'SHA-384 in hex');
    }

    /**
     * The official example from the DS v1.1 Toolkit (DocumentoSoporte-OperacionConResidente.xml)
     * — a real CUDS value computed by DIAN, which lets us empirically verify the correct order
     * of the NITs in the hash string, and that only the *first* HeaderTaxes entry is used even
     * when several share the same typeCode.
     */
    public function testComputeOfficialExample(): void
    {
        $doc = new Invoice(
            environmentCode: '2',
            prefix: 'DS',
            number: '236000000',
            issueDate: '2022-02-18',
            issueTime: '13:34:59-05:00',
            // Supplier = SNO (not obligated, the party selling to the issuer).
            supplier: new Party(identification: new Identification(number: '1020')),
            // Customer = ABS (company issuing the DS).
            customer: new Party(identification: new Identification(number: '800197268')),
            // Both TaxSubtotal entries have typeCode "01" (IVA), but only the first is included
            // in the CUDS (same as in the QR: a single CodImp+ValImp pair) — the second (IVA 5%,
            // 110100.00) must NOT be accumulated.
            headerTaxes: [
                new Tax(taxAmountCents: 32_243_000, typeCode: '01'), // IVA 19% = 322430.00
                new Tax(taxAmountCents: 11_010_000, typeCode: '01'), // IVA 5%  = 110100.00
            ],
            totals: new Totals(lineExtensionCents: 389_900_000, payableCents: 415_217_600),
        );

        $got = Cuds::compute($doc, '12345');

        self::assertSame('c96a728f4453822bfc69b94253880d21d29dd1a9424444da07610799c203506d33fa4f16830dbd6ee0febb4711bfa23a', $got);
    }
}
