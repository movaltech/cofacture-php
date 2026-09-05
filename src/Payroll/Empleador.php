<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/**
 * Identifies the company or person paying the payroll. For natural persons use
 * primerApellido/primerNombre/etc.; for companies use razonSocial. Mirrors payroll.Empleador —
 * see the package doc comment on Nomina for why field names are in Spanish here.
 */
final class Empleador
{
    public function __construct(
        public string $razonSocial = '',
        public string $primerApellido = '',
        public string $segundoApellido = '',
        public string $primerNombre = '',
        public string $otrosNombres = '',
        public string $nit = '',
        public string $dv = '',
        /** "CO" */
        public string $pais = '',
        /** DIAN code */
        public string $departamento = '',
        /** DIAN code */
        public string $municipio = '',
        public string $direccion = '',
    ) {
    }
}
