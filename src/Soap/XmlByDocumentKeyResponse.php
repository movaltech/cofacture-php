<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The result of Client::getXmlByDocumentKey() — the original signed XML of a document
 * previously submitted via sendBillSync/sendBillAsync/sendTestSetAsync, given the trackId/
 * document key returned at submission time. Mirrors soap.XMLByDocumentKeyResponse
 * (soap/types.go).
 *
 * DIAN's own WSDL names this datacontract type "EventResponse" — a naming collision with a
 * future event-building package this library does not have yet; this class is named for what it
 * actually holds instead of copying that name.
 */
final class XmlByDocumentKeyResponse
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        /** Already base64-decoded — ready-to-use XML bytes. */
        public readonly string $xmlBytes,
        public readonly string $validationDate,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            code: XmlReader::text($el, 'Code'),
            message: XmlReader::text($el, 'Message'),
            xmlBytes: XmlReader::bytesFromBase64($el, 'XmlBytesBase64'),
            validationDate: XmlReader::text($el, 'ValidationDate'),
        );
    }
}
