<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** The payroll's payment method to the worker. Mirrors payroll.Pago. */
final class Pago
{
    public function __construct(
        /** "1" cash, "2" credit */
        public string $forma = '',
        /** "10" cash, "42" bank transfer, etc. */
        public string $metodo = '',
        public string $banco = '',
        /** "AHORRO" (savings), "CORRIENTE" (checking) */
        public string $tipoCuenta = '',
        public string $numeroCuenta = '',
    ) {
    }
}
