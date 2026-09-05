<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * Identifies the invoice that a Credit/Debit Note corrects (cac:BillingReference) — always
 * required in both, unlike DiscrepancyResponse. Mirrors domain.BillingReference.
 */
final class BillingReference
{
    public function __construct(
        public string $prefix = '',
        public string $number = '',
        /** Hash of the referenced document — not this note's own CUFE/CUDE. */
        public string $cufe = '',
        public string $issueDate = '',
        /**
         * The referenced document's own hash scheme — "CUFE-SHA384" when a Credit/Debit Note
         * corrects a regular Invoice, "CUDE-SHA384" when it corrects a Documento Equivalente
         * Electrónico (e.g. a POS ticket). Must match the referenced document's actual
         * hashType, not this note's own — the two can differ.
         */
        public string $hashType = '',
    ) {
    }
}
