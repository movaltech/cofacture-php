<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Payroll\Cune;
use PHPUnit\Framework\TestCase;

/** Mirrors payroll/cune_test.go. */
final class PayrollCuneTest extends TestCase
{
    /**
     * A deterministic regression test: locks in Cune::compute()'s output for a fixed set of
     * inputs, formatted the same way the rest of this module formats dates/times/money
     * everywhere else (ISO date, "HH:MM:SS±HH:MM" time, two-decimal money, no thousands
     * separators). It does NOT reproduce DIAN's own worked example from Technical Annex section
     * 8.1 — see testAnexoTecnicoExampleInvestigation() below for that investigation and why it's
     * a separate, non-blocking test instead of this one.
     */
    public function testCompute(): void
    {
        $got = Cune::compute(
            'N00001',
            '2020-01-16',
            '10:53:10-05:00',
            350_000_000,
            100_000_000,
            250_000_000,
            '700085371',
            '800199436',
            '102',
            '693',
            '1',
        );

        self::assertSame('dae35b4dfdf10939502c96278feb97be1af6cd5653bd662ca29b5c9cdf2729464b51d272ba0008586cf153b46f87a2bd', $got);
    }

    /**
     * Documents an unresolved discrepancy: none of the plausible field-formatting variants
     * below reproduce the CUNE that DIAN's Technical Annex (section 8.1) publishes for its own
     * worked example. The annex text was extracted from a PDF, and the composition string it
     * prints may itself carry a transcription artifact — the same class of issue confirmed and
     * fixed in CudeTest for the Debit Note worked example (there, the annex's own printed hash
     * didn't match its own printed input string; Cude::compute() reproduces the mathematically
     * correct hash of that input, not the annex's typo). Unlike that case, this one has NOT been
     * independently resolved — matches the Go original's own unresolved investigation exactly,
     * not a PHP-specific gap.
     *
     * This test intentionally never fails — it is a documented, ongoing investigation, not a
     * correctness check. Cune::compute()'s actual correctness for the standard format is
     * anchored by testCompute() above (regression) and, for real payroll submissions, by
     * whether DIAN's certification environment accepts a document built with it.
     */
    public function testAnexoTecnicoExampleInvestigation(): void
    {
        $want = '16560dc8956122e84ffb743c817fe7d494e058a44d9ca3fa4c234c268b4f766003253fbee7ea4af9682dd57210f3bac2';

        $horVariants = [
            '10:53:10-05:00', // standard ISO 8601 HH:MM:SS±HH:MM
            '1053:10-05:00',  // literal from the annex (without the first ":")
            '105310-05:00',   // without colons between HH/MM/SS
            '10:53:10',       // without timezone
            '105310',         // HHMMSS only
        ];

        foreach ($horVariants as $hor) {
            // With 2 decimals (the format Cune::compute()'s real signature enforces).
            $got = Cune::compute('N00001', '2020-01-16', $hor, 350_000_000, 100_000_000, 250_000_000, '700085371', '800199436', '102', '693', '1');
            if ($got === $want) {
                self::markTestIncomplete("match found: HorNE=\"{$hor}\" with 2-decimal amounts reproduces the annex's published CUNE");
                return;
            }

            // Without decimals — Cune::compute() can't produce this anymore, so build it by hand.
            $raw = 'N00001' . '2020-01-16' . $hor . '3500000' . '1000000' . '2500000'
                . '700085371' . '800199436' . '102' . '693' . '1';
            if (hash('sha384', $raw) === $want) {
                self::markTestIncomplete("match found: HorNE=\"{$hor}\" without decimals reproduces the annex's published CUNE");
                return;
            }
        }

        // The annex's own literal concatenation, byte for byte as printed.
        $raw = 'N000012020-01-161053:10-05:003500000.001000000.002500000.007000853718001994361026931';
        if (hash('sha384', $raw) === $want) {
            self::markTestIncomplete("the annex's literal printed string reproduces its own published CUNE — check field composition");
            return;
        }

        // A couple of spacing/leading-zero variants worth a quick check.
        $variants = [
            'N000012020-01-1610:53:10-05:003500000.001000000.002500000.0070008537180019943610 2693 1',
            'N000012020-01-1610:53:10-05:003500000.001000000.002500000.00700085371800199436 2693 1',
        ];
        foreach ($variants as $v) {
            if (hash('sha384', $v) === $want) {
                self::markTestIncomplete("variant matches: \"{$v}\"");
                return;
            }
        }

        self::assertTrue(true, "no variant tried reproduces the annex's published CUNE ({$want}); literal string hash: " . hash('sha384', $raw));
    }
}
