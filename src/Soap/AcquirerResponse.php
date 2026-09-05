<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The result of GetAcquirer — queries DIAN's exchange/notification registry for a given third
 * party, not a full RUT lookup: it only returns $receiverName/$receiverEmail if that
 * identification number already has a name/email registered to receive electronic documents.
 * Mirrors soap.AcquirerResponse (soap/types.go). An empty $statusCode/$message (or an error
 * $statusCode) is the normal, expected result for most identification numbers — it does not
 * mean the query failed.
 */
final class AcquirerResponse
{
    public function __construct(
        public readonly string $message,
        public readonly string $statusCode,
        public readonly string $receiverName,
        public readonly string $receiverEmail,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            message: XmlReader::text($el, 'Message'),
            statusCode: XmlReader::text($el, 'StatusCode'),
            receiverName: XmlReader::text($el, 'ReceiverName'),
            receiverEmail: XmlReader::text($el, 'ReceiverEmail'),
        );
    }
}
