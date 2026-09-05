<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Signer\Credentials;
use Cofacture\Soap\Internal\Envelope;
use Cofacture\Soap\Internal\Operations;
use Cofacture\Soap\Internal\StreamTransport;
use Cofacture\Soap\Internal\Transport;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * SOAP 1.2 + WS-Security client for DIAN's WcfDianCustomerServices (habilitación and
 * producción). Mirrors soap/client.go + the call() logic of soap/envelope.go.
 *
 * Does not require mutual TLS (the WSDL's real policy has RequireClientCertificate="false") —
 * $credentials is used only to sign the wsa:To WS-Security header (Envelope) and to embed the
 * certificate as a wsse:BinarySecurityToken, not for the transport connection itself.
 */
final class Client
{
    use Operations;

    /** Verified against the downloaded WSDL directly, not inferred from the technical annex. */
    public const HABILITACION_URL = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
    public const PRODUCCION_URL = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc';

    private const NS_SOAP12 = 'http://www.w3.org/2003/05/soap-envelope';

    private readonly Transport $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly Credentials $credentials,
        ?Transport $transport = null,
        private readonly int $timeoutSeconds = 60,
    ) {
        $this->transport = $transport ?? new StreamTransport();
    }

    /**
     * Sends $action with the body $bodyBuilder constructs, and returns the DOMElement holding
     * the raw "{$action}Result" content (already confirmed not to be a soap:Fault). $bodyBuilder
     * receives the empty soap:Body element to append the operation's own wcf:{$action} element
     * into.
     *
     * @param callable(DOMElement):void $bodyBuilder
     */
    public function call(string $action, callable $bodyBuilder): DOMElement
    {
        $doc = Envelope::build($this->baseUrl, $this->credentials, $action, $bodyBuilder);
        $reqXml = $doc->saveXML();
        if ($reqXml === false) {
            throw new RuntimeException('soap: serialize request');
        }

        $contentType = sprintf('application/soap+xml; charset=utf-8; action="%s%s"', Envelope::ACTION_BASE, $action);
        $response = $this->transport->send($this->baseUrl, $reqXml, $contentType, $this->timeoutSeconds);

        $respDoc = new DOMDocument();
        $priorSetting = libxml_use_internal_errors(true);
        $loaded = $respDoc->loadXML($response->body);
        libxml_use_internal_errors($priorSetting);
        if (!$loaded) {
            throw new RuntimeException("soap: parse response (HTTP {$response->statusCode}, " . strlen($response->body) . " bytes):\n{$response->body}");
        }

        $xpath = new DOMXPath($respDoc);

        // DIAN's response elements come back with varying, sometimes inconsistent namespace
        // prefixes per operation (b:, c:, i:, ...) — matched by local-name() throughout, the
        // same effective behavior as Go's encoding/xml with an unqualified struct tag (see
        // Internal\XmlReader's doc comment).
        $faultNode = $xpath->query("//*[local-name()='Body']/*[local-name()='Fault']")->item(0);
        if ($faultNode instanceof DOMElement) {
            $code = (string) $xpath->evaluate("string(.//*[local-name()='Code']/*[local-name()='Value'])", $faultNode);
            $reason = (string) $xpath->evaluate("string(.//*[local-name()='Reason']/*[local-name()='Text'])", $faultNode);
            throw new Fault($code, $reason);
        }

        // The HTTP status is NOT a reliable success/error signal for this service — confirmed
        // against the real DIAN environment: GetAcquirer responds with HTTP 404 and a perfectly
        // valid SOAP body when the acquirer doesn't exist (StatusCode "404" INSIDE the body, no
        // soap:Fault) — that is the normal, expected result for most identification numbers, not
        // a transport error. That's why the body is inspected for the expected result element
        // FIRST, regardless of status; the status is only used to give a clearer error message
        // if that element isn't present.
        $resultNode = $xpath->query("//*[local-name()='{$action}Result']")->item(0);
        if (!($resultNode instanceof DOMElement)) {
            if ($response->statusCode !== 200) {
                throw new RuntimeException("soap: HTTP {$response->statusCode} with no explicit soap:Fault:\n{$response->body}");
            }
            throw new RuntimeException("soap: response missing {$action}Result:\n{$response->body}");
        }
        return $resultNode;
    }
}
