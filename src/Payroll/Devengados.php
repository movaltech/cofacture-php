<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** The worker's earnings for the period. Mirrors payroll.Devengados. */
final class Devengados
{
    public function __construct(
        public Basico $basico = new Basico(),
        /** Null = not applicable. */
        public ?Transporte $transporte = null,
    ) {
    }
}
