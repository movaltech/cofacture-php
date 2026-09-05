<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/** A tax subtotal, at either line or header level. Mirrors domain.Tax. */
final class Tax
{
    public function __construct(
        public int $taxableAmountCents = 0,
        public int $taxAmountCents = 0,
        public float $percent = 0.0,
        /** tax_types catalog, e.g. "01" = VAT */
        public string $typeCode = '',
        public string $typeName = '',
    ) {
    }
}
