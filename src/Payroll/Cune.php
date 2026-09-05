<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

use Cofacture\Domain\Format;

/**
 * Computes the Unique Electronic Payroll Code (Código Único de Nómina Electrónica, CUNE) per
 * section 8.1 of DIAN's Technical Annex:
 *
 *   SHA-384(NumNE + FecNE + HorNE + ValDev + ValDed + ValTolNE + NitNE + DocEmp + TipoXML +
 *   SoftwarePIN + TipAmb)
 *
 * $valDevCents/$valDedCents/$valTolNECents are formatted with Format::formatCents() — the same
 * function Builder::build() uses for DevengadosTotal/DeduccionesTotal/ComprobanteTotal — so the
 * hashed amounts and the amounts written to the XML can never drift apart. Mirrors payroll.Cune.
 *
 * **Known unresolved discrepancy** (matches the Go original, not hidden here): the official
 * worked example in Technical Annex section 8.1 does NOT reproduce with this formula under any
 * plausible field-formatting variant tried so far — see tests/Unit/PayrollCuneTest.php, which
 * documents the investigation instead of asserting a possibly-wrong "fix". This function's
 * correctness for the standard format is anchored by a regression vector (also in that test)
 * and, for real submissions, by whether DIAN's certification environment accepts a document
 * built with it — which, per the Go original, it has not yet, in this environment, for an
 * account-enablement reason unrelated to the hash itself.
 */
final class Cune
{
    private function __construct()
    {
    }

    public static function compute(
        string $numNE,
        string $fecNE,
        string $horNE,
        int $valDevCents,
        int $valDedCents,
        int $valTolNECents,
        string $nitNE,
        string $docEmp,
        string $tipoXML,
        string $softwarePin,
        string $tipAmb,
    ): string {
        $input = $numNE . $fecNE . $horNE
            . Format::formatCents($valDevCents) . Format::formatCents($valDedCents) . Format::formatCents($valTolNECents)
            . $nitNE . $docEmp . $tipoXML . $softwarePin . $tipAmb;
        return hash('sha384', $input);
    }
}
