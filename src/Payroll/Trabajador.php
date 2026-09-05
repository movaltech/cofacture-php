<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** Identifies the employee. Mirrors payroll.Trabajador. */
final class Trabajador
{
    public function __construct(
        /** "01" employee, etc. */
        public string $tipoTrabajador = '',
        /** "00" not applicable, "01" dependent, etc. */
        public string $subTipoTrabajador = '',
        public bool $altoRiesgoPension = false,
        /** "13" national ID, "31" NIT, etc. */
        public string $tipoDocumento = '',
        public string $numeroDocumento = '',
        public string $primerApellido = '',
        public string $segundoApellido = '',
        public string $primerNombre = '',
        public string $otrosNombres = '',
        /** "CO" */
        public string $lugarPais = '',
        /** DIAN code */
        public string $lugarDepartamento = '',
        /** DIAN code */
        public string $lugarMunicipio = '',
        public string $lugarDireccion = '',
        public bool $salarioIntegral = false,
        /** "1" fixed-term, "2" indefinite, "3" apprenticeship, "4" internship */
        public string $tipoContrato = '',
        /** Base monthly salary. */
        public int $sueldoCents = 0,
        /** Internal employee code. */
        public string $codigoTrabajador = '',
    ) {
    }
}
