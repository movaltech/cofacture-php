<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Payroll;

/** Mirrors payroll/filename.go. */
final class Filename
{
    private function __construct()
    {
    }

    /**
     * Builds a NominaIndividual's XML file name: nie{NIT, 10 digits, left-padded}{year, 2
     * digits}{consecutive, 8 hex}.xml — section 3.3 of the Electronic Payroll Technical Annex.
     * The consecutive resets on January 1st each year; keeping track of it is the
     * orchestrator's responsibility.
     */
    public static function xmlFileName(string $nit, int $year, int $consecutive): string
    {
        return sprintf('nie%010s%02d%08X.xml', $nit, $year % 100, $consecutive);
    }

    /** Builds a NominaIndividualDeAjuste's XML file name: niae{NIT, 10 digits}{year, 2
     *  digits}{consecutive, 8 hex}.xml */
    public static function adjustXmlFileName(string $nit, int $year, int $consecutive): string
    {
        return sprintf('niae%010s%02d%08X.xml', $nit, $year % 100, $consecutive);
    }

    /**
     * Builds the ZIP file name that contains the payroll document: z{NIT, 10 digits}{year, 2
     * digits}{consecutive, 8 hex}.zip — section 3.5 of the Technical Annex. This consecutive
     * belongs to the sent ZIP package, distinct from the individual XML files' consecutive.
     */
    public static function zipFileName(string $nit, int $year, int $consecutive): string
    {
        return sprintf('z%010s%02d%08X.zip', $nit, $year % 100, $consecutive);
    }
}
