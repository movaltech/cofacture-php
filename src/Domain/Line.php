<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/** A detail line (InvoiceLine). Mirrors domain.Line. */
final class Line
{
    /** @param Tax[] $taxes */
    public function __construct(
        public string $description = '',
        public float $quantity = 0.0,
        /** unit_codes catalog, e.g. "94" */
        public string $unitCode = '',
        public int $lineExtensionCents = 0,
        public int $unitPriceCents = 0,
        public bool $freeOfCharge = false,
        /** Required when freeOfCharge === true */
        public ?ReferencePrice $referencePrice = null,
        public string $itemCode = '',
        /** Product coding standard catalog (e.g. "999") */
        public string $itemTypeCode = '',
        public string $itemTypeName = '',
        public string $itemTypeAgencyId = '',
        public array $taxes = [],
    ) {
    }
}
