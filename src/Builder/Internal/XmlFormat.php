<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\Format;

/** Mirrors builder/format.go — value formatting shared by every builder. */
final class XmlFormat
{
    private function __construct()
    {
    }

    /** Renders cents as the 2-decimal string DIAN requires. */
    public static function amount(int $cents): string
    {
        return Format::formatCents($cents);
    }

    /** Renders a quantity with 6 decimals, as the technical annex requires. */
    public static function quantity(float $quantity): string
    {
        return sprintf('%.6f', $quantity);
    }

    /** Renders a tax percentage with 2 decimals. */
    public static function percent(float $percent): string
    {
        return sprintf('%.2f', $percent);
    }

    public static function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
