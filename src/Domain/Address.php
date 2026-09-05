<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/** The physical/registered address of a Party. Mirrors domain.Address. */
final class Address
{
    public function __construct(
        public string $line = '',
        public string $cityCode = '',
        public string $cityName = '',
        public string $postalZone = '',
        public string $stateCode = '',
        public string $stateName = '',
        public string $countryCode = '',
        public string $countryName = '',
    ) {
    }
}
