<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/** Used for free samples or no-charge items (PricingReference). Mirrors domain.ReferencePrice. */
final class ReferencePrice
{
    public function __construct(
        public int $priceAmountCents = 0,
        public string $typeCode = '',
    ) {
    }
}
