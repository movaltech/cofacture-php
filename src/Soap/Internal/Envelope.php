<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap\Internal;

use Cofacture\Internal\UuidV4;
use Cofacture\Soap\Credentials;
use Cofacture\Xml\El;
use DOMDocument;
use DOMElement;

/**
 * Builds the SOAP 1.2 envelope + WS-Security header for DIAN's receiving web services
 * (WcfDianCustomerServices). Mirrors soap/envelope.go.
 *
 * The policy published in the WSDL describes RequireThumbprintReference, but that variant is
 * documented (Go original) to have produced "An error occurred when verifying security for the
 * message" against the real server. The pattern that does work (matching existing real-world
 * implementations: Chilkat, PHP's soap-dian) is different:
 *
 *   - An embedded wsse:BinarySecurityToken with a Direct Reference in KeyInfo, not by
 *     thumbprint.
 *   - Exclusive C14N forcing a fixed list of inclusive namespaces "wsa soap wcf" — without
 *     this, the signature doesn't match what the server recomputes when verifying. PHP's
 *     DOMElement::C14N(exclusive: true, ..., nsPrefixes: [...]) implements the same standard
 *     EXC-C14N InclusiveNamespaces mechanism as the Go original's goxmldsig canonicalizer.
 *   - TransportBinding with HttpsToken RequireClientCertificate="false" — plain HTTPS, no
 *     mutual TLS, despite the technical annex (section 7.5) suggesting otherwise.
 *   - Only the wsa:To header is signed (not the Body or the Timestamp).
 *   - AlgorithmSuite Basic256Sha256Rsa15 — SHA-256 digest, RSA-SHA256 signature.
 *   - Strict Layout — inside wsse:Security: Timestamp, BinarySecurityToken, Signature.
 *   - Every element carrying a wsu:Id attribute declares xmlns:wsu on itself (via
 *     setAttributeNS, a genuine namespace declaration — see Xml\El) rather than relying on
 *     inheritance from a sibling, which XML namespaces never provide.
 *
 * This exclusive canonicalizer is intentionally distinct from the inclusive C14N the document's
 * own XAdES signature uses (Cofacture\Signer\Signer) — different signature layers, different
 * algorithms, on purpose.
 */
final class Envelope
{
    private const NS_SOAP12 = 'http://www.w3.org/2003/05/soap-envelope';
    private const NS_WSA = 'http://www.w3.org/2005/08/addressing';
    private const NS_WCF = 'http://wcf.dian.colombia';
    private const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const NS_WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_EC14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    public const ACTION_BASE = self::NS_WCF . '/IWcfDianCustomerServices/';

    private const X509V3_VALUE_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';
    private const BASE64_ENCODING_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    private const SIGNATURE_METHOD_RSA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const DIGEST_METHOD_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    /** Forces these prefixes to be declared in canonical form even where the signed element
     *  doesn't "visibly use" them in the strict sense of the algorithm — exactly what EXC-C14N's
     *  InclusiveNamespaces mechanism exists for, and what real implementations against this same
     *  service use. */
    private const INCLUSIVE_NS_PREFIX_LIST = 'wsa soap wcf';
    private const INCLUSIVE_NS_PREFIXES = ['wsa', 'soap', 'wcf'];

    private function __construct()
    {
    }

    /** @param callable(DOMElement):void $bodyBuilder */
    public static function build(string $baseUrl, Credentials $credentials, string $action, callable $bodyBuilder): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $env = El::create($doc, 'soap:Envelope');
        $doc->appendChild($env);
        El::declareNamespace($env, 'soap', self::NS_SOAP12);
        El::declareNamespace($env, 'wcf', self::NS_WCF);

        $header = El::create($doc, 'soap:Header');
        $env->appendChild($header);

        $actionEl = El::create($doc, 'wsa:Action', self::ACTION_BASE . $action);
        $header->appendChild($actionEl);
        El::declareNamespace($actionEl, 'wsa', self::NS_WSA);

        $toId = '_to';
        $toEl = El::create($doc, 'wsa:To', $baseUrl);
        $header->appendChild($toEl);
        El::declareNamespace($toEl, 'wsa', self::NS_WSA);
        El::declareNamespace($toEl, 'wsu', self::NS_WSU);
        // xmlns:soap and xmlns:wcf are declared here too — redundant for wsa:To's own content,
        // but needed so INCLUSIVE_NS_PREFIXES has something to retain when canonicalizing this
        // element in isolation (see the class doc comment).
        El::declareNamespace($toEl, 'soap', self::NS_SOAP12);
        El::declareNamespace($toEl, 'wcf', self::NS_WCF);
        $toEl->setAttributeNS(self::NS_WSU, 'wsu:Id', $toId);

        $replyTo = El::create($doc, 'wsa:ReplyTo');
        $header->appendChild($replyTo);
        El::declareNamespace($replyTo, 'wsa', self::NS_WSA);
        $replyTo->appendChild(El::create($doc, 'wsa:Address', self::NS_WSA . '/anonymous'));

        $msgId = El::create($doc, 'wsa:MessageID', 'urn:uuid:' . UuidV4::generate());
        $header->appendChild($msgId);
        El::declareNamespace($msgId, 'wsa', self::NS_WSA);

        self::appendSecurityHeader($header, $toEl, $toId, $credentials);

        $body = El::create($doc, 'soap:Body');
        $env->appendChild($body);
        $bodyBuilder($body);

        return $doc;
    }

    private static function appendSecurityHeader(DOMElement $header, DOMElement $toEl, string $toId, Credentials $credentials): void
    {
        $doc = $header->ownerDocument;

        $sec = El::create($doc, 'wsse:Security');
        $header->appendChild($sec);
        El::declareNamespace($sec, 'wsse', self::NS_WSSE);
        $sec->setAttributeNS(self::NS_SOAP12, 'soap:mustUnderstand', '1');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $ts = El::create($doc, 'wsu:Timestamp');
        $sec->appendChild($ts);
        El::declareNamespace($ts, 'wsu', self::NS_WSU);
        $ts->setAttributeNS(self::NS_WSU, 'wsu:Id', '_ts');
        $ts->appendChild(El::create($doc, 'wsu:Created', $now->format('Y-m-d\TH:i:s.v\Z')));
        $ts->appendChild(El::create($doc, 'wsu:Expires', $now->modify('+5 minutes')->format('Y-m-d\TH:i:s.v\Z')));

        $tokenId = 'X509-' . UuidV4::generate();
        $bst = El::create($doc, 'wsse:BinarySecurityToken', $credentials->certificateDerBase64());
        $sec->appendChild($bst);
        // xmlns:wsu must be declared here: it isn't inherited from the sibling Timestamp element
        // (namespace declarations only flow down to descendants, never across siblings). Without
        // this, "wsu:Id" would reference an undeclared prefix and a strict namespace parser
        // (like WCF's) rejects the document before it even processes security.
        El::declareNamespace($bst, 'wsu', self::NS_WSU);
        $bst->setAttributeNS(self::NS_WSU, 'wsu:Id', $tokenId);
        $bst->setAttribute('EncodingType', self::BASE64_ENCODING_TYPE);
        $bst->setAttribute('ValueType', self::X509V3_VALUE_TYPE);

        self::appendSignature($sec, $toEl, $toId, $tokenId, $credentials);
    }

    /** Signs only $toEl (the wsa:To header, via Reference URI="#"+$toId). */
    private static function appendSignature(DOMElement $sec, DOMElement $toEl, string $toId, string $tokenId, Credentials $credentials): void
    {
        $doc = $sec->ownerDocument;

        $canonTo = $toEl->C14N(true, false, null, self::INCLUSIVE_NS_PREFIXES);
        $digestTo = base64_encode(hash('sha256', $canonTo, true));

        $sigEl = El::create($doc, 'ds:Signature');
        $sec->appendChild($sigEl);
        El::declareNamespace($sigEl, 'ds', self::NS_DS);

        $signedInfo = El::create($doc, 'ds:SignedInfo');
        $sigEl->appendChild($signedInfo);
        // The exclusive canonicalizer (unlike the inclusive one Signer uses for XAdES) does not
        // inherit namespaces from ancestors: it treats the canonicalized element as if it were
        // the root. xmlns:ds/soap/wcf must be declared within this subtree.
        El::declareNamespace($signedInfo, 'ds', self::NS_DS);
        El::declareNamespace($signedInfo, 'soap', self::NS_SOAP12);
        El::declareNamespace($signedInfo, 'wcf', self::NS_WCF);

        $canonMethod = El::create($doc, 'ds:CanonicalizationMethod');
        $signedInfo->appendChild($canonMethod);
        $canonMethod->setAttribute('Algorithm', self::NS_EC14N);
        self::appendInclusiveNamespaces($canonMethod);

        $sigMethod = El::create($doc, 'ds:SignatureMethod');
        $signedInfo->appendChild($sigMethod);
        $sigMethod->setAttribute('Algorithm', self::SIGNATURE_METHOD_RSA256);

        $ref = El::create($doc, 'ds:Reference');
        $signedInfo->appendChild($ref);
        $ref->setAttribute('URI', '#' . $toId);
        $transforms = El::create($doc, 'ds:Transforms');
        $ref->appendChild($transforms);
        $transform = El::create($doc, 'ds:Transform');
        $transforms->appendChild($transform);
        $transform->setAttribute('Algorithm', self::NS_EC14N);
        self::appendInclusiveNamespaces($transform);
        $digestMethod = El::create($doc, 'ds:DigestMethod');
        $ref->appendChild($digestMethod);
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD_SHA256);
        $ref->appendChild(El::create($doc, 'ds:DigestValue', $digestTo));

        $canonSignedInfo = $signedInfo->C14N(true, false, null, self::INCLUSIVE_NS_PREFIXES);
        openssl_sign($canonSignedInfo, $signature, $credentials->signingKey(), OPENSSL_ALGO_SHA256);
        $sigEl->appendChild(El::create($doc, 'ds:SignatureValue', base64_encode($signature)));

        $keyInfo = El::create($doc, 'ds:KeyInfo');
        $sigEl->appendChild($keyInfo);
        $str = El::create($doc, 'wsse:SecurityTokenReference');
        $keyInfo->appendChild($str);
        El::declareNamespace($str, 'wsse', self::NS_WSSE);
        $tokenRef = El::create($doc, 'wsse:Reference');
        $str->appendChild($tokenRef);
        $tokenRef->setAttribute('URI', '#' . $tokenId);
        $tokenRef->setAttribute('ValueType', self::X509V3_VALUE_TYPE);
    }

    private static function appendInclusiveNamespaces(DOMElement $transformOrMethod): void
    {
        $doc = $transformOrMethod->ownerDocument;
        $ec = El::create($doc, 'ec:InclusiveNamespaces');
        $transformOrMethod->appendChild($ec);
        El::declareNamespace($ec, 'ec', self::NS_EC14N);
        $ec->setAttribute('PrefixList', self::INCLUSIVE_NS_PREFIX_LIST);
    }

}
