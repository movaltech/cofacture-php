<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\CreditNoteBuilder;
use Cofacture\Builder\DebitNoteBuilder;
use Cofacture\Builder\SignaturePlaceholder;
use Cofacture\Cude\Cude;
use Cofacture\Domain\Address;
use Cofacture\Domain\BillingReference;
use Cofacture\Domain\CreditNote;
use Cofacture\Domain\DebitNote;
use Cofacture\Domain\DiscrepancyResponse;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Line;
use Cofacture\Domain\NumberingRange;
use Cofacture\Domain\Party;
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
 * End-to-end test of the Credit Note and Debit Note pipeline (domain -> build -> CUDE -> sign ->
 * zip), self-signed test certificate, no real DIAN credentials needed. Mirrors the structure of
 * SignedInvoicePipelineTest — same independent-verification approach — but exercises
 * CreditNoteBuilder/DebitNoteBuilder specifically: the "Mandante" Item branch
 * (LineItemXmlBuilder's DOCUMENT_TYPE_CODES_USING_MANDANTE), cac:RequestedMonetaryTotal instead
 * of cac:LegalMonetaryTotal for Debit Note, and cac:BillingReference/cac:DiscrepancyResponse.
 */
final class SignedNotesPipelineTest extends TestCase
{
    private static Credentials $credentials;

    public static function setUpBeforeClass(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        self::$credentials = CertificateLoader::loadPem($certPem, $keyPem);
    }

    public function testBuildSignAndVerifyCreditNote(): void
    {
        $cn = new CreditNote(
            profileId: 'DIAN 2.1: Nota Crédito',
            environmentCode: '2',
            operationTypeCode: '20', // references a specific invoice
            documentTypeCode: '91',
            hashType: 'CUDE-SHA384',
            prefix: 'SETPNC',
            number: '1',
            issueDate: '2026-01-20',
            issueTime: '10:15:00-05:00',
            currencyCode: 'COP',
            supplier: self::supplier(),
            customer: self::customer(),
            headerTaxes: [new Tax(taxableAmountCents: 100000_00, taxAmountCents: 19000_00, percent: 19.0, typeCode: '01', typeName: 'IVA')],
            totals: new Totals(lineExtensionCents: 100000_00, taxExclusiveCents: 100000_00, taxInclusiveCents: 119000_00, payableCents: 119000_00),
            lines: [self::line()],
            numberingRange: self::numberingRange('SETPNC'),
            softwareProvider: self::softwareProvider(),
            creditNoteTypeCode: '91', // fixed DIAN code for a Credit Note (document type)
            billingReference: new BillingReference(
                prefix: 'SETP',
                number: '1',
                cufe: '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
                issueDate: '2026-01-15',
                hashType: 'CUFE-SHA384',
            ),
            discrepancyResponse: new DiscrepancyResponse(
                referenceId: 'SETP1',
                responseCode: '2',
                description: 'Anulación de factura electrónica',
            ),
        );
        $cn->cufe = Cude::compute($cn, '11111');
        $cn->softwareSecurityCode = SecurityCode::compute($cn->softwareProvider->softwareId, '11111', $cn->prefix . $cn->number);
        $cn->qrUrl = Qr::url($cn->environmentCode, $cn->cufe);

        $doc = CreditNoteBuilder::build($cn);
        self::assertSame('CreditNote', $doc->documentElement->nodeName);

        $placeholder = SignaturePlaceholder::find($doc);
        (new Signer(self::$credentials))->sign($doc->documentElement, $placeholder, 'supplier', new \DateTimeImmutable('2026-01-20T10:15:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        // The Credit Note Item carries InformationContentProviderParty/PowerOfAttorney/
        // AgentParty (the "Mandante" branch), never StandardItemIdentification — this is
        // precisely the branch the mandante-vs-standard fix targets.
        self::assertSame(0, $xpath->query('//cac:StandardItemIdentification')->length, 'Credit Note must not use StandardItemIdentification');
        self::assertSame(1, $xpath->query('//cac:InformationContentProviderParty')->length, 'Credit Note must use the Mandante branch');

        self::assertSame(1, $xpath->query('//cac:DiscrepancyResponse')->length);
        self::assertSame(1, $xpath->query('//cac:BillingReference')->length);
        self::assertSame('LegalMonetaryTotal', $xpath->query('//cac:LegalMonetaryTotal')->item(0)?->localName);

        self::verifySignature($doc);

        $xml = $doc->saveXML();
        $fileName = Zip::documentFileName(Zip::KIND_CREDIT_NOTE, '900123456', Zip::SOFTWARE_PROPIO_CODE, 2026, 1);
        $zipBytes = Zip::build([$fileName => $xml]);
        self::assertStringStartsWith("PK\x03\x04", $zipBytes);
    }

    public function testBuildSignAndVerifyDebitNote(): void
    {
        $dn = new DebitNote(
            profileId: 'DIAN 2.1: Nota Débito',
            environmentCode: '2',
            operationTypeCode: '30', // references a specific invoice
            documentTypeCode: '92',
            hashType: 'CUDE-SHA384',
            prefix: 'SETPND',
            number: '1',
            issueDate: '2026-01-20',
            issueTime: '10:15:00-05:00',
            currencyCode: 'COP',
            supplier: self::supplier(),
            customer: self::customer(),
            headerTaxes: [new Tax(taxableAmountCents: 100000_00, taxAmountCents: 19000_00, percent: 19.0, typeCode: '01', typeName: 'IVA')],
            totals: new Totals(lineExtensionCents: 100000_00, taxExclusiveCents: 100000_00, taxInclusiveCents: 119000_00, payableCents: 119000_00),
            lines: [self::line()],
            numberingRange: self::numberingRange('SETPND'),
            softwareProvider: self::softwareProvider(),
            billingReference: new BillingReference(
                prefix: 'SETP',
                number: '1',
                cufe: '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
                issueDate: '2026-01-15',
                hashType: 'CUFE-SHA384',
            ),
            discrepancyResponse: new DiscrepancyResponse(
                referenceId: 'SETP1',
                responseCode: '1',
                description: 'Intereses por mora',
            ),
        );
        $dn->cufe = Cude::compute($dn, '11111');
        $dn->softwareSecurityCode = SecurityCode::compute($dn->softwareProvider->softwareId, '11111', $dn->prefix . $dn->number);
        $dn->qrUrl = Qr::url($dn->environmentCode, $dn->cufe);

        $doc = DebitNoteBuilder::build($dn);
        self::assertSame('DebitNote', $doc->documentElement->nodeName);

        $placeholder = SignaturePlaceholder::find($doc);
        (new Signer(self::$credentials))->sign($doc->documentElement, $placeholder, 'supplier', new \DateTimeImmutable('2026-01-20T10:15:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        self::assertSame(0, $xpath->query('//cac:StandardItemIdentification')->length, 'Debit Note must not use StandardItemIdentification');
        self::assertSame(1, $xpath->query('//cac:InformationContentProviderParty')->length, 'Debit Note must use the Mandante branch');
        self::assertSame(1, $xpath->query('//cac:RequestedMonetaryTotal')->length, 'Debit Note must use RequestedMonetaryTotal, not LegalMonetaryTotal');
        self::assertSame(0, $xpath->query('//cac:LegalMonetaryTotal')->length);
        // Unlike Invoice/CreditNote, the Debit Note customer does carry PhysicalLocation.
        $customerParty = $xpath->query('//cac:AccountingCustomerParty//cac:PhysicalLocation');
        self::assertSame(1, $customerParty->length, 'Debit Note customer must carry PhysicalLocation');

        self::verifySignature($doc);

        $xml = $doc->saveXML();
        $fileName = Zip::documentFileName(Zip::KIND_DEBIT_NOTE, '900123456', Zip::SOFTWARE_PROPIO_CODE, 2026, 1);
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

    private static function supplier(): Party
    {
        return new Party(
            entityTypeCode: '2',
            identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
            name: 'MI EMPRESA S.A.S.',
            address: new Address(
                line: 'CL 1 2 3',
                cityCode: '11001',
                cityName: 'Bogotá',
                stateCode: '11',
                stateName: 'Bogotá D.C.',
                countryCode: 'CO',
                countryName: 'Colombia',
            ),
            taxSchemeCode: '01',
            taxSchemeName: 'IVA',
            phone: '3000000000',
            email: 'facturacion@example.com',
        );
    }

    private static function customer(): Party
    {
        return new Party(
            entityTypeCode: '2',
            identification: new Identification(number: '222222222222', typeCode: '13'),
            name: 'Consumidor Final',
            address: new Address(
                line: 'CL 1 2 3',
                cityCode: '11001',
                cityName: 'Bogotá',
                stateCode: '11',
                stateName: 'Bogotá D.C.',
                countryCode: 'CO',
                countryName: 'Colombia',
            ),
            taxSchemeCode: 'ZZ',
            taxSchemeName: 'No aplica',
        );
    }

    private static function line(): Line
    {
        return new Line(
            description: 'Servicio de prueba',
            quantity: 1.0,
            unitCode: '94',
            lineExtensionCents: 100000_00,
            unitPriceCents: 100000_00,
            itemCode: '0001',
            itemTypeCode: '999',
            itemTypeName: 'Estándar de adopción del contribuyente',
            taxes: [new Tax(taxableAmountCents: 100000_00, taxAmountCents: 19000_00, percent: 19.0, typeCode: '01', typeName: 'IVA')],
        );
    }

    private static function numberingRange(string $prefix): NumberingRange
    {
        return new NumberingRange(
            authorizedCode: '18760000001',
            prefix: $prefix,
            startNumber: '1',
            endNumber: '5000000',
            startDate: '2026-01-01',
            endDate: '2030-01-01',
        );
    }

    private static function softwareProvider(): SoftwareProvider
    {
        return new SoftwareProvider(
            providerIdentification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
            softwareId: '12345678-1234-1234-1234-123456789012',
        );
    }
}
