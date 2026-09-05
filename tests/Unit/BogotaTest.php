<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Domain\Bogota;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Mirrors cofacture/domain/time.go's own reasoning: a fixed UTC-5 offset, not the named
 *  "America/Bogota" zone, so this never depends on the host's tzdata. */
final class BogotaTest extends TestCase
{
    public function testTimezoneIsFixedUtcMinusFive(): void
    {
        $tz = Bogota::timezone();
        self::assertSame('-05:00', $tz->getName());

        $now = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
        self::assertSame('2026-06-15 07:00:00', $now->setTimezone($tz)->format('Y-m-d H:i:s'));
    }
}
