<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Signer;

use Cofacture\Internal\UuidV4;
use Cofacture\Signer\Internal\XadesProperties;
use Cofacture\Xml\El;
use DOMElement;
use RuntimeException;

/**
 * Signs UBL documents with XAdES-EPES. Mirrors signer/signer.go.
 *
 * The structure (three ds:Reference elements — document, KeyInfo and SignedProperties — plus
 * DIAN's fixed signature policy) mirrors the Go original, whose structure was verified
 * byte-for-byte against two real, accepted electronic invoices. This PHP port has NOT yet been
 * independently re-verified against a real DIAN submission — see the MVP caveat in the
 * project README.
 */
final class Signer
{
    private const SIGNATURE_METHOD_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const ENVELOPED_SIGNATURE_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const SIGNED_PROPERTIES_REF_TYPE = 'http://uri.etsi.org/01903#SignedProperties';

    public function __construct(private readonly Credentials $credentials)
    {
    }

    /**
     * Signs $root by inserting an XAdES-EPES ds:Signature inside $placeholder.
     *
     * $placeholder must be an empty ext:ExtensionContent already located in its final position
     * within the document (the last ext:UBLExtension of ext:UBLExtensions, exactly as
     * InvoiceBuilder::build() leaves it) — the signature needs to be in its final position for
     * inclusive canonicalization (C14N 1.0 REC, via DOMDocument::C14N(false, ...)) to correctly
     * inherit the document's namespaces. $root is the document's root element
     * (Invoice/CreditNote/DebitNote/AttachedDocument); its digest (ds:Reference URI="") is
     * computed before the tree is touched further.
     *
     * $role is the value of xades:ClaimedRole: "supplier" for Invoice/CreditNote/DebitNote, ""
     * for the AttachedDocument's own signature.
     */
    public function sign(DOMElement $root, DOMElement $placeholder, string $role, \DateTimeImmutable $signingTime): void
    {
        $doc = $root->ownerDocument;
        $docDigest = $this->digestValue($root);

        $id = 'xmldsig-' . UuidV4::generate();
        $sigEl = El::create($doc, 'ds:Signature');
        $sigEl->setAttribute('Id', $id);
        $placeholder->appendChild($sigEl);

        $keyInfoEl = XadesProperties::buildKeyInfo($sigEl, $id, $this->credentials);
        $keyInfoDigest = $this->digestValue($keyInfoEl);

        $signedPropsEl = XadesProperties::buildSignedProperties($sigEl, $id, $role, $signingTime, $this->credentials);
        $signedPropsDigest = $this->digestValue($signedPropsEl);

        $signedInfoEl = $this->buildSignedInfo($doc, $id, $docDigest, $keyInfoDigest, $signedPropsDigest);
        // Schema order: SignedInfo, SignatureValue, KeyInfo, Object — insert SignedInfo first
        // (before whatever is currently the first child, i.e. KeyInfo).
        $sigEl->insertBefore($signedInfoEl, $sigEl->firstChild);

        // openssl_sign() hashes internally when given OPENSSL_ALGO_SHA256 — unlike the Go
        // original, which hashes explicitly (crypto/sha256) before calling rsa.SignPKCS1v15,
        // there's no separate digest step to write out here; both end up signing the same
        // SHA-256(canonicalized SignedInfo).
        $canonSignedInfo = $signedInfoEl->C14N(false, false);
        openssl_sign($canonSignedInfo, $signature, $this->credentials->key, OPENSSL_ALGO_SHA256);

        $sigValueEl = El::create($doc, 'ds:SignatureValue', base64_encode($signature));
        $sigValueEl->setAttribute('Id', $id . '-sigvalue');
        // Insert right before KeyInfo, which is now the second child (after SignedInfo).
        $sigEl->insertBefore($sigValueEl, $keyInfoEl);
    }

    private function buildSignedInfo(
        \DOMDocument $doc,
        string $id,
        string $docDigest,
        string $keyInfoDigest,
        string $signedPropsDigest,
    ): DOMElement {
        $signedInfo = El::create($doc, 'ds:SignedInfo');

        $canonMethod = El::create($doc, 'ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);

        $sigMethod = El::create($doc, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::SIGNATURE_METHOD_RSA_SHA256);
        $signedInfo->appendChild($sigMethod);

        $ref0 = El::create($doc, 'ds:Reference');
        $ref0->setAttribute('Id', $id . '-ref0');
        $ref0->setAttribute('URI', '');
        $transforms = El::create($doc, 'ds:Transforms');
        $ref0->appendChild($transforms);
        $transform = El::create($doc, 'ds:Transform');
        $transform->setAttribute('Algorithm', self::ENVELOPED_SIGNATURE_ALGORITHM);
        $transforms->appendChild($transform);
        $this->appendDigest($doc, $ref0, $docDigest);
        $signedInfo->appendChild($ref0);

        $ref1 = El::create($doc, 'ds:Reference');
        $ref1->setAttribute('Id', $id . '-ref1');
        $ref1->setAttribute('URI', '#' . $id . '-keyinfo');
        $this->appendDigest($doc, $ref1, $keyInfoDigest);
        $signedInfo->appendChild($ref1);

        $ref2 = El::create($doc, 'ds:Reference');
        $ref2->setAttribute('Type', self::SIGNED_PROPERTIES_REF_TYPE);
        $ref2->setAttribute('URI', '#' . $id . '-signedprops');
        $this->appendDigest($doc, $ref2, $signedPropsDigest);
        $signedInfo->appendChild($ref2);

        return $signedInfo;
    }

    private function appendDigest(\DOMDocument $doc, DOMElement $reference, string $digestValueBase64): void
    {
        $digestMethod = El::create($doc, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', XadesProperties::DIGEST_METHOD_SHA256);
        $reference->appendChild($digestMethod);
        $reference->appendChild(El::create($doc, 'ds:DigestValue', $digestValueBase64));
    }

    /**
     * Canonicalizes $element (already positioned in the tree) and returns its SHA-256 in
     * base64, ready for a ds:DigestValue.
     */
    private function digestValue(DOMElement $element): string
    {
        $canon = $element->C14N(false, false);
        if ($canon === false) {
            throw new RuntimeException('signer: C14N canonicalization failed');
        }
        return base64_encode(hash('sha256', $canon, true));
    }

}
