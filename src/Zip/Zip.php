<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Zip;

use RuntimeException;

/**
 * Compresses signed UBL documents into the package format DIAN's receiving web services
 * require (SendBillSync/SendBillAsync, Technical Annex 1.9, sections 6.5.7/6.5.8/7.8/7.10).
 * Mirrors zip/zip.go + zip/filename.go.
 */
final class Zip
{
    /** Technology Provider code ("ppp") for issuers using their own software (section 6.5.8). */
    public const SOFTWARE_PROPIO_CODE = '000';

    public const KIND_INVOICE = 'fv';
    public const KIND_CREDIT_NOTE = 'nc';
    public const KIND_DEBIT_NOTE = 'nd';
    public const KIND_SUPPORT_DOCUMENT = 'ds';
    public const KIND_ADJUSTMENT_NOTE = 'na';
    public const KIND_APPLICATION_RESPONSE = 'ar';
    public const KIND_ATTACHED_DOCUMENT = 'ad';

    private function __construct()
    {
    }

    /**
     * Compresses one file into a single ZIP, in memory, and returns its bytes. SendBillSync
     * requires exactly one document; SendBillAsync allows up to 50 — validating that count is
     * the caller's responsibility, this class only builds the archive.
     *
     * @param array<string,string> $files fileName => content, in the order they should appear
     *   in the archive (an associative array preserves insertion order in PHP).
     */
    public static function build(array $files): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'cofacture_zip_');
        if ($tmpPath === false) {
            throw new RuntimeException('zip: could not create a temporary file');
        }

        try {
            $archive = new \ZipArchive();
            if ($archive->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('zip: could not open the archive for writing');
            }
            foreach ($files as $name => $content) {
                if (!$archive->addFromString($name, $content)) {
                    $archive->close();
                    throw new RuntimeException("zip: could not add entry {$name}");
                }
            }
            $archive->close();
            $bytes = file_get_contents($tmpPath);
            if ($bytes === false) {
                throw new RuntimeException('zip: could not read back the archive');
            }
        } catch (\Throwable $e) {
            // Best-effort only on the failure path — the caller already has a real error to
            // surface; a secondary "couldn't delete the temp file" failure here must not replace
            // it (a finally block doing the same unlink would do exactly that).
            @unlink($tmpPath);
            throw $e;
        }

        // Success path: $tmpPath briefly held a real signed UBL document — real NIT, names,
        // amounts, tax IDs. Deleting it is not optional cleanup, so a failure here must be loud,
        // not swallowed with @ — the alternative is a legally-binding document sitting in a
        // shared temp directory with nothing ever telling anyone it's there.
        if (!unlink($tmpPath)) {
            throw new RuntimeException(
                "zip: built the archive but could not delete the temporary file at {$tmpPath} — " .
                'it holds a real signed document; remove it by hand.',
            );
        }

        return $bytes;
    }

    /**
     * Builds the XML file name required by section 6.5.7:
     *   {kind}{NIT without check digit, 10 digits}{PT code, 3 digits}{year, 2 digits}{consecutive, 8 hex}.xml
     *
     * $nit must be passed without the check digit. $ptCode is the 3-digit Technology Provider
     * code assigned by DIAN (self::SOFTWARE_PROPIO_CODE for own software). $consecutive is the
     * running count of files sent for the corresponding type — the annex requires resetting it
     * to 1 every January 1st; keeping track of it is the caller's responsibility.
     */
    public static function documentFileName(string $kind, string $nit, string $ptCode, int $year, int $consecutive): string
    {
        return sprintf('%s%010s%s%02d%08X.xml', $kind, $nit, $ptCode, $year % 100, $consecutive);
    }

    /** Builds the ZIP file name required by section 6.5.8. */
    public static function packageFileName(string $nit, string $ptCode, int $year, int $consecutive): string
    {
        return sprintf('z%010s%s%02d%08X.zip', $nit, $ptCode, $year % 100, $consecutive);
    }
}
