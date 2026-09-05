<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Payroll\Filename;
use PHPUnit\Framework\TestCase;

/**
 * Locks in the exact file-naming convention from section 3.3/3.5 of the Electronic Payroll
 * Technical Annex: prefix, NIT left-padded to 10 digits, year as 2 digits, consecutive as 8
 * uppercase hex digits. Mirrors payroll/filename_test.go.
 */
final class PayrollFilenameTest extends TestCase
{
    public function testXmlFileName(): void
    {
        self::assertSame('nie000638235624' . '00000001' . '.xml', Filename::xmlFileName('6382356', 2024, 1));
    }

    public function testAdjustXmlFileName(): void
    {
        self::assertSame('niae000638235624' . '00000001' . '.xml', Filename::adjustXmlFileName('6382356', 2024, 1));
    }

    public function testZipFileName(): void
    {
        self::assertSame('z000638235624' . '00000001' . '.zip', Filename::zipFileName('6382356', 2024, 1));
    }
}
