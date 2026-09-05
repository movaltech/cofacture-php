<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Payroll\Basico;
use Cofacture\Payroll\Builder;
use Cofacture\Payroll\Devengados;
use Cofacture\Payroll\Empleador;
use Cofacture\Payroll\Nomina;
use Cofacture\Payroll\Pago;
use Cofacture\Payroll\Trabajador;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Mirrors payroll/builder_test.go. */
final class PayrollBuilderTest extends TestCase
{
    /**
     * A minimal but complete Nomina for a normal ("102") payroll — enough to get through
     * Builder::build() without hitting an unrelated validation error, so each test below only
     * needs to override the fields it actually cares about.
     */
    public static function validNomina(): Nomina
    {
        return new Nomina(
            consecutivo: '1',
            prefijo: 'NE',
            numero: 'NE1',
            tipoXML: '102',
            ambiente: '2',
            fechaGen: '2026-01-31',
            horaGen: '09:00:00-05:00',
            fechaIngreso: '2020-01-01',
            fechaLiquidacionInicio: '2026-01-01',
            fechaLiquidacionFin: '2026-01-31',
            tiempoLaborado: 31,
            pais: 'CO',
            departamentoEstado: '11',
            municipioCiudad: '001',
            idioma: 'es',
            periodoNomina: 4,
            tipoMoneda: 'COP',
            TRM: '1.00',
            softwareId: '12345678-1234-1234-1234-123456789012',
            proveedorNIT: '700085371',
            proveedorDV: '1',
            proveedorRazonSocial: 'Proveedor SAS',
            fechasPago: ['2026-01-31'],
            empleador: new Empleador(
                razonSocial: 'Empleador SAS',
                nit: '800199436',
                dv: '1',
                pais: 'CO',
                departamento: '11',
                municipio: '001',
                direccion: 'Calle 1 # 2-3',
            ),
            trabajador: new Trabajador(
                tipoTrabajador: '01',
                subTipoTrabajador: '00',
                tipoDocumento: '13',
                numeroDocumento: '123456789',
                primerApellido: 'Perez',
                primerNombre: 'Juan',
                lugarPais: 'CO',
                lugarDepartamento: '11',
                lugarMunicipio: '001',
                lugarDireccion: 'Calle 1 # 2-3',
                tipoContrato: '1',
                sueldoCents: 350_000_000,
                codigoTrabajador: '1',
            ),
            pago: new Pago(
                forma: '1',
                metodo: '42',
                banco: 'Banco de Prueba',
                tipoCuenta: 'AHORRO',
                numeroCuenta: '123456789',
            ),
            devengados: new Devengados(basico: new Basico(diasTrabajados: 31, sueldoTrabajadoCents: 350_000_000)),
            devengadosTotalCents: 350_000_000,
            deduccionesTotalCents: 0,
            comprobanteTotalCents: 350_000_000,
        );
    }

    /** Confirms a regular "102" NominaIndividual serializes Novedad="false" with an empty
     *  CUNENov, and does not require cuneNovedad to be set. */
    public function testBuildNormalPayrollNovedadIsFalse(): void
    {
        $n = self::validNomina(); // tipoXML "102", cuneNovedad left empty

        $doc = Builder::build($n, 'cune-placeholder', 'sc-placeholder', 'https://example.test/qr');

        $xpath = new DOMXPath($doc);
        $novedad = $xpath->query('//*[local-name()="Novedad"]')->item(0);
        self::assertNotNull($novedad, 'Novedad element not found in the built document');
        self::assertSame('false', $novedad->textContent);
        self::assertSame('', $novedad->getAttribute('CUNENov'));
    }

    /**
     * Confirms Builder::build() rejects an adjustment payroll ("103"/"104") that doesn't carry
     * the original document's CUNE — this is exactly the gap that previously made adjustment
     * payroll unusable in the Go original (Novedad was hardcoded to "false").
     */
    public function testBuildAdjustmentRequiresCuneNovedad(): void
    {
        foreach (['103', '104'] as $tipoXML) {
            $n = self::validNomina();
            $n->tipoXML = $tipoXML;
            $n->cuneNovedad = '';

            try {
                Builder::build($n, 'cune-placeholder', 'sc-placeholder', 'https://example.test/qr');
                self::fail("tipoXML \"{$tipoXML}\": Builder::build() should fail when cuneNovedad is empty");
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    /** Confirms an adjustment payroll with cuneNovedad set serializes Novedad="true" and
     *  carries the original CUNE in the CUNENov attribute. */
    public function testBuildAdjustmentSetsNovedadTrue(): void
    {
        $originalCune = '16560dc8956122e84ffb743c817fe7d494e058a44d9ca3fa4c234c268b4f766003253fbee7ea4af9682dd57210f3bac2';

        $n = self::validNomina();
        $n->tipoXML = '103';
        $n->cuneNovedad = $originalCune;

        $doc = Builder::build($n, 'cune-placeholder', 'sc-placeholder', 'https://example.test/qr');

        $xpath = new DOMXPath($doc);
        $novedad = $xpath->query('//*[local-name()="Novedad"]')->item(0);
        self::assertNotNull($novedad, 'Novedad element not found in the built document');
        self::assertSame('true', $novedad->textContent);
        self::assertSame($originalCune, $novedad->getAttribute('CUNENov'));
    }
}
