<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Signer;

use Cofacture\Soap\Credentials as SoapCredentials;

/**
 * A loaded certificate + private key, plus the pieces of the certificate signer.php needs
 * repeatedly (raw DER for ds:X509Certificate, issuer DN and serial number for
 * xades:IssuerSerial) pre-extracted so Signer doesn't re-parse the certificate on every call.
 *
 * Also implements Soap\Credentials — Soap needs a certificate + key too (to sign its own
 * WS-Security header, a different signature than the XAdES one Signer applies to the
 * document), but Soap owns that narrower interface itself and never imports this class
 * directly; this is the only place that points the other way.
 */
final class Credentials implements SoapCredentials
{
    public function __construct(
        public readonly \OpenSSLCertificate $cert,
        public readonly \OpenSSLAsymmetricKey $key,
        /** Base64-encoded DER, ready for ds:X509Certificate — this is just the PEM body with
         *  the "-----BEGIN/END CERTIFICATE-----" armor stripped, since PEM already is base64(DER). */
        public readonly string $certDerBase64,
        /**
         * RFC-2253-ish issuer DN string for xades:X509IssuerName. NOTE: unlike the rest of this
         * port, the exact component order/escaping here has not been cross-checked against a
         * real DIAN-accepted document yet — see the Go original's signer/xades.go, which uses
         * Go's x509.Certificate.Issuer.String() (also RFC 2253) and was verified. Confirm this
         * formatter produces an equivalent string before relying on it for a real submission.
         */
        public readonly string $issuerName,
        public readonly string $serialNumber,
    ) {
    }

    public function certificateDerBase64(): string
    {
        return $this->certDerBase64;
    }

    public function signingKey(): \OpenSSLAsymmetricKey
    {
        return $this->key;
    }
}
