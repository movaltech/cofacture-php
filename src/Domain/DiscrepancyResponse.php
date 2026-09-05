<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The note's reason (cac:DiscrepancyResponse) — required when the note references a specific
 * invoice (operation "20" for Credit Note, "30" for Debit Note, operation type catalog).
 * $responseCode uses the credit/debit note concept catalog. Mirrors domain.DiscrepancyResponse.
 */
final class DiscrepancyResponse
{
    public function __construct(
        /** Normally prefix+number of the referenced invoice. */
        public string $referenceId = '',
        public string $responseCode = '',
        public string $description = '',
    ) {
    }
}
