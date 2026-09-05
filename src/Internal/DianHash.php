<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Internal;

use Cofacture\Domain\Format;
use Cofacture\Domain\Invoice;

/**
 * Mirrors internal/dianhash/dianhash.go — the field-concatenation logic shared by the CUFE and
 * CUDE formulas, Technical Annex 1.9 sections 11.2 and 11.4. Moved here from
 * Cufe\Internal\HashSeed now that Cude\Cude needs it too, the same way the Go original keeps it
 * in its own top-level internal/dianhash package rather than duplicating it per document type.
 */
final class DianHash
{
    private function __construct()
    {
    }

    /**
     * Builds the input string for the SHA-384 hash shared by CUFE and CUDE: same fields, same
     * order. The only thing that differs between the two is $lastSeedComponent (the numbering
     * range's technical key for CUFE, the software PIN for CUDE) — the caller decides which.
     */
    public static function seed(Invoice $doc, string $lastSeedComponent): string
    {
        $ivaCents = 0;
        $incCents = 0;
        $icaCents = 0;
        foreach ($doc->headerTaxes as $tax) {
            match ($tax->typeCode) {
                '01' => $ivaCents += $tax->taxAmountCents,
                '04' => $incCents += $tax->taxAmountCents,
                '03' => $icaCents += $tax->taxAmountCents,
                default => null,
            };
        }

        return $doc->prefix . $doc->number
            . $doc->issueDate
            . $doc->issueTime
            . Format::formatCents($doc->totals->lineExtensionCents)
            . '01' . Format::formatCents($ivaCents)
            . '04' . Format::formatCents($incCents)
            . '03' . Format::formatCents($icaCents)
            . Format::formatCents($doc->totals->payableCents)
            . $doc->supplier->identification->number
            . $doc->customer->identification->number
            . $lastSeedComponent
            . $doc->environmentCode;
    }
}
