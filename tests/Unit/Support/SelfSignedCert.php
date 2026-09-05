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
        return self::generateSignedBy(null, null);
    }

    /**
     * Like generate(), but the certificate is signed by the given CA instead of self-signed —
     * for tests that need a real leaf-signed-by-CA chain (e.g. a .p12 bundling both, the shape
     * real DIAN-issued certificates routinely use). Pass null/null (generate()'s own behavior)
     * for a self-signed certificate.
     *
     * @return array{0: string, 1: string} [certPem, keyPem]
     */
    public static function generateSignedBy(?string $caCertPem, ?string $caKeyPem): array
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

        $caCert = $caCertPem === null ? null : openssl_x509_read($caCertPem);
        $caKey = $caKeyPem === null ? null : openssl_pkey_get_private($caKeyPem);
        $cert = openssl_csr_sign($csr, $caCert, $caKey ?? $keyPair, 365, $opensslConfig);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($keyPair, $keyPem, null, $opensslConfig);

        return [$certPem, $keyPem];
    }

    /**
     * Windows PHP builds don't ship a default openssl.cnf path in php.ini, so openssl_* calls
     * silently fail (return false) unless a config file is passed explicitly. Tried in order,
     * none of them hardcoding a machine- or version-specific path:
     *   1. OPENSSL_CONF — the standard OpenSSL-itself override; if a host already sets this,
     *      respect it instead of guessing.
     *   2. PHP_BINDIR/extras/ssl/openssl.cnf — the layout the official windows.php.net zip
     *      distribution ships (a config bundled next to whichever php.exe is actually running).
     *   3. Any php-*\extras\ssl\openssl.cnf under Laragon's default install root — same bundled
     *      layout Laragon uses, but matched by a glob instead of one pinned PHP version string,
     *      so it keeps working after a Laragon PHP version upgrade.
     *
     * @return array<string,string>
     */
    public static function opensslConfig(): array
    {
        $envConfig = getenv('OPENSSL_CONF');
        if (is_string($envConfig) && $envConfig !== '' && is_file($envConfig)) {
            return ['config' => $envConfig];
        }

        $bundled = PHP_BINDIR . '/extras/ssl/openssl.cnf';
        if (is_file($bundled)) {
            return ['config' => $bundled];
        }

        foreach (glob('C:/laragon/bin/php/php-*/extras/ssl/openssl.cnf') ?: [] as $laragonConfig) {
            return ['config' => $laragonConfig];
        }

        return [];
    }
}
