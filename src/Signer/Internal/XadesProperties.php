<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Signer\Internal;

use Cofacture\Signer\Credentials;
use Cofacture\Xml\El;
use DOMElement;

/**
 * Mirrors signer/xades.go — builds the ds:KeyInfo and xades:SignedProperties blocks of the
 * signature. DIAN's signature policy (URL + hash) is unique and fixed for all electronic
 * documents, confirmed byte-for-byte against the Go original (which itself was confirmed
 * against two real, accepted electronic invoices) — see Cofacture\Cufe\Cufe's doc comment for
 * the general cross-language verification approach this port follows.
 */
final class XadesProperties
{
    public const POLICY_URL = 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf';
    public const POLICY_DESCRIPTION = 'Política de firma para facturas electrónicas de la República de Colombia.';
    public const POLICY_HASH_SHA256_B64 = 'dMoMvtcG5aIzgYo0tIsSQeVJBDnUnfSOfBpxXrmor0Y=';

    public const DIGEST_METHOD_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    private function __construct()
    {
    }

    /**
     * Appends ds:KeyInfo (with the X.509 certificate in DER+base64) as a child of $parent and
     * returns it. $parent must already be inserted in its final position within the document
     * so canonicalization inherits the correct namespace context.
     */
    public static function buildKeyInfo(DOMElement $parent, string $id, Credentials $credentials): DOMElement
    {
        $doc = $parent->ownerDocument;
        $keyInfo = El::create($doc, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $id . '-keyinfo');
        $x509Data = El::create($doc, 'ds:X509Data');
        $x509Certificate = El::create($doc, 'ds:X509Certificate', $credentials->certDerBase64);
        $x509Data->appendChild($x509Certificate);
        $keyInfo->appendChild($x509Data);
        $parent->appendChild($keyInfo);
        return $keyInfo;
    }

    /**
     * Appends ds:Object/xades:QualifyingProperties/xades:SignedProperties as a child of $parent
     * and returns the SignedProperties element. $role is the value of xades:ClaimedRole
     * ("supplier" for Invoice/CreditNote/DebitNote, "" for AttachedDocument).
     */
    public static function buildSignedProperties(
        DOMElement $parent,
        string $id,
        string $role,
        \DateTimeImmutable $signingTime,
        Credentials $credentials,
    ): DOMElement {
        $doc = $parent->ownerDocument;

        $object = El::create($doc, 'ds:Object');
        $qualifying = El::create($doc, 'xades:QualifyingProperties');
        $qualifying->setAttribute('Target', '#' . $id);
        $object->appendChild($qualifying);

        $signedProps = El::create($doc, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $id . '-signedprops');
        $qualifying->appendChild($signedProps);

        $ssp = El::create($doc, 'xades:SignedSignatureProperties');
        $signedProps->appendChild($ssp);
        $ssp->appendChild(El::create($doc, 'xades:SigningTime', $signingTime->format(\DateTimeInterface::ATOM)));

        $certDigest = base64_encode(hash('sha256', self::exportCertDer($credentials), true));

        $signingCertificate = El::create($doc, 'xades:SigningCertificate');
        $ssp->appendChild($signingCertificate);
        $certEl = El::create($doc, 'xades:Cert');
        $signingCertificate->appendChild($certEl);
        $digestEl = El::create($doc, 'xades:CertDigest');
        $certEl->appendChild($digestEl);
        $digestMethod = El::create($doc, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD_SHA256);
        $digestEl->appendChild($digestMethod);
        $digestEl->appendChild(El::create($doc, 'ds:DigestValue', $certDigest));

        $issuerSerial = El::create($doc, 'xades:IssuerSerial');
        $certEl->appendChild($issuerSerial);
        $issuerSerial->appendChild(El::create($doc, 'ds:X509IssuerName', $credentials->issuerName));
        $issuerSerial->appendChild(El::create($doc, 'ds:X509SerialNumber', $credentials->serialNumber));

        $policyIdentifier = El::create($doc, 'xades:SignaturePolicyIdentifier');
        $ssp->appendChild($policyIdentifier);
        $policyId = El::create($doc, 'xades:SignaturePolicyId');
        $policyIdentifier->appendChild($policyId);
        $sigPolicyId = El::create($doc, 'xades:SigPolicyId');
        $policyId->appendChild($sigPolicyId);
        $sigPolicyId->appendChild(El::create($doc, 'xades:Identifier', self::POLICY_URL));
        $sigPolicyId->appendChild(El::create($doc, 'xades:Description', self::POLICY_DESCRIPTION));

        $policyHash = El::create($doc, 'xades:SigPolicyHash');
        $policyId->appendChild($policyHash);
        $policyDigestMethod = El::create($doc, 'ds:DigestMethod');
        $policyDigestMethod->setAttribute('Algorithm', self::DIGEST_METHOD_SHA256);
        $policyHash->appendChild($policyDigestMethod);
        $policyHash->appendChild(El::create($doc, 'ds:DigestValue', self::POLICY_HASH_SHA256_B64));

        if ($role !== '') {
            $signerRole = El::create($doc, 'xades:SignerRole');
            $ssp->appendChild($signerRole);
            $claimedRoles = El::create($doc, 'xades:ClaimedRoles');
            $signerRole->appendChild($claimedRoles);
            $claimedRoles->appendChild(El::create($doc, 'xades:ClaimedRole', $role));
        }

        $parent->appendChild($object);
        return $signedProps;
    }

    private static function exportCertDer(Credentials $credentials): string
    {
        return base64_decode($credentials->certDerBase64);
    }
}
