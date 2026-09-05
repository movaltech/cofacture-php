<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** The deduction for the Solidarity Pension Fund (Fondo de Solidaridad Pensional). Mirrors
 *  payroll.FondoSP. */
final class FondoSP
{
    public function __construct(
        public float $porcentaje = 0.0,
        public int $deduccionSPCents = 0,
        public float $porcentajeSub = 0.0,
        public int $deduccionSubCents = 0,
    ) {
    }
}
