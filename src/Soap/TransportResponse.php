<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

/**
 * A raw HTTP response. $statusCode is kept alongside $body because DIAN's receiving service
 * does not use HTTP status as a reliable success/error signal (see Client's doc comment) — the
 * body must always be parsed first, regardless of status.
 */
final class TransportResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
    }
}
