<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/**
 * Gathers all the data needed to generate, sign and send a NominaIndividual or
 * NominaIndividualDeAjuste. Mirrors payroll.Nomina.
 *
 * Field names intentionally mirror DIAN's own Spanish XML attribute names
 * (NominaIndividualElectronicaXSD.xsd) so the mapping between this class and the wire format
 * stays obvious — only the doc comments are in English, same convention as the Go original.
 *
 * Lives in Cofacture\Payroll rather than Cofacture\Domain, same as in Go: NominaIndividual's
 * structure is not UBL 2.1 and shares nothing with Builder\* — see Builder's own doc comment.
 */
final class Nomina
{
    /** @param string[] $fechasPago */
    public function __construct(
        // Sequence and type.
        /** e.g. "1" */
        public string $consecutivo = '',
        /** e.g. "NE" */
        public string $prefijo = '',
        /** Full number = prefijo + consecutivo */
        public string $numero = '',
        /** "102" NominaIndividual, "103" Adjustment-Replace, "104" Adjustment-Delete */
        public string $tipoXML = '',
        /**
         * The CUNE of the original NominaIndividual this document adjusts. Required when
         * tipoXML is "103" (Adjustment-Replace) or "104" (Adjustment-Delete); Builder::build()
         * rejects an adjustment with this left empty. Ignored (and must be left empty) for a
         * normal "102" payroll — DIAN's Novedad/@CUNENov pair only applies to adjustments.
         */
        public string $cuneNovedad = '',

        /** "1" production, "2" certification/testing. */
        public string $ambiente = '',

        // Period and document-generation dates/times.
        /** "2024-01-31" */
        public string $fechaGen = '',
        /** "09:00:00-05:00" */
        public string $horaGen = '',
        /** "2020-01-01" */
        public string $fechaIngreso = '',
        /** "" empty = not applicable */
        public string $fechaRetiro = '',
        /** "2024-01-01" */
        public string $fechaLiquidacionInicio = '',
        /** "2024-01-31" */
        public string $fechaLiquidacionFin = '',
        /** Days worked in the period. */
        public int $tiempoLaborado = 0,

        // Place of generation.
        /** "CO" */
        public string $pais = '',
        /** DIAN code, e.g. "11" */
        public string $departamentoEstado = '',
        /** DIAN code, e.g. "001" */
        public string $municipioCiudad = '',
        /** ISO 639-1, e.g. "es" */
        public string $idioma = '',

        // Document metadata.
        /** 1=Weekly 2=Ten-day 3=Biweekly 4=Monthly ... */
        public int $periodoNomina = 0,
        /** "COP" */
        public string $tipoMoneda = '',
        /** "1.00" (exchange rate; "1.00" for COP→COP) */
        public string $TRM = '',
        /** Optional free text. */
        public string $notas = '',

        // Own software / Technology Provider (PT).
        /** UUID registered with DIAN. */
        public string $softwareId = '',
        /** Numeric software PIN. */
        public string $pin = '',

        // XML provider (who generates the document — company or PT). For natural persons use
        // proveedorApellido1/proveedorNombre1; for companies use proveedorRazonSocial.
        public string $proveedorNIT = '',
        public string $proveedorDV = '',
        public string $proveedorRazonSocial = '',
        public string $proveedorApellido1 = '',
        public string $proveedorApellido2 = '',
        public string $proveedorNombre1 = '',
        /** OtrosNombres */
        public string $proveedorNombre2 = '',

        /** []"2024-01-31" */
        public array $fechasPago = [],

        public Empleador $empleador = new Empleador(),
        public Trabajador $trabajador = new Trabajador(),
        public Pago $pago = new Pago(),
        public Devengados $devengados = new Devengados(),
        public Deducciones $deducciones = new Deducciones(),

        // Totals (if zero, they are computed from devengados/deducciones by the caller before
        // calling Builder::build() — this library does not compute them for you).
        public int $redondeoCents = 0,
        public int $devengadosTotalCents = 0,
        public int $deduccionesTotalCents = 0,
        public int $comprobanteTotalCents = 0,
    ) {
    }
}
