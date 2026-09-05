<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * Identifies the natural person who received the goods/service. Only used by the Acuse de
 * Recibo event (section 6.5.5.3, group AAH11-18) — null for every other type. Mirrors
 * domain.EventReceiverPerson.
 */
final class EventReceiverPerson
{
    public function __construct(
        public Identification $identification = new Identification(),
        public string $firstName = '',
        public string $familyName = '',
        public string $jobTitle = '',
        public string $organizationDepartment = '',
    ) {
    }
}
