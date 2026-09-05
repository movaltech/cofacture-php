<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The result of Client::getExchangeEmails() — the email addresses configured to exchange
 * electronic documents with DIAN's registry, as a CSV. Mirrors soap.ExchangeEmailResponse
 * (soap/types.go).
 */
final class ExchangeEmailResponse
{
    public function __construct(
        /** Already base64-decoded — ready-to-use CSV bytes. */
        public readonly string $csvBytes,
        public readonly string $message,
        public readonly string $statusCode,
        public readonly bool $success,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            csvBytes: XmlReader::bytesFromBase64($el, 'CsvBase64Bytes'),
            message: XmlReader::text($el, 'Message'),
            statusCode: XmlReader::text($el, 'StatusCode'),
            success: XmlReader::bool($el, 'Success'),
        );
    }
}
