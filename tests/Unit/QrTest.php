<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Domain\Environment;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Party;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use Cofacture\Qr\Qr;
use PHPUnit\Framework\TestCase;

/** Mirrors cofacture/qr/qr_test.go. */
final class QrTest extends TestCase
{
    public function testUrl(): void
    {
        self::assertSame(
            'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=abc123',
            Qr::url(Environment::Produccion, 'abc123'),
        );
        self::assertSame(
            'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=abc123',
            Qr::url(Environment::Habilitacion, 'abc123'),
        );
    }

    public function testSupportDocumentContent(): void
    {
        $inv = new Invoice(
            prefix: 'DS',
            number: '1',
            issueDate: '2024-03-15',
            issueTime: '09:30:00-05:00',
            environmentCode: Environment::Habilitacion,
            headerTaxes: [new Tax(typeCode: '01', taxAmountCents: 1_900_000)],
            totals: new Totals(lineExtensionCents: 10_000_000, payableCents: 11_900_000),
            // DS: Supplier = non-obligated third party (supplier), Customer = issuer.
            supplier: new Party(identification: new Identification(number: '1020304050')),
            customer: new Party(identification: new Identification(number: '900123456')),
        );

        $cuds = '907e4444decc9e59c160a2fb3b6659b33dc5b632a5008922b9a62f83f757b1c448e47f5867f2b50dbdb96f48c7681168';
        $pin = '12345';

        $got = Qr::supportDocumentContent($inv, $cuds, $pin);

        // The 12 mandatory tags of the Support Document QR (Annex 1.9, section 11.7.1).
        $required = [
            'N°DocSoporte=DS1',
            'Fecha=2024-03-15',
            'Hora=09:30:00-05:00',
            'ValDS=100000.00',
            'CodImp=01',
            'ValImp=19000.00',
            'ValTot=119000.00',
            'NumSNO=1020304050',
            'NITABS=900123456',
            'PIN:12345',
            'Amb:2',
            'CUDS=' . $cuds,
        ];
        foreach ($required as $r) {
            self::assertStringContainsString($r, $got);
        }

        // The last line must be "URL=<searchqr>" — same endpoint as FE (FindDocument does not redirect).
        $lines = explode("\n", rtrim($got, "\n"));
        self::assertSame(
            'URL=https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=' . $cuds,
            $lines[count($lines) - 1],
        );
    }

    /** Verifies that environment "1" uses the production domain. */
    public function testSupportDocumentContentProduccion(): void
    {
        $inv = new Invoice(
            number: '99',
            issueDate: '2024-01-01',
            issueTime: '00:00:00-05:00',
            environmentCode: Environment::Produccion,
            totals: new Totals(lineExtensionCents: 100_000, payableCents: 100_000),
            supplier: new Party(identification: new Identification(number: '111')),
            customer: new Party(identification: new Identification(number: '222')),
        );
        $got = Qr::supportDocumentContent($inv, 'abc', '000');

        self::assertStringContainsString(
            'URL=https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=abc',
            $got,
        );
        self::assertStringContainsString('Amb:1', $got);
    }

    /** Confirms that when headerTaxes has entries but none is VAT ("01"), CodImp/ValImp fall
     *  back to the first tax instead of the "01"/"0.00" default. */
    public function testSupportDocumentContentNoVatFallsBackToFirstTax(): void
    {
        $inv = new Invoice(
            number: '1',
            issueDate: '2024-01-01',
            issueTime: '00:00:00-05:00',
            environmentCode: Environment::Habilitacion,
            headerTaxes: [new Tax(typeCode: '04', taxAmountCents: 500_00)], // ICA, not VAT
            totals: new Totals(lineExtensionCents: 100_000, payableCents: 105_000),
            supplier: new Party(identification: new Identification(number: '111')),
            customer: new Party(identification: new Identification(number: '222')),
        );
        $got = Qr::supportDocumentContent($inv, 'cuds', 'pin');
        self::assertStringContainsString('CodImp=04', $got);
        self::assertStringContainsString('ValImp=500.00', $got);
    }

    /** Confirms that with zero headerTaxes, CodImp/ValImp default to "01"/"0.00". */
    public function testSupportDocumentContentNoTaxesDefaultsToZero(): void
    {
        $inv = new Invoice(
            number: '1',
            issueDate: '2024-01-01',
            issueTime: '00:00:00-05:00',
            environmentCode: Environment::Habilitacion,
            totals: new Totals(lineExtensionCents: 100_000, payableCents: 100_000),
            supplier: new Party(identification: new Identification(number: '111')),
            customer: new Party(identification: new Identification(number: '222')),
        );
        $got = Qr::supportDocumentContent($inv, 'cuds', 'pin');
        self::assertStringContainsString('CodImp=01', $got);
        self::assertStringContainsString('ValImp=0.00', $got);
    }

    public function testSupportDocumentUrl(): void
    {
        self::assertSame(
            'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=cuds123',
            Qr::supportDocumentUrl(Environment::Produccion, 'cuds123'),
        );
        self::assertSame(
            'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=cuds123',
            Qr::supportDocumentUrl(Environment::Habilitacion, 'cuds123'),
        );
    }

    public function testAdjustmentNoteContent(): void
    {
        $inv = new Invoice(
            prefix: 'NAP',
            number: '1',
            issueDate: '2024-03-15',
            issueTime: '09:30:00-05:00',
            environmentCode: Environment::Habilitacion,
            headerTaxes: [new Tax(typeCode: '01', taxAmountCents: 1_900_000)],
            totals: new Totals(lineExtensionCents: 10_000_000, payableCents: 11_900_000),
            // Roles reversed, same as the Support Document it adjusts: Supplier = SNO, Customer = ABS.
            supplier: new Party(identification: new Identification(number: '1020304050')),
            customer: new Party(identification: new Identification(number: '900123456')),
        );

        $cuds = '907e4444decc9e59c160a2fb3b6659b33dc5b632a5008922b9a62f83f757b1c448e47f5867f2b50dbdb96f48c7681168';
        $pin = '12345';

        $got = Qr::adjustmentNoteContent($inv, $cuds, $pin);

        $required = [
            'N°NotaAjuste=NAP1',
            'Fecha=2024-03-15',
            'Hora=09:30:00-05:00',
            'ValNA=100000.00',
            'CodImp=01',
            'ValImp=19000.00',
            'ValTot=119000.00',
            'NumSNO=1020304050',
            'NITABS=900123456',
            'PIN:12345',
            'Amb:2',
            'CUDS=' . $cuds,
        ];
        foreach ($required as $r) {
            self::assertStringContainsString($r, $got);
        }

        $lines = explode("\n", rtrim($got, "\n"));
        self::assertSame(
            'URL=https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=' . $cuds,
            $lines[count($lines) - 1],
        );
    }

    /** Verifies that environment "1" uses the production domain. */
    public function testAdjustmentNoteContentProduccion(): void
    {
        $inv = new Invoice(
            number: '99',
            issueDate: '2024-01-01',
            issueTime: '00:00:00-05:00',
            environmentCode: Environment::Produccion,
            totals: new Totals(lineExtensionCents: 100_000, payableCents: 100_000),
            supplier: new Party(identification: new Identification(number: '111')),
            customer: new Party(identification: new Identification(number: '222')),
        );
        $got = Qr::adjustmentNoteContent($inv, 'abc', '000');

        self::assertStringContainsString(
            'URL=https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=abc',
            $got,
        );
        self::assertStringContainsString('Amb:1', $got);
    }

    /** Mirrors testSupportDocumentContentNoTaxesDefaultsToZero for adjustmentNoteContent()'s
     *  own (duplicated) fallback logic. */
    public function testAdjustmentNoteContentNoTaxesDefaultsToZero(): void
    {
        $inv = new Invoice(
            number: '1',
            issueDate: '2024-01-01',
            issueTime: '00:00:00-05:00',
            environmentCode: Environment::Habilitacion,
            totals: new Totals(lineExtensionCents: 100_000, payableCents: 100_000),
            supplier: new Party(identification: new Identification(number: '111')),
            customer: new Party(identification: new Identification(number: '222')),
        );
        $got = Qr::adjustmentNoteContent($inv, 'cuds', 'pin');
        self::assertStringContainsString('CodImp=01', $got);
        self::assertStringContainsString('ValImp=0.00', $got);
    }
}
