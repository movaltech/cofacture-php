<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit\Support;

/** Test-only self-signed certificate generation, shared by every test that needs Credentials
 *  without a real DIAN-issued .p12. */
final class SelfSignedCert
{
    private function __construct()
    {
    }

    /** @return array{0: string, 1: string} [certPem, keyPem] */
    public static function generate(): array
    {
        // Windows PHP builds don't ship a default openssl.cnf path in php.ini, so openssl_*
        // calls silently fail (return false) unless a config file is passed explicitly here.
        // This is purely a test-environment concern — production certificates are always loaded
        // from a real .p12/.pem via CertificateLoader, never generated like this.
        $opensslConfig = self::opensslConfig();

        $keyPair = openssl_pkey_new($opensslConfig + [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'cofacture-php test'], $keyPair, $opensslConfig);
        $cert = openssl_csr_sign($csr, null, $keyPair, 365, $opensslConfig);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($keyPair, $keyPem, null, $opensslConfig);

        return [$certPem, $keyPem];
    }

    /** @return array<string,string> */
    private static function opensslConfig(): array
    {
        $winPath = 'C:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\extras\\ssl\\openssl.cnf';
        return is_file($winPath) ? ['config' => $winPath] : [];
    }
}
