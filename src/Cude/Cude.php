<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Cude;

use Cofacture\Domain\Invoice;
use Cofacture\Internal\DianHash;

/**
 * Computes the Unique Electronic Document Code (Código Único de Documento Electrónico, CUDE)
 * for Credit Notes and Debit Notes, per DIAN's Technical Annex 1.9 (sections 11.4.3-11.4.6).
 * Mirrors cude/cude.go.
 *
 * The CUFE (Invoice) lives in Cufe\Cufe, not here — the formulas are nearly identical (both
 * built from Internal\DianHash::seed()), but they are distinct identifiers for distinct
 * documents.
 *
 * This same function also computes the CUDE of a Documento Equivalente Electrónico (POS
 * receipt, cinema ticket, toll receipt, etc.) and of its own adjustment notes: the Documento
 * Equivalente Electrónico Technical Annex V1.0 defines a CUDE with the exact same 14-field
 * composition, order, and Software-PIN input as this one — DIAN reused the formula, not just
 * the name. There is no separate class for this on purpose, same reasoning as the Go original.
 */
final class Cude
{
    private function __construct()
    {
    }

    /**
     * Calculates the CUDE of a Credit Note, a Debit Note, a Documento Equivalente Electrónico,
     * or a Documento Equivalente Electrónico adjustment note.
     *
     * The formula is identical to CUFE's in structure and field order (validated against the
     * two official Technical Annex 1.9 examples, one per note type — see tests/Unit/CudeTest.php)
     * — the only real difference is that instead of the numbering range's technical key, the
     * software's PIN is used ("Software-PIN", the same one used for SecurityCode::compute()).
     * $noteBase is the document's own Invoice-shaped data — pass a CreditNote/DebitNote
     * directly (both extend Invoice) or a plain Invoice for a Documento Equivalente Electrónico.
     */
    public static function compute(Invoice $noteBase, string $softwarePin): string
    {
        return hash('sha384', DianHash::seed($noteBase, $softwarePin));
    }
}
