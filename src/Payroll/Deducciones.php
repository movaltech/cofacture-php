<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** The worker's deductions for the period. Mirrors payroll.Deducciones. */
final class Deducciones
{
    public function __construct(
        /** Null = not applicable. */
        public ?DeduccionPct $salud = null,
        /** Null = not applicable. */
        public ?DeduccionPct $fondoPension = null,
        /** Null = not applicable (only for salaries > 4 SMLMV). */
        public ?FondoSP $fondoSP = null,
        public int $retencionFuenteCents = 0,
    ) {
    }
}
