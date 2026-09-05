<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Xml;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Namespace-aware element factory used by every XML-building file in this port — UBL/XAdES
 * documents (builder/signer) and the SOAP/WS-Security envelope (soap) alike.
 *
 * PHP's DOMDocument::createElement() with a prefixed tag name (e.g. "cac:ID") does NOT create
 * a genuinely namespace-aware node — DOMElement::$localName ends up being the whole string
 * "cac:ID" and $namespaceURI stays null. That's harmless for simple serialization, but it
 * silently breaks two things this port depends on: DOMXPath queries with a registered
 * namespace, and — critically — DOMElement::C14N() on a sub-element (as opposed to the whole
 * document), which needs genuine namespace membership to correctly reconstruct the in-scope
 * xmlns declarations for that subtree. Confirmed by direct experiment: canonicalizing a
 * plain-createElement "cbc:ID" node in isolation silently drops its own xmlns:cbc declaration,
 * which would produce a wrong digest for ds:Reference elements pointing at ds:KeyInfo or
 * xades:SignedProperties. createElementNS() does not have this problem and was verified (via
 * the same experiment) to reconstruct the correct in-scope namespaces on isolated
 * canonicalization — see tests/Unit/SignedInvoicePipelineTest.php, which independently
 * re-verifies a real signature end to end specifically to catch a regression here. The SOAP
 * envelope's WS-Security signature (soap/Internal/Envelope.php) needs the exact same guarantee
 * for its own isolated-element canonicalization (wsa:To, ds:SignedInfo, both under exclusive
 * C14N instead of XAdES's inclusive C14N — see that file's doc comment for why they differ).
 */
final class El
{
    private const PREFIX_NS = [
        'cac' => Namespaces::NS_CAC,
        'cbc' => Namespaces::NS_CBC,
        'ext' => Namespaces::NS_EXT,
        'sts' => Namespaces::NS_STS,
        'ds' => Namespaces::NS_DS,
        'xades' => Namespaces::NS_XADES,
        'xades141' => Namespaces::NS_XADES141,
        // SOAP 1.2 + WS-Security envelope (soap/Internal/Envelope.php) — not a UBL namespace,
        // grouped here anyway since this is the one namespace-aware element factory in the port.
        'soap' => 'http://www.w3.org/2003/05/soap-envelope',
        'wcf' => 'http://wcf.dian.colombia',
        'wsa' => 'http://www.w3.org/2005/08/addressing',
        'wsse' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
        'wsu' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
        'ec' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
    ];

    private function __construct()
    {
    }

    /** Creates a namespace-aware element from a prefixed name like "cac:ID". */
    public static function create(DOMDocument $doc, string $qualifiedName, ?string $value = null): DOMElement
    {
        $prefix = strstr($qualifiedName, ':', true);
        if ($prefix === false) {
            throw new InvalidArgumentException("El::create expects a prefixed name like 'cac:ID', got '{$qualifiedName}'");
        }
        $nsUri = self::PREFIX_NS[$prefix]
            ?? throw new InvalidArgumentException("El::create: no namespace registered for prefix '{$prefix}'");

        return $value === null
            ? $doc->createElementNS($nsUri, $qualifiedName)
            : $doc->createElementNS($nsUri, $qualifiedName, $value);
    }

    /**
     * Declares xmlns:$prefix = $uri as a genuine namespace declaration node (not a plain string
     * attribute that happens to be named "xmlns:...") — the distinction matters: only a real
     * declaration is correctly inherited by descendant createElementNS() calls during
     * canonicalization. Pass an empty $prefix to declare the default (unprefixed) namespace.
     */
    public static function declareNamespace(DOMElement $element, string $prefix, string $uri): void
    {
        $name = $prefix === '' ? 'xmlns' : 'xmlns:' . $prefix;
        $element->setAttributeNS('http://www.w3.org/2000/xmlns/', $name, $uri);
    }
}
