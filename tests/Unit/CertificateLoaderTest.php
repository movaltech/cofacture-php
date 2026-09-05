<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Signer\CertificateLoader;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Mirrors cofacture/signer/certificate_test.go. CertificateLoader::loadPkcs12()/loadPem() were
 * previously untested in isolation — loadPkcs12() specifically was never called anywhere in
 * this test suite at all; loadPem() was only ever exercised via its happy path inside
 * Signed*PipelineTest's setUpBeforeClass().
 */
final class CertificateLoaderTest extends TestCase
{
    private const TEST_P12_PASSWORD = 'test-password';

    /**
     * Confirms loadPkcs12() accepts a .p12 that bundles a CA certificate alongside the leaf
     * cert + key — the real-world shape DIAN-issued certificates routinely use. Go's LoadPKCS12
     * specifically needed pkcs12.DecodeChain instead of pkcs12.Decode for this exact case (see
     * docs/dian-rejection-log.md history); this test exists so a future PHP regression here is
     * caught the same way Go's is.
     */
    public function testLoadPkcs12WithCaChain(): void
    {
        [$caCertPem, $caKeyPem] = SelfSignedCert::generate();
        [$leafCertPem, $leafKeyPem] = SelfSignedCert::generateSignedBy($caCertPem, $caKeyPem);

        $p12 = self::buildPkcs12($leafCertPem, $leafKeyPem, self::TEST_P12_PASSWORD, [$caCertPem]);

        $credentials = CertificateLoader::loadPkcs12($p12, self::TEST_P12_PASSWORD);

        openssl_x509_export($credentials->cert, $gotCertPem);
        self::assertSame(
            self::normalizePem($leafCertPem),
            self::normalizePem($gotCertPem),
            'loadPkcs12 returned a different certificate than the leaf that was encoded',
        );
    }

    /** Confirms the simple case (leaf cert + key only, no CA bundled) still works. */
    public function testLoadPkcs12NoChain(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $p12 = self::buildPkcs12($certPem, $keyPem, self::TEST_P12_PASSWORD);

        $credentials = CertificateLoader::loadPkcs12($p12, self::TEST_P12_PASSWORD);

        openssl_x509_export($credentials->cert, $gotCertPem);
        self::assertSame(self::normalizePem($certPem), self::normalizePem($gotCertPem));
    }

    /** Confirms a wrong password is reported as an error. */
    public function testLoadPkcs12WrongPassword(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $p12 = self::buildPkcs12($certPem, $keyPem, self::TEST_P12_PASSWORD);

        $this->expectException(RuntimeException::class);
        CertificateLoader::loadPkcs12($p12, 'wrong-password');
    }

    /** Confirms loadPem() reconstructs the same certificate from its PEM-encoded form. */
    public function testLoadPemRoundTrip(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();

        $credentials = CertificateLoader::loadPem($certPem, $keyPem);

        openssl_x509_export($credentials->cert, $gotCertPem);
        self::assertSame(self::normalizePem($certPem), self::normalizePem($gotCertPem));
    }

    /** Confirms a missing/invalid certificate PEM block is reported as an error, not a crash. */
    public function testLoadPemInvalidCertBlock(): void
    {
        [, $keyPem] = SelfSignedCert::generate();

        $this->expectException(RuntimeException::class);
        CertificateLoader::loadPem('not a pem block', $keyPem);
    }

    /** @param string[] $extraCerts */
    private static function buildPkcs12(string $certPem, string $keyPem, string $password, array $extraCerts = []): string
    {
        $args = $extraCerts === [] ? [] : ['extracerts' => $extraCerts];
        if (!openssl_pkcs12_export($certPem, $out, $keyPem, $password, $args)) {
            throw new RuntimeException('failed to build test .p12: ' . openssl_error_string());
        }
        return $out;
    }

    private static function normalizePem(string $pem): string
    {
        return trim($pem);
    }
}
