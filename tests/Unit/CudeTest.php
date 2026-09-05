<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Cude\Cude;
use Cofacture\Domain\Environment;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Party;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use PHPUnit\Framework\TestCase;

/**
 * Uses the official examples from Technical Annex 1.9 sections 11.4.3-11.4.6 — same input
 * values DIAN publishes. Mirrors cude/cude_test.go.
 */
final class CudeTest extends TestCase
{
    /** Section 11.4.3/11.4.4's official Credit Note example — reproduces the annex's own
     *  published hash exactly. */
    public function testComputeCreditNoteOfficialExample(): void
    {
        $note = new Invoice(
            environmentCode: Environment::Produccion,
            number: '8110007871',
            issueDate: '2019-01-12',
            issueTime: '07:00:00-05:00',
            supplier: new Party(identification: new Identification(number: '900373076')),
            customer: new Party(identification: new Identification(number: '8355990')),
            headerTaxes: [new Tax(taxAmountCents: 95_000, typeCode: '01')], // IVA 950.00
            totals: new Totals(lineExtensionCents: 500_000, payableCents: 595_000),
        );

        $got = Cude::compute($note, '12301');

        self::assertSame('907e4444decc9e59c160a2fb3b6659b33dc5b632a5008922b9a62f83f757b1c448e47f5867f2b50dbdb96f48c7681168', $got);
    }

    /**
     * Section 11.4.5/11.4.6's official Debit Note example — uses the input data from the
     * annex, but NOT the hash it claims to produce.
     *
     * The annex has a transcription error in this specific example: it publishes the
     * composition string "ND10012019-01-1810:58:00-05:0030000.00010.00042400.00030.0032400.0090
     * 019726410254102102012" and states its SHA-384 is "b9483dc2...", but the actual SHA-384 of
     * that string is "3fa73a86..." (verified independently, outside this codebase, with the raw
     * hash function). The field-by-field mapping and the formula match exactly with CUFE and
     * with the Credit Note example above (which does reproduce its own hash correctly) — that's
     * why the mathematically correct hash is asserted here, not the one the annex publishes.
     * Same conclusion as the Go original's cude_test.go.
     */
    public function testComputeDebitNoteOfficialExample(): void
    {
        $note = new Invoice(
            environmentCode: Environment::Habilitacion,
            number: 'ND1001',
            issueDate: '2019-01-18',
            issueTime: '10:58:00-05:00',
            supplier: new Party(identification: new Identification(number: '900197264')),
            customer: new Party(identification: new Identification(number: '10254102')),
            headerTaxes: [new Tax(taxAmountCents: 240_000, typeCode: '04')], // INC 2400.00
            totals: new Totals(lineExtensionCents: 3_000_000, payableCents: 3_240_000),
        );

        $got = Cude::compute($note, '10201');

        self::assertSame('3fa73a86d57d9341c536afde1f85c4efd9d4591c2c22bce4dfb0e6b0d2e83b8f047a8bde7098292e9d2493e60d1c31da', $got);
    }
}
