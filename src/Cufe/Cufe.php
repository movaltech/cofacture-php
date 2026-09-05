<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Cufe;

use Cofacture\Domain\Invoice;
use Cofacture\Internal\DianHash;

/**
 * Computes the Unique Electronic Invoice Code (Código Único de Factura Electrónica, CUFE) per
 * DIAN's Technical Annex 1.9 (Resolución 000165/2023, section 11.2). Mirrors cufe/cufe.go.
 *
 * Cross-checked against the Go implementation directly: for a fixed dummy invoice
 * (prefix "SETP", number "990000001", issueDate "2026-01-15", issueTime "10:15:00-05:00",
 * one 19000-cent IVA header tax, payableCents 119000, supplier NIT "900123456", customer NIT
 * "222222222222", environmentCode "2", technicalKey "fc8eac422eba16e22ffd8c6f94b3f40a6e38162c"),
 * both cufe.Compute() in Go and Cufe::compute() here must produce:
 * 8b5b4b91baa5bd95fe88aa09cf5b8bb09d3f50e8733514ce59cb65d64a9eb560e6fe95e3dbe860bd1b81784fbf5814e1
 * — see tests/Unit/CufeTest.php.
 */
final class Cufe
{
    private function __construct()
    {
    }

    /**
     * $technicalKey is the authorized numbering range's "technical key" (ClTec) — obtained from
     * DIAN's GetNumberingRange web service and never travels inside the XML, so it is not part
     * of Invoice/NumberingRange. The caller is responsible for obtaining and storing it securely.
     *
     * Formula (section 11.2): SHA-384(NumFac+FecFac+HorFac+ValFac+CodImp1+ValImp1+CodImp2+
     * ValImp2+CodImp3+ValImp3+ValTot+NitOFE+NumAdq+ClTec+TipoAmbiente), where CodImp1/2/3 are
     * fixed "01"/"04"/"03" (VAT/INC/ICA) and ValImpN is "0.00" when that tax does not apply.
     */
    public static function compute(Invoice $inv, string $technicalKey): string
    {
        return hash('sha384', DianHash::seed($inv, $technicalKey));
    }
}
