<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\InvoiceBuilder;
use Cofacture\Builder\SignaturePlaceholder;
use Cofacture\Cufe\Cufe;
use Cofacture\Domain\Address;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Line;
use Cofacture\Domain\NumberingRange;
use Cofacture\Domain\Party;
use Cofacture\Domain\SoftwareProvider;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use Cofacture\Qr\Qr;
use Cofacture\SecurityCode\SecurityCode;
use Cofacture\Signer\CertificateLoader;
use Cofacture\Signer\Signer;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use Cofacture\Zip\Zip;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of the whole MVP pipeline (domain -> build -> hash -> sign -> zip), using a
 * self-signed test certificate (no real DIAN credentials needed). This is the test that
 * actually proves DOMDocument::C14N() canonicalizes correctly with plain createElement()
 * (prefixed tag names + manual xmlns:* attributes, no createElementNS) — the one architectural
 * risk this port carries that the Go original doesn't have to worry about (etree works the
 * same way natively). If the signature verifies independently below, that risk is closed.
 */
final class SignedInvoicePipelineTest extends TestCase
{
    public function testBuildSignAndVerify(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);

        $inv = new Invoice(
            profileId: 'DIAN 2.1',
            environmentCode: '2',
            operationTypeCode: '10',
            documentTypeCode: '01',
            hashType: 'CUFE-SHA384',
            prefix: 'SETP',
            number: '990000001',
            issueDate: '2026-01-15',
            issueTime: '10:15:00-05:00',
            currencyCode: 'COP',
            supplier: new Party(
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
            ),
            customer: new Party(
                entityTypeCode: '2',
                identification: new Identification(number: '222222222222', typeCode: '13'),
                name: 'Consumidor Final',
                taxSchemeCode: 'ZZ',
                taxSchemeName: 'No aplica',
            ),
            headerTaxes: [new Tax(taxableAmountCents: 100000_00, taxAmountCents: 19000_00, percent: 19.0, typeCode: '01', typeName: 'IVA')],
            totals: new Totals(
                lineExtensionCents: 100000_00,
                taxExclusiveCents: 100000_00,
                taxInclusiveCents: 119000_00,
                payableCents: 119000_00,
            ),
            lines: [
                new Line(
                    description: 'Servicio de prueba',
                    quantity: 1.0,
                    unitCode: '94',
                    lineExtensionCents: 100000_00,
                    unitPriceCents: 100000_00,
                    itemCode: '0001',
                    itemTypeCode: '999',
                    itemTypeName: 'Estándar de adopción del contribuyente',
                    taxes: [new Tax(taxableAmountCents: 100000_00, taxAmountCents: 19000_00, percent: 19.0, typeCode: '01', typeName: 'IVA')],
                ),
            ],
            numberingRange: new NumberingRange(
                authorizedCode: '18760000001',
                prefix: 'SETP',
                startNumber: '990000000',
                endNumber: '995000000',
                startDate: '2026-01-01',
                endDate: '2030-01-01',
            ),
            softwareProvider: new SoftwareProvider(
                providerIdentification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                softwareId: '12345678-1234-1234-1234-123456789012',
            ),
        );

        $technicalKey = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';
        $inv->cufe = Cufe::compute($inv, $technicalKey);
        $inv->softwareSecurityCode = SecurityCode::compute($inv->softwareProvider->softwareId, '11111', $inv->prefix . $inv->number);
        $inv->qrUrl = Qr::url($inv->environmentCode, $inv->cufe);

        $doc = InvoiceBuilder::build($inv);
        $root = $doc->documentElement;
        self::assertSame('Invoice', $root->nodeName);

        $placeholder = SignaturePlaceholder::find($doc);
        $signer = new Signer($credentials);
        $signer->sign($root, $placeholder, 'supplier', new \DateTimeImmutable('2026-01-15T10:15:00-05:00'));

        $xml = $doc->saveXML();
        self::assertIsString($xml);

        // Structural assertions via XPath — namespace-aware, using the prefixes exactly as
        // declared on the root (this alone proves DOMDocument::C14N() had genuine namespace
        // nodes to work with; a malformed namespace setup would have made C14N() throw or
        // silently drop the xmlns declarations, which would make these queries return nothing).
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        $idNodes = $xpath->query('//cbc:ID');
        self::assertGreaterThan(0, $idNodes->length, 'expected at least one cbc:ID element to resolve via namespace-aware XPath');

        $uuidNodes = $xpath->query('//cbc:UUID');
        self::assertSame(1, $uuidNodes->length);
        self::assertSame($inv->cufe, $uuidNodes->item(0)->nodeValue);

        $sigNodes = $xpath->query('//ds:Signature');
        self::assertSame(1, $sigNodes->length, 'ds:Signature was not inserted');

        $refNodes = $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference');
        self::assertSame(3, $refNodes->length, 'expected exactly 3 ds:Reference (document, KeyInfo, SignedProperties)');

        $claimedRole = $xpath->query('//xades:ClaimedRole');
        self::assertSame(1, $claimedRole->length);
        self::assertSame('supplier', $claimedRole->item(0)->nodeValue);

        // The real proof: independently re-verify the RSA signature against the public key,
        // exactly the way a real XAdES verifier would — canonicalize ds:SignedInfo and check
        // ds:SignatureValue. If C14N() produced anything even slightly different between when
        // it was signed and when it's re-canonicalized here, this fails.
        $signedInfo = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
        $canonSignedInfo = $signedInfo->C14N(false, false);
        $signatureValueB64 = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0)->nodeValue;
        $publicKeyDetails = openssl_pkey_get_details($credentials->key);
        $publicKey = openssl_pkey_get_public($publicKeyDetails['key']);

        $verifyResult = openssl_verify(
            $canonSignedInfo,
            base64_decode($signatureValueB64),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );
        self::assertSame(1, $verifyResult, 'signature does not verify against the public key — openssl_verify returned ' . $verifyResult);

        // Also confirm the ZIP packaging step runs without error on the signed XML.
        $fileName = Zip::documentFileName(Zip::KIND_INVOICE, '900123456', Zip::SOFTWARE_PROPIO_CODE, 2026, 1);
        $zipBytes = Zip::build([$fileName => $xml]);
        self::assertGreaterThan(0, strlen($zipBytes));
        self::assertStringStartsWith("PK\x03\x04", $zipBytes, 'output should be a valid ZIP local-file-header signature');
    }
}
