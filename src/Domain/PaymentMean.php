<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/** A payment method (PaymentMeans). Mirrors domain.PaymentMean. */
final class PaymentMean
{
    public function __construct(
        /** Payment term: cash/credit (the orchestrator's "payment_terms" catalog) */
        public string $code = '',
        /** The payment method itself: cash, transfer... ("payment_methods" catalog) */
        public string $paymentMethodCode = '',
        /** Only applies when code === "2" (credit) */
        public string $dueDate = '',
        public string $paymentReference = '',
    ) {
    }
}
