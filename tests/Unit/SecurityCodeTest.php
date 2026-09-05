<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\SecurityCode\SecurityCode;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors cofacture/securitycode/securitycode_test.go. There is no published official example
 * to validate this formula byte-for-byte (unlike CUFE) — this is a regression/sanity check:
 * correct length, determinism, and that each input actually participates in the hash.
 */
final class SecurityCodeTest extends TestCase
{
    public function testCompute(): void
    {
        $got = SecurityCode::compute('software-id', '1234', 'SETP1');
        self::assertSame(96, strlen($got), 'expected length 96 (SHA-384 in hex)');

        self::assertSame($got, SecurityCode::compute('software-id', '1234', 'SETP1'), 'Compute is not deterministic');

        self::assertNotSame($got, SecurityCode::compute('software-id', '1234', 'SETP2'), 'changing documentId should change the result');
        self::assertNotSame($got, SecurityCode::compute('software-id', '9999', 'SETP1'), 'changing the PIN should change the result');
    }
}
