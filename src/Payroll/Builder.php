<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

use Cofacture\Domain\Format;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Builds the XML tree of a NominaIndividual, with CUNE and CodigoQR already computed. Mirrors
 * payroll.Build (payroll/builder.go). Named Builder (not PayrollBuilder) because it's already
 * namespaced under Cofacture\Payroll — matches Cude\Cude/Cuds\Cuds's naming convention.
 *
 * The document is not signed yet — call Builder\SignaturePlaceholder::find() + Signer::sign()
 * afterward, same pipeline as every other document type in this port.
 *
 * The caller is responsible for computing:
 *  - $n->devengadosTotalCents / $n->deduccionesTotalCents / $n->comprobanteTotalCents
 *  - The CUNE via Cune::compute() and assigning it to the $cune parameter below
 *  - The SoftwareSC via SecurityCode::compute($softwareId, $pin, $numDoc) and assigning it to
 *    $softwareSc below
 *  - The CodigoQR via Qr::url($ambiente, $cune) and assigning it to $codigoQr below
 */
final class Builder
{
    private const NS_NOMINA = 'dian:gov:co:facturaelectronica:NominaIndividual';
    private const SCHEMA_LOCATION_NOMINA = 'dian:gov:co:facturaelectronica:NominaIndividual NominaIndividualElectronicaXSD.xsd';

    /** The fixed value of the InformacionGeneral/@EncripCUNE attribute. */
    public const ENCRIP_CUNE = 'CUNE-SHA384';
    /** The fixed value of the InformacionGeneral/@Version attribute. */
    public const VERSION = 'V1.0: Documento Soporte de Pago de Nómina Electrónica';

    private function __construct()
    {
    }

    public static function build(Nomina $n, string $cune, string $softwareSc, string $codigoQr): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = false;

        $root = $doc->createElementNS(self::NS_NOMINA, 'NominaIndividual');
        $doc->appendChild($root);
        El::declareNamespace($root, '', self::NS_NOMINA);
        // "xs" mapped to the XSI namespace URI (not a typo — this is what the Go original
        // literally emits, replicated here for fidelity even though it's an unusual choice).
        El::declareNamespace($root, 'xs', NS::NS_XSI);
        El::declareNamespace($root, 'ds', NS::NS_DS);
        El::declareNamespace($root, 'ext', NS::NS_EXT);
        El::declareNamespace($root, 'xades', NS::NS_XADES);
        El::declareNamespace($root, 'xades141', NS::NS_XADES141);
        El::declareNamespace($root, 'xsi', NS::NS_XSI);
        $root->setAttribute('SchemaLocation', '');
        $root->setAttributeNS(NS::NS_XSI, 'xsi:schemaLocation', self::SCHEMA_LOCATION_NOMINA);

        // ext:UBLExtensions — just an empty ext:UBLExtension reserved for the signature.
        // (NominaIndividual has no DianExtensions: its equivalents are ProveedorXML, CodigoQR, etc.)
        $extensions = El::create($doc, 'ext:UBLExtensions');
        $root->appendChild($extensions);
        $extension = El::create($doc, 'ext:UBLExtension');
        $extensions->appendChild($extension);
        $extension->appendChild(El::create($doc, 'ext:ExtensionContent'));

        // Novedad marks this document as an adjustment ("novedad") of a previously submitted
        // NominaIndividual — true for tipoXML "103"/"104", false for a normal "102" payroll.
        // cuneNovedad must carry the original document's CUNE when this is an adjustment.
        $isAdjustment = $n->tipoXML === '103' || $n->tipoXML === '104';
        if ($isAdjustment && $n->cuneNovedad === '') {
            throw new InvalidArgumentException('payroll: cuneNovedad is required when tipoXML is an adjustment ("103"/"104")');
        }
        $novedad = self::el($doc, 'Novedad', self::boolStr($isAdjustment));
        $root->appendChild($novedad);
        $novedad->setAttribute('CUNENov', $n->cuneNovedad);

        // Periodo
        $fecRetiro = $n->fechaRetiro !== '' ? $n->fechaRetiro : '9999-12-31';
        $periodo = self::el($doc, 'Periodo');
        $root->appendChild($periodo);
        $periodo->setAttribute('FechaIngreso', $n->fechaIngreso);
        $periodo->setAttribute('FechaRetiro', $fecRetiro);
        $periodo->setAttribute('FechaLiquidacionInicio', $n->fechaLiquidacionInicio);
        $periodo->setAttribute('FechaLiquidacionFin', $n->fechaLiquidacionFin);
        $periodo->setAttribute('TiempoLaborado', (string) $n->tiempoLaborado);
        $periodo->setAttribute('FechaGen', $n->fechaGen);

        // NumeroSecuenciaXML
        $numSeq = self::el($doc, 'NumeroSecuenciaXML');
        $root->appendChild($numSeq);
        $numSeq->setAttribute('CodigoTrabajador', $n->trabajador->codigoTrabajador);
        $numSeq->setAttribute('Prefijo', $n->prefijo);
        $numSeq->setAttribute('Consecutivo', $n->consecutivo);
        $numSeq->setAttribute('Numero', $n->numero);

        // LugarGeneracionXML
        $lugar = self::el($doc, 'LugarGeneracionXML');
        $root->appendChild($lugar);
        $lugar->setAttribute('Pais', $n->pais);
        $lugar->setAttribute('DepartamentoEstado', $n->departamentoEstado);
        $lugar->setAttribute('MunicipioCiudad', $n->municipioCiudad);
        $lugar->setAttribute('Idioma', $n->idioma);

        // ProveedorXML
        $proveedor = self::el($doc, 'ProveedorXML');
        $root->appendChild($proveedor);
        $proveedor->setAttribute('RazonSocial', $n->proveedorRazonSocial);
        $proveedor->setAttribute('PrimerApellido', $n->proveedorApellido1);
        $proveedor->setAttribute('SegundoApellido', $n->proveedorApellido2);
        $proveedor->setAttribute('PrimerNombre', $n->proveedorNombre1);
        $proveedor->setAttribute('OtrosNombres', $n->proveedorNombre2);
        $proveedor->setAttribute('NIT', $n->proveedorNIT);
        $proveedor->setAttribute('DV', $n->proveedorDV);
        $proveedor->setAttribute('SoftwareID', $n->softwareId);
        $proveedor->setAttribute('SoftwareSC', $softwareSc);

        // CodigoQR
        $root->appendChild(self::el($doc, 'CodigoQR', $codigoQr));

        // InformacionGeneral
        $info = self::el($doc, 'InformacionGeneral');
        $root->appendChild($info);
        $info->setAttribute('Version', self::VERSION);
        $info->setAttribute('Ambiente', $n->ambiente);
        $info->setAttribute('TipoXML', $n->tipoXML);
        $info->setAttribute('CUNE', $cune);
        $info->setAttribute('EncripCUNE', self::ENCRIP_CUNE);
        $info->setAttribute('FechaGen', $n->fechaGen);
        $info->setAttribute('HoraGen', $n->horaGen);
        $info->setAttribute('PeriodoNomina', (string) $n->periodoNomina);
        $info->setAttribute('TipoMoneda', $n->tipoMoneda);
        $info->setAttribute('TRM', $n->TRM);

        // Notas (optional)
        if ($n->notas !== '') {
            $root->appendChild(self::el($doc, 'Notas', $n->notas));
        }

        // Empleador
        $emp = self::el($doc, 'Empleador');
        $root->appendChild($emp);
        $emp->setAttribute('RazonSocial', $n->empleador->razonSocial);
        $emp->setAttribute('PrimerApellido', $n->empleador->primerApellido);
        $emp->setAttribute('SegundoApellido', $n->empleador->segundoApellido);
        $emp->setAttribute('PrimerNombre', $n->empleador->primerNombre);
        $emp->setAttribute('OtrosNombres', $n->empleador->otrosNombres);
        $emp->setAttribute('NIT', $n->empleador->nit);
        $emp->setAttribute('DV', $n->empleador->dv);
        $emp->setAttribute('Pais', $n->empleador->pais);
        $emp->setAttribute('DepartamentoEstado', $n->empleador->departamento);
        $emp->setAttribute('MunicipioCiudad', $n->empleador->municipio);
        $emp->setAttribute('Direccion', $n->empleador->direccion);

        // Trabajador
        $trab = self::el($doc, 'Trabajador');
        $root->appendChild($trab);
        $trab->setAttribute('TipoTrabajador', $n->trabajador->tipoTrabajador);
        $trab->setAttribute('SubTipoTrabajador', $n->trabajador->subTipoTrabajador);
        $trab->setAttribute('AltoRiesgoPension', self::boolStr($n->trabajador->altoRiesgoPension));
        $trab->setAttribute('TipoDocumento', $n->trabajador->tipoDocumento);
        $trab->setAttribute('NumeroDocumento', $n->trabajador->numeroDocumento);
        $trab->setAttribute('PrimerApellido', $n->trabajador->primerApellido);
        $trab->setAttribute('SegundoApellido', $n->trabajador->segundoApellido);
        $trab->setAttribute('PrimerNombre', $n->trabajador->primerNombre);
        $trab->setAttribute('OtrosNombres', $n->trabajador->otrosNombres);
        $trab->setAttribute('LugarTrabajoPais', $n->trabajador->lugarPais);
        $trab->setAttribute('LugarTrabajoDepartamentoEstado', $n->trabajador->lugarDepartamento);
        $trab->setAttribute('LugarTrabajoMunicipioCiudad', $n->trabajador->lugarMunicipio);
        $trab->setAttribute('LugarTrabajoDireccion', $n->trabajador->lugarDireccion);
        $trab->setAttribute('SalarioIntegral', self::boolStr($n->trabajador->salarioIntegral));
        $trab->setAttribute('TipoContrato', $n->trabajador->tipoContrato);
        $trab->setAttribute('Sueldo', Format::formatCents($n->trabajador->sueldoCents));
        $trab->setAttribute('CodigoTrabajador', $n->trabajador->codigoTrabajador);

        // Pago
        $pago = self::el($doc, 'Pago');
        $root->appendChild($pago);
        $pago->setAttribute('Forma', $n->pago->forma);
        $pago->setAttribute('Metodo', $n->pago->metodo);
        $pago->setAttribute('Banco', $n->pago->banco);
        $pago->setAttribute('TipoCuenta', $n->pago->tipoCuenta);
        $pago->setAttribute('NumeroCuenta', $n->pago->numeroCuenta);

        // FechasPagos
        if ($n->fechasPago === []) {
            throw new InvalidArgumentException('payroll: fechasPago must not be empty');
        }
        $fechas = self::el($doc, 'FechasPagos');
        $root->appendChild($fechas);
        foreach ($n->fechasPago as $f) {
            $fechas->appendChild(self::el($doc, 'FechaPago', $f));
        }

        // Devengados
        $dev = self::el($doc, 'Devengados');
        $root->appendChild($dev);
        $basico = self::el($doc, 'Basico');
        $dev->appendChild($basico);
        $basico->setAttribute('DiasTrabajados', (string) $n->devengados->basico->diasTrabajados);
        $basico->setAttribute('SueldoTrabajado', Format::formatCents($n->devengados->basico->sueldoTrabajadoCents));

        if ($n->devengados->transporte !== null) {
            $t = $n->devengados->transporte;
            $tr = self::el($doc, 'Transporte');
            $dev->appendChild($tr);
            $tr->setAttribute('AuxilioTransporte', Format::formatCents($t->auxilioTransporteCents));
            // ViaticoManuAlojS/NS are only emitted when > 0 (NIE072/073 rejects zeros).
            if ($t->viaticoManuAlojSCents > 0) {
                $tr->setAttribute('ViaticoManuAlojS', Format::formatCents($t->viaticoManuAlojSCents));
            }
            if ($t->viaticoManuAlojNSCents > 0) {
                $tr->setAttribute('ViaticoManuAlojNS', Format::formatCents($t->viaticoManuAlojNSCents));
            }
        }

        // Deducciones
        $ded = self::el($doc, 'Deducciones');
        $root->appendChild($ded);
        if ($n->deducciones->salud !== null) {
            $s = $n->deducciones->salud;
            $sal = self::el($doc, 'Salud');
            $ded->appendChild($sal);
            $sal->setAttribute('Porcentaje', self::pct($s->porcentaje));
            $sal->setAttribute('Deduccion', Format::formatCents($s->deduccionCents));
        }
        if ($n->deducciones->fondoPension !== null) {
            $fp = $n->deducciones->fondoPension;
            $pension = self::el($doc, 'FondoPension');
            $ded->appendChild($pension);
            $pension->setAttribute('Porcentaje', self::pct($fp->porcentaje));
            $pension->setAttribute('Deduccion', Format::formatCents($fp->deduccionCents));
        }
        if ($n->deducciones->fondoSP !== null) {
            $fsp = $n->deducciones->fondoSP;
            $fondoSP = self::el($doc, 'FondoSP');
            $ded->appendChild($fondoSP);
            $fondoSP->setAttribute('Porcentaje', self::pct($fsp->porcentaje));
            $fondoSP->setAttribute('DeduccionSP', Format::formatCents($fsp->deduccionSPCents));
            $fondoSP->setAttribute('PorcentajeSub', self::pct($fsp->porcentajeSub));
            $fondoSP->setAttribute('DeduccionSub', Format::formatCents($fsp->deduccionSubCents));
        }
        if ($n->deducciones->retencionFuenteCents !== 0) {
            $ded->appendChild(self::el($doc, 'RetencionFuente', Format::formatCents($n->deducciones->retencionFuenteCents)));
        }

        // Totales
        $root->appendChild(self::el($doc, 'Redondeo', Format::formatCents($n->redondeoCents)));
        $root->appendChild(self::el($doc, 'DevengadosTotal', Format::formatCents($n->devengadosTotalCents)));
        $root->appendChild(self::el($doc, 'DeduccionesTotal', Format::formatCents($n->deduccionesTotalCents)));
        $root->appendChild(self::el($doc, 'ComprobanteTotal', Format::formatCents($n->comprobanteTotalCents)));

        return $doc;
    }

    /** Creates a namespace-aware element in the NominaIndividual default namespace — none of
     *  these elements are prefixed, so Xml\El::create() (which requires a "prefix:Name" string)
     *  doesn't apply here. */
    private static function el(DOMDocument $doc, string $name, ?string $value = null): DOMElement
    {
        return $value === null
            ? $doc->createElementNS(self::NS_NOMINA, $name)
            : $doc->createElementNS(self::NS_NOMINA, $name, $value);
    }

    private static function pct(float $v): string
    {
        return sprintf('%.2f', $v);
    }

    private static function boolStr(bool $b): string
    {
        return $b ? 'true' : 'false';
    }
}
