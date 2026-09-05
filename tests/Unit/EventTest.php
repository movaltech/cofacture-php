<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Event\Event;
use PHPUnit\Framework\TestCase;

/** Mirrors event/event_test.go. */
final class EventTest extends TestCase
{
    /** Reproduces the official worked example from Technical Annex 1.9, section 11.5.1. */
    public function testComputeOfficialExample(): void
    {
        $got = Event::compute(
            '1',              // Num_DE
            '2019-04-30',     // Fec_Emi
            '19:48:50-05:00', // Hor_Emi
            '99998888',       // NitFE
            '800197268',      // DocAdq
            '030',            // ResponseCode
            'FE123',          // ID
            '01',             // DocumentTypeCode
            '11111',          // Software-PIN
        );

        self::assertSame('0d91ba25b01f5e7dbda870a11b274501d3a62a73e91932c473c86c93f12a142a2ac45876efcde3e679024a01c0be41f9', $got);
    }

    public function testTacitAcceptanceNote(): void
    {
        $got = Event::tacitAcceptanceNote('1', 'CUDE123', 'Consumidor Final', '222222222222');

        self::assertStringContainsString('Recibo de bienes y servicios 1 con CUDE CUDE123', $got);
        self::assertStringContainsString('el adquirente Consumidor Final identificado con NIT 222222222222', $got);
    }
}
