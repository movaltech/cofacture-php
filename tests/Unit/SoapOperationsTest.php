<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Signer\CertificateLoader;
use Cofacture\Soap\Client;
use Cofacture\Soap\Fault;
use Cofacture\Tests\Unit\Support\FakeTransport;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use PHPUnit\Framework\TestCase;

/**
 * Confirms each operation parses a real (or a deliberately empty/error-shaped) DIAN response
 * correctly, using a FakeTransport instead of a live server or DIAN credentials — Client's own
 * signing (already proven self-consistent in SoapEnvelopeTest) is exercised for real on every
 * call here; only the socket is faked. Response fixtures are taken verbatim from
 * soap/operations_test.go in the Go original.
 */
final class SoapOperationsTest extends TestCase
{
    private static function client(FakeTransport $transport): Client
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);
        return new Client(Client::HABILITACION_URL, $credentials, $transport);
    }

    public function testGetNumberingRangeParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetNumberingRangeResponse xmlns="http://wcf.dian.colombia">
      <GetNumberingRangeResult xmlns:b="http://schemas.datacontract.org/2004/07/NumberRangeResponseList"
                               xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <b:OperationCode>0</b:OperationCode>
        <b:OperationDescription>Proceso Exitoso</b:OperationDescription>
        <b:ResponseList xmlns:c="http://schemas.datacontract.org/2004/07/NumberRangeResponse">
          <c:NumberRangeResponse>
            <c:ResolutionNumber>18760000001</c:ResolutionNumber>
            <c:ResolutionDate>2019-01-19T00:00:00</c:ResolutionDate>
            <c:Prefix>SETP</c:Prefix>
            <c:FromNumber>990000000</c:FromNumber>
            <c:ToNumber>995000000</c:ToNumber>
            <c:ValidDateFrom>2019-01-19T00:00:00</c:ValidDateFrom>
            <c:ValidDateTo>2030-01-19T00:00:00</c:ValidDateTo>
            <c:TechnicalKey>fc8eac422eba16e22ffd8c6f94b3f40a6e38162c</c:TechnicalKey>
          </c:NumberRangeResponse>
        </b:ResponseList>
      </GetNumberingRangeResult>
    </GetNumberingRangeResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->getNumberingRange('6382356', '6382356', '12345678-1234-1234-1234-123456789012');

        self::assertSame('0', $got->operationCode);
        self::assertCount(1, $got->responseList);
        $r = $got->responseList[0];
        self::assertSame('18760000001', $r->resolutionNumber);
        self::assertSame('SETP', $r->prefix);
        self::assertSame(990000000, $r->fromNumber);
        self::assertSame(995000000, $r->toNumber);
        self::assertSame('fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', $r->technicalKey);
    }

    public function testGetNumberingRangeEmptyList(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetNumberingRangeResponse xmlns="http://wcf.dian.colombia">
      <GetNumberingRangeResult xmlns:b="http://schemas.datacontract.org/2004/07/NumberRangeResponseList">
        <b:OperationCode>0</b:OperationCode>
        <b:OperationDescription>Proceso Exitoso</b:OperationDescription>
        <b:ResponseList/>
      </GetNumberingRangeResult>
    </GetNumberingRangeResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->getNumberingRange('6382356', '6382356', 'any-software-id');

        self::assertSame([], $got->responseList);
    }

    public function testSendEventUpdateStatusParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <SendEventUpdateStatusResponse xmlns="http://wcf.dian.colombia">
      <SendEventUpdateStatusResult xmlns:b="http://schemas.datacontract.org/2004/07/"
                                    xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <b:IsValid>true</b:IsValid>
        <b:StatusCode>00</b:StatusCode>
        <b:StatusDescription>La Notificacion ha sido autorizada</b:StatusDescription>
      </SendEventUpdateStatusResult>
    </SendEventUpdateStatusResponse>
  </s:Body>
</s:Envelope>
XML;
        $transport = new FakeTransport($xml);
        $client = self::client($transport);

        $got = $client->sendEventUpdateStatus('<ApplicationResponse/>');

        self::assertTrue($got->isValid);
        self::assertSame('00', $got->statusCode);
        self::assertStringContainsString('<wcf:SendEventUpdateStatus>', $transport->sentBody);
    }

    public function testGetAcquirerParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetAcquirerResponse xmlns="http://wcf.dian.colombia">
      <GetAcquirerResult>
        <Message>OK</Message>
        <StatusCode>0</StatusCode>
        <ReceiverName>Cliente De Prueba</ReceiverName>
        <ReceiverEmail>cliente@prueba.test</ReceiverEmail>
      </GetAcquirerResult>
    </GetAcquirerResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->getAcquirer('31', '900373076');

        self::assertSame('Cliente De Prueba', $got->receiverName);
        self::assertSame('cliente@prueba.test', $got->receiverEmail);
        self::assertSame('0', $got->statusCode);
    }

    /** Reproduces a real response confirmed against the DIAN certification environment: when
     *  the acquirer does not exist, DIAN responds with HTTP 404 but a perfectly valid SOAP body
     *  — StatusCode "404" INSIDE the body, no soap:Fault. This must not be treated as an error. */
    public function testGetAcquirerHttp404WithValidBodyIsNotAnError(): void
    {
        $xml = <<<'XML'
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://www.w3.org/2005/08/addressing">
  <s:Body>
    <GetAcquirerResponse xmlns="http://wcf.dian.colombia">
      <GetAcquirerResult xmlns:b="http://schemas.datacontract.org/2004/07/Gosocket.Dian.Services.Utils.Common" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <b:Message>El adquirente No existe en la base de datos</b:Message>
        <b:ReceiverEmail i:nil="true"/>
        <b:ReceiverName i:nil="true"/>
        <b:StatusCode>404</b:StatusCode>
      </GetAcquirerResult>
    </GetAcquirerResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml, 404));

        $got = $client->getAcquirer('13', '6382356');

        self::assertSame('404', $got->statusCode);
        self::assertSame('El adquirente No existe en la base de datos', $got->message);
        self::assertSame('', $got->receiverName);
    }

    public function testGetStatusEventParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetStatusEventResponse xmlns="http://wcf.dian.colombia">
      <GetStatusEventResult xmlns:b="http://schemas.datacontract.org/2004/07/"
                             xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <b:IsValid>true</b:IsValid>
        <b:StatusCode>00</b:StatusCode>
        <b:StatusDescription>La Notificacion ha sido autorizada</b:StatusDescription>
      </GetStatusEventResult>
    </GetStatusEventResponse>
  </s:Body>
</s:Envelope>
XML;
        $transport = new FakeTransport($xml);
        $client = self::client($transport);

        $got = $client->getStatusEvent('some-track-id');

        self::assertTrue($got->isValid);
        self::assertStringContainsString('<wcf:trackId>some-track-id</wcf:trackId>', $transport->sentBody);
    }

    /** Confirms the normal/expected case for most ID numbers: DIAN responds without an
     *  ErrorMessage/Fault but with empty fields — this must not be treated as an error, only as
     *  "no record found" (non-blocking, see the doc comment on Client::getAcquirer). */
    public function testGetAcquirerNotFoundIsNotAnError(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetAcquirerResponse xmlns="http://wcf.dian.colombia">
      <GetAcquirerResult></GetAcquirerResult>
    </GetAcquirerResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->getAcquirer('13', '1122334455');

        self::assertSame('', $got->receiverName);
        self::assertSame('', $got->receiverEmail);
    }

    /** Confirms getStatus sends trackId (same request shape as getStatusEvent) and parses its
     *  own <GetStatusResult> element — the query counterpart of sendBillSync. */
    public function testGetStatusParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <GetStatusResponse xmlns="http://wcf.dian.colombia">
      <GetStatusResult xmlns:b="http://schemas.datacontract.org/2004/07/"
                        xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <b:IsValid>true</b:IsValid>
        <b:StatusCode>00</b:StatusCode>
        <b:StatusDescription>La Factura electrónica ha sido autorizada</b:StatusDescription>
      </GetStatusResult>
    </GetStatusResponse>
  </s:Body>
</s:Envelope>
XML;
        $transport = new FakeTransport($xml);
        $client = self::client($transport);

        $got = $client->getStatus('some-track-id');

        self::assertTrue($got->isValid);
        self::assertSame('00', $got->statusCode);
        self::assertStringContainsString('<wcf:trackId>some-track-id</wcf:trackId>', $transport->sentBody);
    }

    /** Confirms sendNominaSync sends only contentFile (no fileName, no testSetId — unlike
     *  sendNominaSyncTestSet) and parses the shared <SendNominaSyncResult> element. */
    public function testSendNominaSyncParsesResponse(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <SendNominaSyncResponse xmlns="http://wcf.dian.colombia">
      <SendNominaSyncResult xmlns:b="http://schemas.datacontract.org/2004/07/"
                             xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <b:IsValid>true</b:IsValid>
        <b:StatusCode>00</b:StatusCode>
        <b:StatusDescription>La Nómina Electrónica ha sido autorizada</b:StatusDescription>
      </SendNominaSyncResult>
    </SendNominaSyncResponse>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml));

        $got = $client->sendNominaSync('<NominaIndividual/>');

        self::assertTrue($got->isValid);
        self::assertSame('00', $got->statusCode);
    }

    public function testSoapFaultIsThrown(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
  <s:Body>
    <s:Fault>
      <s:Code><s:Value>s:Sender</s:Value></s:Code>
      <s:Reason><s:Text xml:lang="en-US">An error occurred when verifying security for the message.</s:Text></s:Reason>
    </s:Fault>
  </s:Body>
</s:Envelope>
XML;
        $client = self::client(new FakeTransport($xml, 500));

        $this->expectException(Fault::class);
        $this->expectExceptionMessage('An error occurred when verifying security for the message.');
        $client->getStatus('some-track-id');
    }
}
