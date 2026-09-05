<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use RuntimeException;

/**
 * A soap:Fault returned by DIAN. Mirrors soap.Fault (soap/client.go).
 *
 * $faultCode (not $code — Exception::$code is a built-in int property this class cannot
 * shadow with a readonly string of the same name) holds the soap:Value inside soap:Fault/
 * soap:Code, e.g. "s:Sender".
 */
final class Fault extends RuntimeException
{
    public function __construct(
        public readonly string $faultCode,
        public readonly string $reason,
    ) {
        parent::__construct(sprintf('soap fault [%s]: %s', $faultCode, $reason));
    }
}
