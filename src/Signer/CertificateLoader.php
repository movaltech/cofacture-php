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

    /** @param array<string,string> $issuer */
    private static function formatDn(array $issuer): string
    {
        $parts = [];
        foreach ($issuer as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode(',', $parts);
    }
}
