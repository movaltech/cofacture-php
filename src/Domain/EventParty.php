<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The reduced third-party shape ApplicationResponse events use for SenderParty/ReceiverParty
 * (Technical Annex 1.9, section 6.5.4, groups AAF/AAG) — narrower than Party: just registration
 * name, identification and tax scheme, no address/liability codes/contact. Mirrors
 * domain.EventParty.
 */
final class EventParty
{
    public function __construct(
        public string $name = '',
        public Identification $identification = new Identification(),
        public string $taxSchemeCode = '',
        public string $taxSchemeName = '',
    ) {
    }
}
