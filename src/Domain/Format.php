<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * Mirrors domain/format.go — renders cents as the 2-decimal string DIAN requires (no
 * thousands separator, dot as the decimal separator), used both when serializing XML and when
 * composing the CUFE/CUDE hash input strings, which share the same format.
 */
final class Format
{
    private function __construct()
    {
    }

    public static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        if ($negative) {
            $cents = -$cents;
        }
        $formatted = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
        return $negative ? '-' . $formatted : $formatted;
    }
}
