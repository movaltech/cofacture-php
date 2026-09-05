<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** A deduction defined by percentage and amount. Mirrors payroll.DeduccionPct. */
final class DeduccionPct
{
    public function __construct(
        public float $porcentaje = 0.0,
        public int $deduccionCents = 0,
    ) {
    }
}
