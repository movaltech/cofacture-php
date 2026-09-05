<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Dian;

/**
 * A validation message already split into its parts. DIAN delivers them as a single string
 * with the format "Regla: <code>, <Rechazo|Notificación>: <text>" — $severity is left empty if
 * the message doesn't follow that pattern (it isn't discarded; $raw always keeps the original).
 * Mirrors dian.Message.
 */
final class Message
{
    public function __construct(
        public readonly string $rule = '',
        public readonly string $severity = '',
        public readonly string $text = '',
        public readonly string $raw = '',
    ) {
    }

    /** True when $severity is "Rechazo" — distinguishes an actual rejection from a plain
     *  informational notice (DIAN can approve a document while still attaching notices). */
    public function isRejection(): bool
    {
        return $this->severity === 'Rechazo';
    }

    public static function parse(string $raw): self
    {
        if (!str_starts_with($raw, 'Regla: ')) {
            return new self(raw: $raw);
        }
        $rest = substr($raw, strlen('Regla: '));

        $commaPos = strpos($rest, ', ');
        if ($commaPos === false) {
            return new self(raw: $raw);
        }
        $rule = substr($rest, 0, $commaPos);
        $rest = substr($rest, $commaPos + 2);

        $colonPos = strpos($rest, ': ');
        if ($colonPos === false) {
            return new self(raw: $raw);
        }
        $severity = substr($rest, 0, $colonPos);
        $text = substr($rest, $colonPos + 2);

        return new self(rule: $rule, severity: $severity, text: $text, raw: $raw);
    }
}
