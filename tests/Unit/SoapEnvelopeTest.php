<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Signer\CertificateLoader;
use Cofacture\Soap\Client;
use Cofacture\Soap\Internal\Envelope;
use Cofacture\Tests\Unit\Support\FakeTransport;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use Cofacture\Xml\El;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * Builds a complete WS-Security envelope and verifies it the same way an independent verifier
 * would: canonicalize wsa:To and ds:SignedInfo with exclusive C14N (not the inclusive variant
 * XAdES uses) and check the signature against the public key. Mirrors
 * soap/envelope_test.go's TestBuildEnvelope_SignatureVerifies — the same self-consistency bar
 * the Go original's own unit test applies (real DIAN confirmation happens by actually submitting
 * to the certification environment, which is outside what a unit test can do without live
 * credentials).
 */
final class SoapEnvelopeTest extends TestCase
{
    public function testEnvelopeIsWellFormedAndSignatureVerifies(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);

        $doc = Envelope::build(Client::HABILITACION_URL, $credentials, 'SendTestSetAsync', static function (DOMElement $body): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendTestSetAsync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:fileName', 'z0000000000000190000000B.zip'));
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode('contenido de prueba')));
            $el->appendChild(El::create($doc, 'wcf:testSetId', '653bf9d9-b2b1-44ae-a66d-3b9cdc4271c3'));
        });

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('wsa', 'http://www.w3.org/2005/08/addressing');
        $xpath->registerNamespace('wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
        $xpath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $toEl = $xpath->query('//wsa:To')->item(0);
        self::assertNotNull($toEl, 'wsa:To not found');
        self::assertSame(Client::HABILITACION_URL, $toEl->textContent);

        $signedInfo = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
        self::assertNotNull($signedInfo, 'ds:Signature has no ds:SignedInfo');

        // The reference must point only to the signed wsa:To (Body and Timestamp are NOT
        // signed).
        $refs = $xpath->query('ds:Reference', $signedInfo);
        self::assertSame(1, $refs->length, 'expected exactly 1 ds:Reference (only wsa:To)');
        self::assertSame('#_to', $refs->item(0)->getAttribute('URI'));

        $canonSignedInfo = $signedInfo->C14N(true, false, null, ['wsa', 'soap', 'wcf']);

        $sigValueEl = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0);
        self::assertNotNull($sigValueEl, 'ds:SignatureValue not found');
        $sigValue = base64_decode($sigValueEl->textContent, true);
        self::assertIsString($sigValue);

        $publicKeyDetails = openssl_pkey_get_details($credentials->key);
        $publicKey = openssl_pkey_get_public($publicKeyDetails['key']);
        $verifyResult = openssl_verify($canonSignedInfo, $sigValue, $publicKey, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $verifyResult, 'the WS-Security signature does not verify against the public key');

        // The certificate is embedded as a BinarySecurityToken, referenced by Direct Reference
        // from KeyInfo — not by thumbprint (see Envelope's doc comment: the thumbprint variant
        // the WSDL's published policy asks for was rejected by the real server).
        $bst = $xpath->query('//wsse:BinarySecurityToken')->item(0);
        self::assertNotNull($bst, 'wsse:BinarySecurityToken not found');
        self::assertSame($credentials->certDerBase64, $bst->textContent);
        $tokenId = $bst->getAttributeNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd', 'Id');
        self::assertNotSame('', $tokenId, 'BinarySecurityToken has no wsu:Id');

        $tokenRef = $xpath->query('//ds:Signature/ds:KeyInfo/wsse:SecurityTokenReference/wsse:Reference')->item(0);
        self::assertNotNull($tokenRef, 'wsse:Reference inside KeyInfo not found');
        self::assertSame('#' . $tokenId, $tokenRef->getAttribute('URI'));
    }

    public function testTransportReceivesSignedEnvelopeAndParsesResult(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);

        $responseXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <SendBillAttachmentAsyncResponse xmlns="http://wcf.dian.colombia">
      <SendBillAttachmentAsyncResult xmlns:b="http://schemas.datacontract.org/2004/07/">
        <b:ZipKey>a1b2c3d4-e5f6-7890-abcd-ef1234567890</b:ZipKey>
      </SendBillAttachmentAsyncResult>
    </SendBillAttachmentAsyncResponse>
  </s:Body>
</s:Envelope>
XML;
        $transport = new FakeTransport($responseXml);
        $client = new Client(Client::HABILITACION_URL, $credentials, $transport);

        $result = $client->sendBillAttachmentAsync('ad900123456000123456789.zip', '<AttachedDocument/>');

        self::assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $result->zipKey);
        self::assertStringContainsString('<wcf:SendBillAttachmentAsync>', $transport->sentBody);
        self::assertStringContainsString('ad900123456000123456789.zip', $transport->sentBody);
        self::assertStringContainsString('action="http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillAttachmentAsync"', $transport->sentContentType);
    }
}
