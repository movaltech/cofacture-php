<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The DIAN-authorized numbering resolution range that covers this document (InvoiceControl).
 * Mirrors domain.NumberingRange.
 */
final class NumberingRange
{
    public function __construct(
        public string $authorizedCode = '',
        public string $prefix = '',
        public string $startNumber = '',
        public string $endNumber = '',
        /** YYYY-MM-DD */
        public string $startDate = '',
        /** YYYY-MM-DD */
        public string $endDate = '',
    ) {
    }
}
