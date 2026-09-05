<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\AttachedDocumentBuilder;
use Cofacture\Builder\SignaturePlaceholder;
use Cofacture\Domain\AttachedDocument;
use Cofacture\Domain\AttachedPartyInfo;
use Cofacture\Domain\Identification;
use Cofacture\Domain\ValidationResult;
use Cofacture\Signer\CertificateLoader;
use Cofacture\Signer\Signer;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use Cofacture\Zip\Zip;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of the AttachedDocument pipeline (domain -> build -> sign -> zip), using the
 * same real-world shape as the Go original's builder/attached_document_test.go fixture. Unlike
 * every other document type in this port, the AttachedDocument signs with an EMPTY
 * xades:ClaimedRole (not "supplier") and has only one ext:UBLExtension (no
 * sts:DianExtensions/InvoiceControl — it carries no numbering-range data of its own).
 */
final class SignedAttachedDocumentPipelineTest extends TestCase
{
    public function testBuildSignAndVerifyForInvoice(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);

        $ad = new AttachedDocument(
            environmentCode: '2',
            id: '1',
            issueDate: '2026-01-20',
            issueTime: '10:05:00-05:00',
            parentDocumentId: 'SETP1',
            sender: new AttachedPartyInfo(
                name: 'MI EMPRESA S.A.S.',
                identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                taxRegimeCode: '48',
                liabilityCodes: ['O-13', 'O-15', 'O-23'],
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            receiver: new AttachedPartyInfo(
                name: 'Juan Pérez',
                identification: new Identification(number: '1234567890', typeCode: '13'),
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            attachmentXml: '<Invoice>simplified content for this test, not validated here</Invoice>',
            validationResults: [
                new ValidationResult(
                    lineId: '1',
                    documentId: 'SETP1',
                    documentCufe: '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
                    documentHashType: 'CUFE-SHA384',
                    documentIssueDate: '2026-01-20',
                    applicationResponseXml: '<ApplicationResponse>simplified content for this test</ApplicationResponse>',
                    validatorId: 'Unidad Especial Dirección de Impuestos y Aduanas Nacionales',
                    validationResultCode: '02',
                    validationDate: '2026-01-20',
                    validationTime: '10:10:00-05:00',
                ),
            ],
        );

        $doc = AttachedDocumentBuilder::buildForInvoice($ad);
        self::assertSame('AttachedDocument', $doc->documentElement->nodeName);

        $placeholder = SignaturePlaceholder::find($doc);
        // Empty role, unlike every other document type — the AttachedDocument's own signature
        // carries no xades:ClaimedRole.
        (new Signer($credentials))->sign($doc->documentElement, $placeholder, '', new \DateTimeImmutable('2026-01-20T10:05:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
        $xpath->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        self::assertSame(0, $xpath->query('//xades:ClaimedRole')->length, 'AttachedDocument must sign with an empty role');
        self::assertSame(0, $xpath->query('//sts:DianExtensions')->length, 'AttachedDocument carries no DianExtensions');
        self::assertSame('SETP1', $xpath->query('//cbc:ParentDocumentID')->item(0)?->textContent);
        self::assertSame('Contenedor de Factura Electrónica', $xpath->query('//cbc:DocumentType')->item(0)?->textContent);

        $pdlr = $xpath->query('//cac:ParentDocumentLineReference');
        self::assertSame(1, $pdlr->length);
        $docRefUuid = $xpath->query('.//cac:DocumentReference/cbc:UUID', $pdlr->item(0));
        self::assertSame('CUFE-SHA384', $docRefUuid->item(0)?->getAttribute('schemeName'));

        // Both cbc:Description CDATA blocks (the wrapped document and the ApplicationResponse)
        // must round-trip verbatim.
        $descriptions = $xpath->query('//cbc:Description');
        self::assertSame(2, $descriptions->length);
        self::assertSame($ad->attachmentXml, $descriptions->item(0)?->textContent);
        self::assertSame($ad->validationResults[0]->applicationResponseXml, $descriptions->item(1)?->textContent);

        $signedInfo = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
        self::assertNotNull($signedInfo);
        $canonSignedInfo = $signedInfo->C14N(false, false);
        $sigValueB64 = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0)->nodeValue;
        $publicKeyDetails = openssl_pkey_get_details($credentials->key);
        $publicKey = openssl_pkey_get_public($publicKeyDetails['key']);
        $verifyResult = openssl_verify($canonSignedInfo, base64_decode($sigValueB64), $publicKey, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $verifyResult, 'signature does not verify against the public key');

        $xml = $doc->saveXML();
        $fileName = Zip::documentFileName(Zip::KIND_ATTACHED_DOCUMENT, '900123456', Zip::SOFTWARE_PROPIO_CODE, 2026, 1);
        $zipBytes = Zip::build([$fileName => $xml]);
        self::assertStringStartsWith("PK\x03\x04", $zipBytes);
    }
}
