<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\SecurityCode;

/**
 * Computes sts:SoftwareSecurityCode per section 11.8 of Technical Annex 1.9. Mirrors
 * securitycode/securitycode.go. Neither a CUFE nor a CUDE — it's the fingerprint of the
 * DIAN-authorized software, applies equally to Invoice/CreditNote/DebitNote.
 */
final class SecurityCode
{
    private function __construct()
    {
    }

    /**
     * $softwareId and $pin are sensitive values DIAN assigns when the software is activated;
     * never log or persist them in plain text outside of wherever they are already protected.
     * $documentId is the document's ID (Invoice/CreditNote/DebitNote/ApplicationResponse), i.e.
     * prefix + number.
     */
    public static function compute(string $softwareId, string $pin, string $documentId): string
    {
        return hash('sha384', $softwareId . $pin . $documentId);
    }
}
