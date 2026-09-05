<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Signer;

use RuntimeException;

/**
 * Loads a certificate and its private key from PEM or PKCS#12 (.p12/.pfx), exactly as issued
 * by the certificate authority. Mirrors signer/certificate.go's LoadPEM/LoadPKCS12.
 */
final class CertificateLoader
{
    private function __construct()
    {
    }

    public static function loadPem(string $certPem, string $keyPem): Credentials
    {
        $cert = openssl_x509_read($certPem);
        if ($cert === false) {
            throw new RuntimeException('signer: could not parse the PEM certificate: ' . openssl_error_string());
        }
        $key = openssl_pkey_get_private($keyPem);
        if ($key === false) {
            throw new RuntimeException('signer: could not parse the PEM private key: ' . openssl_error_string());
        }
        return self::buildCredentials($cert, $key);
    }

    public static function loadPkcs12(string $pkcs12Data, string $password): Credentials
    {
        if (!openssl_pkcs12_read($pkcs12Data, $parsed, $password)) {
            throw new RuntimeException('signer: could not read the .p12 file: ' . openssl_error_string());
        }
        $cert = openssl_x509_read($parsed['cert']);
        if ($cert === false) {
            throw new RuntimeException('signer: .p12 certificate did not parse: ' . openssl_error_string());
        }
        $key = openssl_pkey_get_private($parsed['pkey']);
        if ($key === false) {
            throw new RuntimeException('signer: .p12 private key did not parse: ' . openssl_error_string());
        }
        return self::buildCredentials($cert, $key);
    }

    private static function buildCredentials(\OpenSSLCertificate $cert, \OpenSSLAsymmetricKey $key): Credentials
    {
        openssl_x509_export($cert, $certPem);
        $derBase64 = self::stripPemArmor($certPem);

        $parsed = openssl_x509_parse($cert);
        if ($parsed === false) {
            throw new RuntimeException('signer: could not parse certificate details: ' . openssl_error_string());
        }

        return new Credentials(
            cert: $cert,
            key: $key,
            certDerBase64: $derBase64,
            issuerName: self::formatDn($parsed['issuer'] ?? []),
            serialNumber: (string) ($parsed['serialNumber'] ?? ''),
        );
    }

    /** PEM is just "-----BEGIN...-----" + base64(DER), wrapped at 64 columns, + "-----END...-----". */
    private static function stripPemArmor(string $pem): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($pem));
        $body = array_filter($lines, static fn (string $line) => !str_starts_with($line, '-----'));
        return implode('', $body);
    }

    /**
     * Formats openssl_x509_parse()'s issuer array as an RFC 2253 distinguished name string —
     * what xades:X509IssuerName needs, matching Go's pkix.Name.String() (signer/xades.go).
     *
     * Confirmed byte-for-byte against Go's actual pkix.Name.String() output for a real
     * self-signed certificate with no repeated attribute — the common case, and the one every
     * real DIAN-issued certificate this library has actually seen matches: most-specific
     * attribute first (e.g. "CN=...,OU=IT,O=...,L=...,ST=...,C=CO"), the reverse of the
     * certificate's own ASN.1 order (general-to-specific) and of PHP's un-formatted "issuer="
     * string. The previous version built the DN in the raw ASN.1 order, with no escaping, and
     * silently dropped one of any two same-type attributes (e.g. two OUs) — all three are fixed
     * here.
     *
     * PHP's openssl_x509_parse() does preserve a repeated attribute type (confirmed
     * empirically): 'OU' => ['IT', 'Legal'] instead of overwriting to a single string, in the
     * order it appears in the certificate — flattening that out before reversing recovers the
     * same ordered (type, value) list Go's ASN.1 parser sees, in the overwhelmingly common case
     * where each repeat is its own separate RDN (joined by ',' in the RFC 2253 string).
     *
     * KNOWN REMAINING GAP: X.509 also allows a single RDN to be genuinely multi-valued (one
     * RelativeDistinguishedName SET holding more than one AttributeTypeAndValue) — RFC 2253
     * joins those with '+', not ','. Confirmed by testing: a certificate built with two
     * consecutive "/OU=.../OU=..." in an openssl -subj string produced exactly this structure,
     * and Go's pkix.Name.String() correctly rendered it as "OU=IT+OU=Legal" — but PHP's
     * openssl_x509_parse() collapses both a genuine multi-valued RDN and two separate
     * same-type RDNs into the identical 'OU' => [...] shape, with no way from this API alone to
     * tell which one a given certificate actually has. This code always treats a repeat as
     * separate RDNs (the far more common real-world shape); a certificate using a genuine
     * multi-valued RDN for a repeated attribute would still format differently than Go's here.
     * Re-verify against a real DIAN-issued certificate with a repeated attribute, if one is
     * ever seen, before assuming this handles it.
     *
     * @param array<string, string|array<int, string>> $issuer
     */
    private static function formatDn(array $issuer): string
    {
        $pairs = [];
        foreach ($issuer as $type => $value) {
            foreach ((array) $value as $v) {
                $pairs[] = $type . '=' . self::escapeRdnValue($v);
            }
        }
        return implode(',', array_reverse($pairs));
    }

    /**
     * Escapes one RDN attribute value per RFC 2253 §2.4: a leading '#' or space, a trailing
     * space, and the characters , + " \ < > ; each get a preceding backslash; a null byte
     * becomes the two-hex-digit form \00.
     */
    private static function escapeRdnValue(string $value): string
    {
        $length = strlen($value);
        $escaped = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($char === "\0") {
                $escaped .= '\\00';
                continue;
            }
            if (($i === 0 && ($char === '#' || $char === ' '))
                || ($i === $length - 1 && $char === ' ')
                || str_contains(',+"\\<>;', $char)
            ) {
                $escaped .= '\\' . $char;
                continue;
            }

            $escaped .= $char;
        }

        return $escaped;
    }
}
