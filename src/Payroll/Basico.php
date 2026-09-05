<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** The base salary for the period (required). Mirrors payroll.Basico. */
final class Basico
{
    public function __construct(
        public int $diasTrabajados = 0,
        public int $sueldoTrabajadoCents = 0,
    ) {
    }
}
