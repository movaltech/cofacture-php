<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

/**
 * The HTTP mechanics of sending a SOAP request, factored out from Client so tests can inject a
 * fake transport and assert on request/response handling without a real DIAN server or a local
 * HTTP listener — mirrors what the Go original's tests do with httptest.Server, minus needing an
 * actual socket.
 *
 * Lives directly under Cofacture\Soap, not Soap\Internal — it's a genuine, intentional extension
 * point (Client's own constructor accepts one), not an implementation detail; this project's own
 * test suite already implements it from outside the Soap namespace (see
 * tests/Unit/Support/FakeTransport.php), which is exactly what "Internal" is supposed to prevent.
 */
interface Transport
{
    public function send(string $url, string $body, string $contentType, int $timeoutSeconds): TransportResponse;
}
