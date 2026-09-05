<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\SignaturePlaceholder;
use Cofacture\Domain\Environment;
use Cofacture\Payroll\Builder;
use Cofacture\Payroll\Cune;
use Cofacture\Qr\Qr;
use Cofacture\SecurityCode\SecurityCode;
use Cofacture\Signer\CertificateLoader;
use Cofacture\Signer\Signer;
use Cofacture\Tests\Unit\Support\SelfSignedCert;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of the Payroll pipeline (domain -> build CUNE -> build XML -> sign -> verify),
 * reusing PayrollBuilderTest::validNomina() as the fixture. Confirms Builder\SignaturePlaceholder
 * and Signer work on NominaIndividual's structure exactly like every UBL document type, even
 * though NominaIndividual itself is not UBL — both only depend on the shared
 * ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent placeholder shape.
 */
final class SignedPayrollPipelineTest extends TestCase
{
    public function testBuildSignAndVerify(): void
    {
        [$certPem, $keyPem] = SelfSignedCert::generate();
        $credentials = CertificateLoader::loadPem($certPem, $keyPem);

        $n = PayrollBuilderTest::validNomina();

        $cune = Cune::compute(
            $n->numero,
            $n->fechaGen,
            $n->horaGen,
            $n->devengadosTotalCents,
            $n->deduccionesTotalCents,
            $n->comprobanteTotalCents,
            $n->empleador->nit,
            $n->trabajador->numeroDocumento,
            $n->tipoXML,
            '11111',
            $n->ambiente,
        );
        $softwareSc = SecurityCode::compute($n->softwareId, '11111', $n->numero);
        $codigoQr = Qr::url(Environment::from($n->ambiente), $cune);

        $doc = Builder::build($n, $cune, $softwareSc, $codigoQr);
        self::assertSame('NominaIndividual', $doc->documentElement->localName);

        $placeholder = SignaturePlaceholder::find($doc);
        (new Signer($credentials))->sign($doc->documentElement, $placeholder, 'supplier', new \DateTimeImmutable('2026-01-31T09:00:00-05:00'));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        $infoGeneral = $xpath->query('//*[local-name()="InformacionGeneral"]')->item(0);
        self::assertNotNull($infoGeneral);
        self::assertSame($cune, $infoGeneral->getAttribute('CUNE'));
        self::assertSame(Builder::ENCRIP_CUNE, $infoGeneral->getAttribute('EncripCUNE'));
        self::assertSame('supplier', $xpath->query('//xades:ClaimedRole')->item(0)?->textContent);

        $signedInfo = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
        self::assertNotNull($signedInfo);
        $canonSignedInfo = $signedInfo->C14N(false, false);
        $sigValueB64 = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0)->nodeValue;
        $publicKeyDetails = openssl_pkey_get_details($credentials->key);
        $publicKey = openssl_pkey_get_public($publicKeyDetails['key']);
        $verifyResult = openssl_verify($canonSignedInfo, base64_decode($sigValueB64), $publicKey, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $verifyResult, 'signature does not verify against the public key');
        self::assertSame(3, $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference')->length);
    }
}
