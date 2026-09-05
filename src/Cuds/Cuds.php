<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Cuds;

use Cofacture\Domain\Format;
use Cofacture\Domain\Invoice;

/**
 * Computes the Unique Support Document Code (Código Único de Documento Soporte, CUDS) per
 * DIAN's Technical Annex 1.9, section 11.5. Mirrors cuds/cuds.go.
 *
 * The CUDS formula differs from CUFE/CUDE's: instead of three fixed tax slots (VAT+"01",
 * INC+"04", ICA+"03"), the CUDS uses a single CodImp+ValImp pair taken from the first element
 * of headerTaxes — confirmed against the official DIAN Support Document Toolkit v1.1 example
 * (see tests/Unit/CudsTest.php) and matches the structure of the Support Document's QR content.
 * That's why this doesn't reuse Internal\DianHash::seed() the way Cufe/Cude do — the field
 * composition genuinely differs, not just the last component.
 */
final class Cuds
{
    private function __construct()
    {
    }

    /**
     * Calculates the CUDS of a Support Document (documentTypeCode "05") or its Adjustment Note
     * ("95"). $softwarePin is the DIAN-authorized software PIN — the same value that appears in
     * the QR content's PIN field and in sts:QRCode.
     */
    public static function compute(Invoice $doc, string $softwarePin): string
    {
        $taxCode = '';
        $taxCents = 0;
        if ($doc->headerTaxes !== []) {
            $taxCode = $doc->headerTaxes[0]->typeCode;
            $taxCents = $doc->headerTaxes[0]->taxAmountCents;
        }

        $seed = $doc->prefix . $doc->number
            . $doc->issueDate . $doc->issueTime
            . Format::formatCents($doc->totals->lineExtensionCents)
            . $taxCode . Format::formatCents($taxCents)
            . Format::formatCents($doc->totals->payableCents)
            . $doc->supplier->identification->number
            . $doc->customer->identification->number
            . $softwarePin
            . $doc->environmentCode->value;

        return hash('sha384', $seed);
    }
}
