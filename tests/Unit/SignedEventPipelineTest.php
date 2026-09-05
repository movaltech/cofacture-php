<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\EventBuilder;
use Cofacture\Builder\SignaturePlaceholder;
use Cofacture\Domain\DocumentType;
use Cofacture\Domain\Environment;
use Cofacture\Domain\Event;
use Cofacture\Domain\EventDocumentReference;
use Cofacture\Domain\EventParty;
use Cofacture\Domain\EventReceiverPerson;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Reclamo;
use Cofacture\Domain\SoftwareProvider;
use Cofacture\Event\Event as EventCude;
use Cofacture\Signer\CertificateLoader;
use Cofacture\Signer\Credentials;
use Cofacture\Signer\Signer;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of all 5 RADIAN event builders (domain -> build -> sign -> verify), using the
 * same real-world shape as the Go original's builder/event_test.go fixture. Mirrors that file's
 * 5 golden tests (not byte-identical XML, same reasoning as SignedNotesPipelineTest) plus
 * event/event_test.go's CUDE formula, already covered separately by EventTest.
 */
final class SignedEventPipelineTest extends TestCase
{
    private static Credentials $credentials;

    public static function setUpBeforeClass(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        self::$credentials = CertificateLoader::loadPem($certPem, $keyPem);
    }

    private static function sampleEvent(): Event
    {
        return new Event(
            environmentCode: Environment::Habilitacion,
            id: '1',
            issueDate: '2026-02-05',
            issueTime: '09:00:00-05:00',
            documentReference: new EventDocumentReference(
                prefix: 'SETP',
                number: '990068706',
                cufe: '853657dcf2841c55c04338b24cc4db9dfbf87042f1ce1798e53f7b1f0502d00df9bd3f371dea47b02766424976d60ba2',
                hashType: 'CUFE-SHA384',
                documentTypeCode: DocumentType::Invoice,
            ),
            sender: new EventParty(
                name: 'Consumidor Final',
                identification: new Identification(number: '222222222222', typeCode: '13'),
                taxSchemeCode: 'ZZ',
                taxSchemeName: 'No aplica',
            ),
            receiver: new EventParty(
                name: 'MI EMPRESA S.A.S.',
                identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            softwareProvider: new SoftwareProvider(
                providerIdentification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                softwareId: '12345678-1234-1234-1234-123456789012',
            ),
            cude: '0d91ba25b01f5e7dbda870a11b274501d3a62a73e91932c473c86c93f12a142a2ac45876efcde3e679024a01c0be41f9',
            softwareSecurityCode: 'abc123',
            qrUrl: 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=853657dcf2841c55c04338b24cc4db9dfbf87042f1ce1798e53f7b1f0502d00df9bd3f371dea47b02766424976d60ba2',
        );
    }

    public function testAcuseReciboRequiresReceiverPerson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventBuilder::buildAcuseRecibo(self::sampleEvent());
    }

    public function testBuildSignAndVerifyAcuseRecibo(): void
    {
        $ev = self::sampleEvent();
        $ev->receiverPerson = new EventReceiverPerson(
            identification: new Identification(number: '1234567890', typeCode: '13'),
            firstName: 'Juan',
            familyName: 'Pérez',
            jobTitle: 'Gerente de Compras',
        );

        $doc = EventBuilder::buildAcuseRecibo($ev);
        self::assertSame('ApplicationResponse', $doc->documentElement->nodeName);

        $xpath = self::sign($doc);
        self::assertSame('030', $xpath->query('//cbc:ResponseCode')->item(0)?->textContent);
        $person = $xpath->query('//cac:IssuerParty/cac:Person');
        self::assertSame(1, $person->length, 'Acuse de Recibo must carry IssuerParty/Person');
        self::assertSame('Juan', $xpath->query('.//cbc:FirstName', $person->item(0))->item(0)?->textContent);

        self::verifySignature($doc, 'supplier');
    }

    public function testBuildSignAndVerifyRecibidoBien(): void
    {
        $doc = EventBuilder::buildRecibidoBien(self::sampleEvent());
        $xpath = self::sign($doc);
        self::assertSame('032', $xpath->query('//cbc:ResponseCode')->item(0)?->textContent);
        self::assertSame(0, $xpath->query('//cac:IssuerParty')->length);
        self::verifySignature($doc, 'supplier');
    }

    public function testBuildSignAndVerifyAceptacionExpresa(): void
    {
        $doc = EventBuilder::buildAceptacionExpresa(self::sampleEvent());
        $xpath = self::sign($doc);
        self::assertSame('033', $xpath->query('//cbc:ResponseCode')->item(0)?->textContent);
        self::verifySignature($doc, 'supplier');
    }

    public function testBuildSignAndVerifyAceptacionTacita(): void
    {
        $ev = self::sampleEvent();
        // Aceptación Tácita is issuer-generated: DIAN is the recipient of the event, and the
        // issuer (Receiver in the referenced invoice) is who sends it — roles are swapped vs.
        // the other four events, which is why Sender/Receiver are reversed here.
        [$ev->sender, $ev->receiver] = [$ev->receiver, $ev->sender];
        $ev->note = EventCude::tacitAcceptanceNote('1', $ev->cude, 'Consumidor Final', '222222222222');

        $doc = EventBuilder::buildAceptacionTacita($ev);
        $xpath = self::sign($doc);
        self::assertSame('034', $xpath->query('//cbc:ResponseCode')->item(0)?->textContent);
        self::assertSame(1, $xpath->query('//cbc:Note')->length);
        self::assertSame('MI EMPRESA S.A.S.', $xpath->query('//cac:SenderParty//cbc:RegistrationName')->item(0)?->textContent, 'Sender/Receiver must be swapped');
        self::verifySignature($doc, 'supplier');
    }

    public function testBuildSignAndVerifyReclamo(): void
    {
        $base = self::sampleEvent();
        $r = new Reclamo(
            environmentCode: $base->environmentCode,
            id: $base->id,
            issueDate: $base->issueDate,
            issueTime: $base->issueTime,
            documentReference: $base->documentReference,
            sender: $base->sender,
            receiver: $base->receiver,
            softwareProvider: $base->softwareProvider,
            cude: $base->cude,
            softwareSecurityCode: $base->softwareSecurityCode,
            qrUrl: $base->qrUrl,
            rejectionListId: '2',
            rejectionName: 'Reclamo',
        );

        $doc = EventBuilder::buildReclamo($r);
        $xpath = self::sign($doc);
        $rc = $xpath->query('//cbc:ResponseCode')->item(0);
        self::assertSame('031', $rc?->textContent);
        self::assertSame('2', $rc?->getAttribute('listID'));
        self::assertSame('Reclamo', $rc?->getAttribute('name'));
        self::verifySignature($doc, 'supplier');
    }

    private static function sign(\DOMDocument $doc): DOMXPath
    {
        $placeholder = SignaturePlaceholder::find($doc);
        (new Signer(self::$credentials))->sign($doc->documentElement, $placeholder, 'supplier', new \DateTimeImmutable('2026-02-05T09:00:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');
        return $xpath;
    }

    private static function verifySignature(\DOMDocument $doc, string $role): void
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
        $xpath->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');

        // Events have no sts:InvoiceControl — that group only applies to Invoice/Support
        // Document, which have their own DIAN numbering resolution.
        self::assertSame(0, $xpath->query('//sts:InvoiceControl')->length);
        self::assertSame($role, $xpath->query('//xades:ClaimedRole')->item(0)?->textContent);

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
}
