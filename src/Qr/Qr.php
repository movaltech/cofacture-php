<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Qr;

use Cofacture\Domain\Environment;
use Cofacture\Domain\Format;
use Cofacture\Domain\Invoice;

/**
 * Builds the QR code content required in the graphic representation of DIAN electronic
 * documents (Technical Annex 1.9, section 11.7.1). Mirrors qr/qr.go.
 */
final class Qr
{
    private const HABILITACION_BASE_URL = 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr';
    private const PRODUCCION_BASE_URL = 'https://catalogo-vpfe.dian.gov.co/document/searchqr';

    private function __construct()
    {
    }

    /**
     * $environmentCode: "1" production, "2" certification/testing (the same value as
     * cbc:ProfileExecutionID). DIAN uses different domains per environment. This is the whole
     * QR content for Invoice/Credit Note/Debit Note — just a URL.
     */
    public static function url(Environment $environmentCode, string $documentKey): string
    {
        $base = $environmentCode === Environment::Habilitacion ? self::HABILITACION_BASE_URL : self::PRODUCCION_BASE_URL;
        return $base . '?documentkey=' . $documentKey;
    }

    /** Builds the Support Document's QR URL. Uses the same searchqr endpoint as Invoice/Credit
     *  Note/Debit Note (FindDocument does not redirect) — this is only the URL component; see
     *  supportDocumentContent() for the full QR content the graphic representation requires. */
    public static function supportDocumentUrl(Environment $environmentCode, string $cuds): string
    {
        return self::url($environmentCode, $cuds);
    }

    /**
     * Builds the full QR content for the Support Document (documentTypeCode "05"). Unlike
     * Invoice/Credit Note/Debit Note, whose QR is just a URL, the Support Document requires a
     * multi-line text block followed by the URL (Technical Annex 1.9, section 11.7.1).
     *
     * $softwarePin is the authorized software PIN — the same one used to compute the CUDS.
     * $cuds is the already-computed CUDS (hex SHA-384). Roles in the Support Document: Supplier
     * = non-obligated third party (NumSNO), Customer = issuing company (NITABS).
     */
    public static function supportDocumentContent(Invoice $inv, string $cuds, string $softwarePin): string
    {
        [$codImp, $valImp] = self::firstVatOrHeaderTax($inv);

        return sprintf(
            "N°DocSoporte=%s\nFecha=%s\nHora=%s\nValDS=%s\nCodImp=%s\nValImp=%s\nValTot=%s\nNumSNO=%s\nNITABS=%s\nPIN:%s\nAmb:%s\nCUDS=%s\nURL=%s",
            $inv->prefix . $inv->number,
            $inv->issueDate,
            $inv->issueTime,
            Format::formatCents($inv->totals->lineExtensionCents),
            $codImp,
            $valImp,
            Format::formatCents($inv->totals->payableCents),
            $inv->supplier->identification->number,
            $inv->customer->identification->number,
            $softwarePin,
            $inv->environmentCode === Environment::Habilitacion ? '2' : '1',
            $cuds,
            self::url($inv->environmentCode, $cuds),
        );
    }

    /**
     * Builds the full QR content for the Adjustment Note to the Support Document
     * (documentTypeCode "95"). Follows the same pattern as supportDocumentContent() (a
     * multi-line text block followed by the URL), adapted for the Adjustment Note document type.
     */
    public static function adjustmentNoteContent(Invoice $inv, string $cuds, string $softwarePin): string
    {
        [$codImp, $valImp] = self::firstVatOrHeaderTax($inv);

        return sprintf(
            "N°NotaAjuste=%s\nFecha=%s\nHora=%s\nValNA=%s\nCodImp=%s\nValImp=%s\nValTot=%s\nNumSNO=%s\nNITABS=%s\nPIN:%s\nAmb:%s\nCUDS=%s\nURL=%s",
            $inv->prefix . $inv->number,
            $inv->issueDate,
            $inv->issueTime,
            Format::formatCents($inv->totals->lineExtensionCents),
            $codImp,
            $valImp,
            Format::formatCents($inv->totals->payableCents),
            $inv->supplier->identification->number,
            $inv->customer->identification->number,
            $softwarePin,
            $inv->environmentCode === Environment::Habilitacion ? '2' : '1',
            $cuds,
            self::url($inv->environmentCode, $cuds),
        );
    }

    /**
     * The CodImp/ValImp pair shared by supportDocumentContent()/adjustmentNoteContent(): the
     * first VAT ("01") header tax if there is one, otherwise the first header tax of any type,
     * otherwise "01"/"0.00". Mirrors the equivalent inline loop duplicated in both Go functions.
     *
     * @return array{0: string, 1: string}
     */
    private static function firstVatOrHeaderTax(Invoice $inv): array
    {
        foreach ($inv->headerTaxes as $tax) {
            if ($tax->typeCode === '01') {
                return [$tax->typeCode, Format::formatCents($tax->taxAmountCents)];
            }
        }
        if ($inv->headerTaxes !== []) {
            $first = $inv->headerTaxes[0];
            return [$first->typeCode, Format::formatCents($first->taxAmountCents)];
        }
        return ['01', '0.00'];
    }
}
