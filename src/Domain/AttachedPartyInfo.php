<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The reduced version of a third party used by AttachedDocument (SenderParty/ReceiverParty) —
 * it only carries basic tax data, unlike Party (which also has address, contact and legal
 * representation, used in AccountingSupplierParty/AccountingCustomerParty within the wrapped
 * document). Mirrors domain.AttachedPartyInfo.
 */
final class AttachedPartyInfo
{
    /** @param string[] $liabilityCodes */
    public function __construct(
        public string $name = '',
        public Identification $identification = new Identification(),
        /** TaxLevelCode's listName. */
        public string $taxRegimeCode = '',
        public array $liabilityCodes = [],
        public string $taxSchemeCode = '',
        public string $taxSchemeName = '',
    ) {
    }
}
