<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit\Support;

use Cofacture\Soap\Transport;
use Cofacture\Soap\TransportResponse;

/**
 * A canned-response Transport for testing Client without a real DIAN server or a local HTTP
 * listener — mirrors what the Go original's tests do with httptest.Server (Client::call's own
 * signing/parsing logic is exercised for real; only the socket is faked).
 */
final class FakeTransport implements Transport
{
    public ?string $sentUrl = null;
    public ?string $sentBody = null;
    public ?string $sentContentType = null;

    public function __construct(
        private readonly string $responseBody,
        private readonly int $statusCode = 200,
    ) {
    }

    public function send(string $url, string $body, string $contentType, int $timeoutSeconds): TransportResponse
    {
        $this->sentUrl = $url;
        $this->sentBody = $body;
        $this->sentContentType = $contentType;
        return new TransportResponse($this->statusCode, $this->responseBody);
    }
}
