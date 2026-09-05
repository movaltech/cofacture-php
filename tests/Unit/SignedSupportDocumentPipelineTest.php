<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\AdjustmentNoteBuilder;
use Cofacture\Builder\SignaturePlaceholder;
use Cofacture\Builder\SupportDocumentBuilder;
use Cofacture\Cuds\Cuds;
use Cofacture\Domain\Address;
use Cofacture\Domain\AdjustmentNote;
use Cofacture\Domain\BillingReference;
use Cofacture\Domain\DiscrepancyResponse;
use Cofacture\Domain\DocumentType;
use Cofacture\Domain\Environment;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Line;
use Cofacture\Domain\NumberingRange;
use Cofacture\Domain\Party;
use Cofacture\Domain\PaymentMean;
use Cofacture\Domain\SoftwareProvider;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use Cofacture\Qr\Qr;
use Cofacture\SecurityCode\SecurityCode;
use Cofacture\Signer\CertificateLoader;
use Cofacture\Signer\Credentials;
use Cofacture\Signer\Signer;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use Cofacture\Zip\Zip;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of the Support Document and its Adjustment Note (domain -> build -> CUDS ->
 * sign -> zip), self-signed test certificate. Mirrors the fixture data in the Go original's
 * builder/support_document_test.go and builder/adjustment_note_test.go (the latter's field
 * values come from a real Adjustment Note DIAN's habilitación environment accepted — see that
 * file's own comment) — not byte-identical XML, since PHP/Go serialize differently, but the same
 * real-world shape.
 *
 * Exercises what's genuinely different about this document family versus Invoice/Notes:
 * SupportDocumentPartyXmlBuilder (inverted roles, no CorporateRegistrationScheme, no listName
 * on TaxLevelCode), cac:WithholdingTaxTotal, and — for the Adjustment Note — CreditNote as the
 * root element while still being a Support-Document-family document (no InvoiceControl, since
 * ExtensionsXmlBuilder only serializes it for "01"/"05").
 */
final class SignedSupportDocumentPipelineTest extends TestCase
{
    private static Credentials $credentials;

    public static function setUpBeforeClass(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        self::$credentials = CertificateLoader::loadPem($certPem, $keyPem);
    }

    public function testBuildSignAndVerifySupportDocument(): void
    {
        $sd = self::sampleSupportDocument();
        $sd->cufe = Cuds::compute($sd, '11111');
        $sd->softwareSecurityCode = SecurityCode::compute($sd->softwareProvider->softwareId, '11111', $sd->prefix . $sd->number);
        $sd->qrUrl = Qr::supportDocumentContent($sd, $sd->cufe, '11111');

        $doc = SupportDocumentBuilder::build($sd);
        self::assertSame('Invoice', $doc->documentElement->nodeName);

        $placeholder = SignaturePlaceholder::find($doc);
        (new Signer(self::$credentials))->sign($doc->documentElement, $placeholder, 'supplier', new \DateTimeImmutable('2026-01-20T10:00:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        self::assertSame(1, $xpath->query('//cac:WithholdingTaxTotal')->length);
        self::assertSame('06', $xpath->query('//cac:WithholdingTaxTotal//cac:TaxScheme/cbc:ID')->item(0)?->textContent);

        // The Support Document's QR is a multi-line content block, not just a URL like
        // Invoice/Credit Note/Debit Note — Qr::supportDocumentContent(), not Qr::url().
        $xpath->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');
        $qrCode = $xpath->query('//sts:QRCode')->item(0)?->textContent;
        self::assertStringStartsWith('N°DocSoporte=' . $sd->prefix . $sd->number, $qrCode);
        self::assertStringContainsString("\nCUDS=" . $sd->cufe, $qrCode);
        self::assertStringEndsWith('URL=' . Qr::url($sd->environmentCode, $sd->cufe), $qrCode);

        // TaxLevelCode carries no listName attribute in the Support Document's party structure
        // (unlike Invoice/CreditNote's PartyXmlBuilder, which does set one).
        $tlc = $xpath->query('//cac:AccountingSupplierParty//cbc:TaxLevelCode')->item(0);
        self::assertNotNull($tlc);
        self::assertSame('', $tlc->getAttribute('listName'));
        self::assertFalse($tlc->hasAttribute('listName'));

        // The supplier (SNO) carries no PartyLegalEntity/CorporateRegistrationScheme, unlike
        // the Invoice/Credit Note party structure.
        self::assertSame(0, $xpath->query('//cac:AccountingSupplierParty//cac:PartyLegalEntity')->length);
        self::assertSame(0, $xpath->query('//cac:AccountingSupplierParty//cac:CorporateRegistrationScheme')->length);

        // Line items use StandardItemIdentification (documentTypeCode "05" is not in the
        // Mandante set), and every line carries cac:InvoicePeriod.
        self::assertSame(1, $xpath->query('//cac:StandardItemIdentification')->length);
        self::assertSame(1, $xpath->query('//cac:InvoicePeriod')->length);
        self::assertSame($sd->issueDate, $xpath->query('//cac:InvoicePeriod/cbc:StartDate')->item(0)?->textContent);

        self::verifySignature($doc);

        $xml = $doc->saveXML();
        $fileName = Zip::documentFileName(Zip::KIND_SUPPORT_DOCUMENT, '900123456', Zip::SOFTWARE_PROPIO_CODE, 2026, 1);
        $zipBytes = Zip::build([$fileName => $xml]);
        self::assertStringStartsWith("PK\x03\x04", $zipBytes);
    }

    public function testBuildSignAndVerifyAdjustmentNote(): void
    {
        $sd = self::sampleSupportDocument();
        $sd->cufe = Cuds::compute($sd, '11111');

        $an = new AdjustmentNote(
            profileId: 'DIAN 2.1: Nota de ajuste al documento soporte en adquisiciones efectuadas a sujetos no obligados a expedir factura o documento equivalente',
            environmentCode: $sd->environmentCode,
            operationTypeCode: $sd->operationTypeCode,
            documentTypeCode: DocumentType::AdjustmentNote,
            hashType: 'CUDS-SHA384',
            prefix: 'NAP',
            number: '1',
            issueDate: '2026-01-25',
            issueTime: '10:00:00-05:00',
            currencyCode: 'COP',
            supplier: $sd->supplier,
            customer: $sd->customer,
            headerTaxes: $sd->headerTaxes,
            withholdingTaxes: $sd->withholdingTaxes,
            totals: $sd->totals,
            lines: $sd->lines,
            numberingRange: $sd->numberingRange,
            softwareProvider: $sd->softwareProvider,
            billingReference: new BillingReference(
                prefix: 'SEDS',
                number: '1',
                cufe: $sd->cufe,
                issueDate: $sd->issueDate,
                hashType: 'CUDS-SHA384', // unused by the builder, which hardcodes this correctly — set here for clarity
            ),
            discrepancyResponse: new DiscrepancyResponse(
                referenceId: 'SEDS1',
                responseCode: '1',
                description: 'Corrección de valores',
            ),
        );
        $an->cufe = Cuds::compute($an, '11111');
        $an->softwareSecurityCode = SecurityCode::compute($an->softwareProvider->softwareId, '11111', $an->prefix . $an->number);
        $an->qrUrl = Qr::adjustmentNoteContent($an, $an->cufe, '11111');

        $doc = AdjustmentNoteBuilder::build($an);
        self::assertSame('CreditNote', $doc->documentElement->nodeName);

        $placeholder = SignaturePlaceholder::find($doc);
        (new Signer(self::$credentials))->sign($doc->documentElement, $placeholder, 'supplier', new \DateTimeImmutable('2026-01-25T10:00:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');

        self::assertSame('95', $xpath->query('//cbc:CreditNoteTypeCode')->item(0)?->textContent);
        self::assertSame(1, $xpath->query('//cac:DiscrepancyResponse')->length);
        $billingUuid = $xpath->query('//cac:BillingReference//cbc:UUID')->item(0);
        self::assertNotNull($billingUuid);
        self::assertSame('CUDS-SHA384', $billingUuid->getAttribute('schemeName'));
        self::assertSame($sd->cufe, $billingUuid->textContent);

        // documentTypeCode "95" is not "01" or "05" — ExtensionsXmlBuilder must NOT emit
        // sts:InvoiceControl for the Adjustment Note (same gating as every other non-Invoice/SD
        // document type).
        self::assertSame(0, $xpath->query('//sts:InvoiceControl')->length);

        self::assertSame(1, $xpath->query('//cac:WithholdingTaxTotal')->length);
        self::assertSame(1, $xpath->query('//cac:StandardItemIdentification')->length, 'documentTypeCode 95 is not in the Mandante set');

        // Same multi-line QR content requirement as the Support Document, using the
        // N°NotaAjuste= label instead of N°DocSoporte= — Qr::adjustmentNoteContent().
        $qrCode = $xpath->query('//sts:QRCode')->item(0)?->textContent;
        self::assertStringStartsWith('N°NotaAjuste=' . $an->prefix . $an->number, $qrCode);
        self::assertStringEndsWith('URL=' . Qr::url($an->environmentCode, $an->cufe), $qrCode);

        self::verifySignature($doc);

        $xml = $doc->saveXML();
        $fileName = Zip::documentFileName(Zip::KIND_ADJUSTMENT_NOTE, '900123456', Zip::SOFTWARE_PROPIO_CODE, 2026, 1);
        $zipBytes = Zip::build([$fileName => $xml]);
        self::assertStringStartsWith("PK\x03\x04", $zipBytes);
    }

    private static function verifySignature(\DOMDocument $doc): void
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signedInfo = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
        self::assertNotNull($signedInfo);
        $canonSignedInfo = $signedInfo->C14N(false, false);

        $sigValueB64 = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0)->nodeValue;
        $publicKeyDetails = openssl_pkey_get_details(self::$credentials->key);
        $publicKey = openssl_pkey_get_public($publicKeyDetails['key']);

        $verifyResult = openssl_verify($canonSignedInfo, base64_decode($sigValueB64), $publicKey, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $verifyResult, 'signature does not verify against the public key');
        self::assertSame(3, $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference')->length);
    }

    /** Mirrors the Go original's sampleSupportDocument() — same real-world shape (a ReteRenta
     *  withholding of 3.5% on a base of 1,260,504.20). */
    private static function sampleSupportDocument(): Invoice
    {
        return new Invoice(
            profileId: 'DIAN 2.1: documento soporte en adquisiciones efectuadas a no obligados a facturar.',
            environmentCode: Environment::Habilitacion,
            operationTypeCode: '10', // Resident
            documentTypeCode: DocumentType::SupportDocument,
            hashType: 'CUDS-SHA384',
            prefix: 'DS',
            number: '1',
            issueDate: '2026-01-20',
            issueTime: '10:00:00-05:00',
            currencyCode: 'COP',
            // Roles reversed: Supplier = third party not obligated to invoice (SNO), Customer =
            // issuer. DIAN requires schemeName="31" (NIT) for the SNO.
            supplier: new Party(
                entityTypeCode: '2',
                identification: new Identification(number: '1020304050', typeCode: '31', verificationCode: '8'),
                name: 'María García',
                taxSchemeCode: 'ZZ',
                taxSchemeName: 'No aplica',
                liabilityCodes: ['R-99-PN'],
                taxRegimeCode: '49',
                address: new Address(
                    line: 'Vereda El Rosal',
                    cityCode: '05001',
                    cityName: 'Medellín',
                    stateCode: '05',
                    stateName: 'Antioquia',
                    countryCode: 'CO',
                    countryName: 'Colombia',
                ),
            ),
            customer: new Party(
                entityTypeCode: '2',
                identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '8'),
                name: 'MI EMPRESA S.A.S.',
                address: new Address(
                    line: 'Calle 123 #45-67',
                    cityCode: '11001',
                    cityName: 'Bogotá D.C.',
                    stateCode: '11',
                    stateName: 'Bogotá D.C.',
                    countryCode: 'CO',
                    countryName: 'Colombia',
                ),
                taxRegimeCode: '48',
                liabilityCodes: ['O-13', 'O-15', 'O-23'],
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            paymentMeans: [new PaymentMean(code: '1', paymentMethodCode: '10')],
            headerTaxes: [new Tax(taxableAmountCents: 1_260_504_20, taxAmountCents: 239_495_80, percent: 19.0, typeCode: '01', typeName: 'IVA')],
            // A ReteRenta withholding of 3.5% on a base of 1,260,504.20.
            withholdingTaxes: [new Tax(taxableAmountCents: 1_260_504_20, taxAmountCents: 44_117_65, percent: 3.5, typeCode: '06', typeName: 'ReteRenta')],
            totals: new Totals(
                lineExtensionCents: 1_260_504_20,
                taxExclusiveCents: 1_260_504_20,
                taxInclusiveCents: 1_500_000_00,
                payableCents: 1_500_000_00,
            ),
            lines: [
                new Line(
                    description: 'Laptop Dell Inspiron 15, 8GB RAM, 256GB SSD',
                    quantity: 1.0,
                    unitCode: '94',
                    lineExtensionCents: 1_260_504_20,
                    unitPriceCents: 1_260_504_20,
                    itemCode: 'PROD001',
                    itemTypeCode: '999',
                    itemTypeName: 'Estándar de adopción del contribuyente',
                    taxes: [new Tax(taxableAmountCents: 1_260_504_20, taxAmountCents: 239_495_80, percent: 19.0, typeCode: '01', typeName: 'IVA')],
                ),
            ],
            numberingRange: new NumberingRange(
                authorizedCode: '18760000001',
                prefix: 'SETP',
                startNumber: '1',
                endNumber: '5000',
                startDate: '2024-01-15',
                endDate: '2026-01-15',
            ),
            softwareProvider: new SoftwareProvider(
                providerIdentification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                softwareId: 'fac4203d-2451-4806-8a3e-000000000001',
            ),
        );
    }
}
