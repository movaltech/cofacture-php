<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/** Identifies the technology provider and the software authorized by DIAN. Mirrors domain.SoftwareProvider. */
final class SoftwareProvider
{
    public function __construct(
        public Identification $providerIdentification = new Identification(),
        public string $softwareId = '',
    ) {
    }
}
