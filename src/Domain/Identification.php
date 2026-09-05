<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * A third party's identification number (NIT, national ID, etc.) along with the attributes
 * DIAN requires in the schemeID/schemeName schemas.
 *
 * Mirrors domain.Identification in the Go original (domain/types.go) — same fields, same
 * meaning, plain public properties (no validation) on purpose: this package doesn't know
 * whether a code is valid, only where it goes in the XML. See Package-level note in
 * Invoice.php for the full boundary this mirrors.
 */
final class Identification
{
    public function __construct(
        public string $number = '',
        /** identification_types catalog, e.g. "31" = NIT */
        public string $typeCode = '',
        /** Check digit, only applies when typeCode === "31" */
        public string $verificationCode = '',
    ) {
    }
}
