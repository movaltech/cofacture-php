<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Zip\Zip;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** Mirrors cofacture/zip/zip_test.go. */
final class ZipTest extends TestCase
{
    public function testBuildRoundTrip(): void
    {
        $files = [
            'fv0800197268000190000000B.xml' => '<Invoice/>',
            'ar0800197268000190000000B.xml' => '<ApplicationResponse/>',
        ];

        $data = Zip::build($files);

        $tmpPath = tempnam(sys_get_temp_dir(), 'cofacture_ziptest_');
        self::assertNotFalse($tmpPath);
        try {
            file_put_contents($tmpPath, $data);

            $archive = new ZipArchive();
            self::assertTrue($archive->open($tmpPath) === true, 'could not open generated zip');
            self::assertSame(count($files), $archive->numFiles);

            $i = 0;
            foreach ($files as $name => $content) {
                $entryName = $archive->getNameIndex($i);
                self::assertSame($name, $entryName, "entry $i: name mismatch");
                self::assertSame($content, $archive->getFromName($name), "entry $name: content mismatch");
                $i++;
            }
            $archive->close();
        } finally {
            @unlink($tmpPath);
        }
    }

    public function testDocumentFileName(): void
    {
        // NIT 800197268 (9 digits) -> 0800197268 (10 digits), in-house software, year 2019,
        // decimal sequence number 11 -> hex "0000000B" (not "00000011": see the note in
        // Zip::documentFileName about the inconsistency in the Annex's own illustrative example).
        $cases = [
            [Zip::KIND_INVOICE, 'fv0800197268000190000000B.xml'],
            [Zip::KIND_CREDIT_NOTE, 'nc0800197268000190000000B.xml'],
            [Zip::KIND_DEBIT_NOTE, 'nd0800197268000190000000B.xml'],
            [Zip::KIND_SUPPORT_DOCUMENT, 'ds0800197268000190000000B.xml'],
            [Zip::KIND_ADJUSTMENT_NOTE, 'na0800197268000190000000B.xml'],
        ];
        foreach ($cases as [$kind, $want]) {
            self::assertSame($want, Zip::documentFileName($kind, '800197268', Zip::SOFTWARE_PROPIO_CODE, 2019, 11));
        }
    }

    public function testPackageFileName(): void
    {
        self::assertSame(
            'z0800197268000190000000B.zip',
            Zip::packageFileName('800197268', Zip::SOFTWARE_PROPIO_CODE, 2019, 11),
        );
    }
}
