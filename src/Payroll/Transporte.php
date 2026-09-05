<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** Transportation allowance and per diems. Mirrors payroll.Transporte. */
final class Transporte
{
    public function __construct(
        public int $auxilioTransporteCents = 0,
        /** Salary-affecting. */
        public int $viaticoManuAlojSCents = 0,
        /** Non-salary-affecting. */
        public int $viaticoManuAlojNSCents = 0,
    ) {
    }
}
