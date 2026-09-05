<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use RuntimeException;

/** Default Transport, built on PHP's stream wrapper — no extra extension (e.g. curl) required. */
final class StreamTransport implements Transport
{
    public function send(string $url, string $body, string $contentType, int $timeoutSeconds): TransportResponse
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: {$contentType}\r\n",
                'content' => $body,
                'timeout' => $timeoutSeconds,
                // DIAN's service can respond with a non-2xx HTTP status while still carrying a
                // perfectly valid, meaningful SOAP body (see Client's doc comment) — without
                // this, PHP's stream wrapper discards the body on 4xx/5xx.
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $respBody = @file_get_contents($url, false, $context);
        if ($respBody === false) {
            $error = error_get_last();
            throw new RuntimeException('soap: send request: ' . ($error['message'] ?? 'unknown error'));
        }

        return new TransportResponse(self::parseStatusCode($http_response_header ?? []), $respBody);
    }

    /** @param string[] $headers */
    private static function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                return (int) $m[1];
            }
        }
        return 0;
    }
}
