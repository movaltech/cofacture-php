<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Signer\CertificateLoader;
use Cofacture\Soap\Client;
use Cofacture\Tests\Unit\Support\FakeTransport;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use PHPUnit\Framework\TestCase;

/**
 * Confirms the 4 operations added alongside sendBillSync/getStatus/etc. — getXmlByDocumentKey,
 * getReferenceNotes, getDocumentInfo, getExchangeEmails — parse correctly, using a FakeTransport
 * (same approach as SoapOperationsTest). These 4 mirror operations the Go original added after
 * confirming they exist in the real WSDL (previously documented there as "not implemented");
 * none of the 4 have been exercised against DIAN's real certification environment in either
 * language yet, so response fixtures here are built from the WSDL's own schema shape, not a
 * captured real response.
 */
final class SoapDocumentQueriesTest extends TestCase
{
    private static function client(FakeTransport $transport): Client
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);
        return new Client(Client::HABILITACION_URL, $credentials, $transport);
    }

    public function testGetXmlByDocumentKeyParsesResponse(): void
    {
        $xmlPayload = base64_encode('<Invoice/>');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetXmlByDocumentKeyResponse xmlns="http://wcf.dian.colombia">
      <GetXmlByDocumentKeyResult xmlns:b="http://schemas.datacontract.org/2004/07/EventResponse">
        <b:Code>00</b:Code>
        <b:Message>OK</b:Message>
        <b:XmlBytesBase64>{$xmlPayload}</b:XmlBytesBase64>
        <b:ValidationDate>2026-08-30T10:00:00</b:ValidationDate>
      </GetXmlByDocumentKeyResult>
    </GetXmlByDocumentKeyResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->getXmlByDocumentKey('some-track-id');

        self::assertSame('00', $got->code);
        self::assertSame('<Invoice/>', $got->xmlBytes);
        self::assertSame('2026-08-30T10:00:00', $got->validationDate);
    }

    public function testGetReferenceNotesParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetReferenceNotesResponse xmlns="http://wcf.dian.colombia">
      <GetReferenceNotesResult xmlns:b="http://schemas.datacontract.org/2004/07/">
        <b:IsValid>true</b:IsValid>
        <b:StatusCode>00</b:StatusCode>
      </GetReferenceNotesResult>
    </GetReferenceNotesResponse>
  </s:Body>
</s:Envelope>
XML;
        $transport = new FakeTransport($xml);
        $client = self::client($transport);

        $got = $client->getReferenceNotes('some-track-id');

        self::assertTrue($got->isValid);
        self::assertStringContainsString('<wcf:GetReferenceNotes>', $transport->sentBody);
    }

    public function testGetExchangeEmailsParsesResponse(): void
    {
        $csvPayload = base64_encode("email\nfacturacion@example.com\n");
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetExchangeEmailsResponse xmlns="http://wcf.dian.colombia">
      <GetExchangeEmailsResult xmlns:b="http://schemas.datacontract.org/2004/07/">
        <b:CsvBase64Bytes>{$csvPayload}</b:CsvBase64Bytes>
        <b:Message>OK</b:Message>
        <b:StatusCode>0</b:StatusCode>
        <b:Success>true</b:Success>
      </GetExchangeEmailsResult>
    </GetExchangeEmailsResponse>
  </s:Body>
</s:Envelope>
XML;
        $transport = new FakeTransport($xml);
        $client = self::client($transport);

        $got = $client->getExchangeEmails();

        self::assertTrue($got->success);
        self::assertSame("email\nfacturacion@example.com\n", $got->csvBytes);
        self::assertStringContainsString('<wcf:GetExchangeEmails/>', $transport->sentBody);
    }

    public function testGetDocumentInfoParsesNestedStructure(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetDocumentInfoResponse xmlns="http://wcf.dian.colombia">
      <GetDocumentInfoResult xmlns:b="http://schemas.datacontract.org/2004/07/DocumentInfoResponse">
        <b:StatusCode>00</b:StatusCode>
        <b:StatusDescription>OK</b:StatusDescription>
        <b:DocumentInfo>
          <b:Documento>
            <b:DocumentCode>SETP990000001</b:DocumentCode>
            <b:DocumentTypeId>01</b:DocumentTypeId>
            <b:UUID>abc123</b:UUID>
            <b:Emisor>
              <b:Nombre>MI EMPRESA S.A.S.</b:Nombre>
              <b:NumeroDoc>900123456</b:NumeroDoc>
            </b:Emisor>
            <b:Estado>
              <b:KeyValueOfintstring>
                <b:Key>1</b:Key>
                <b:Value>Autorizado</b:Value>
              </b:KeyValueOfintstring>
            </b:Estado>
            <b:TotalEImpuestos>
              <b:Iva>19000.5</b:Iva>
              <b:Total>119000.5</b:Total>
            </b:TotalEImpuestos>
          </b:Documento>
        </b:DocumentInfo>
      </GetDocumentInfoResult>
    </GetDocumentInfoResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->getDocumentInfo('abc123');

        self::assertSame('00', $got->statusCode);
        self::assertCount(1, $got->documentInfo);
        $doc = $got->documentInfo[0];
        self::assertSame('SETP990000001', $doc->documentCode);
        self::assertSame('MI EMPRESA S.A.S.', $doc->emisor->nombre);
        self::assertCount(1, $doc->estado);
        self::assertSame(1, $doc->estado[0]->key);
        self::assertSame('Autorizado', $doc->estado[0]->value);
        self::assertSame(19000.5, $doc->totalEImpuestos->iva);
        // A document with no notes/events/references leaves those arrays empty rather than
        // throwing — the same zero-value tolerance Go's encoding/xml gives for free.
        self::assertSame([], $doc->documentTags);
        self::assertSame([], $doc->eventos);
    }
}
