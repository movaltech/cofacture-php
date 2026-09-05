<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

use DateTimeZone;

/**
 * Colombia's time zone: fixed UTC-5, no daylight saving. Mirrors domain.Bogota (Go's
 * time.FixedZone("America/Bogota", -5*60*60)) — built from a plain UTC offset ("-05:00"), not
 * PHP's named "America/Bogota" zone, so a caller building a signing timestamp with this doesn't
 * depend on the host's tzdata being installed or current, the same reasoning as the Go original.
 * README.md's own example currently uses `new DateTimeZone('America/Bogota')` directly instead
 * of this — both produce the same real-world instant for Colombia today, but only this one is
 * safe on a host with no/stale tzdata.
 */
final class Bogota
{
    private function __construct()
    {
    }

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone('-05:00');
    }
}
