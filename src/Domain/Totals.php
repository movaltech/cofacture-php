<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The document's legal totals (LegalMonetaryTotal). Mirrors domain.Totals. The builder does not
 * recompute or cross-check these fields against Line.lineExtensionCents or Line.taxes — getting
 * them to actually add up to the sum of the document's lines is the caller's responsibility,
 * same as every other value in this package (see the no-catalog-validation note on Invoice).
 */
final class Totals
{
    public function __construct(
        public int $lineExtensionCents = 0,
        public int $taxExclusiveCents = 0,
        public int $taxInclusiveCents = 0,
        /** Only serialized when > 0 and the document is an Invoice */
        public int $prepaidCents = 0,
        /**
         * cbc:PayableRoundingAmount — the only monetary field the Technical Annex allows to be
         * negative (e.g. rounding a POS total to the nearest 50/100 pesos). Only serialized when
         * non-zero.
         */
        public int $roundingCents = 0,
        public int $payableCents = 0,
    ) {
    }
}
