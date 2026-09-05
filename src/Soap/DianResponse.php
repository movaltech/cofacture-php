<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The result of validating a document (SendBillSyncResult, GetStatusResult, each element of
 * GetStatusZipResult, SendNominaSyncResult, GetStatusEventResult, SendEventUpdateStatusResult).
 * Mirrors soap.DianResponse (soap/types.go).
 */
final class DianResponse
{
    /**
     * @param string[] $errorMessages
     * @param string $xmlBase64Bytes The raw base64 text exactly as DIAN sends it, NOT decoded
     *   here — Dian\Result::interpret() does that decode step, same layering as the Go
     *   original's dian package (confirmed against a real GetStatusZip response: DIAN's own XML
     *   character data is never auto-decoded, this field is genuinely base64 text until
     *   something explicitly decodes it).
     * @param string $xmlBytes The alternative DIAN sometimes uses instead of $xmlBase64Bytes —
     *   already the final XML content as sent, never base64 here, so it must NOT be run through
     *   a base64 decode (doing so would silently corrupt or empty it).
     */
    public function __construct(
        public readonly array $errorMessages,
        public readonly bool $isValid,
        public readonly string $statusCode,
        public readonly string $statusDescription,
        public readonly string $statusMessage,
        public readonly string $xmlBase64Bytes,
        public readonly string $xmlBytes,
        public readonly string $xmlDocumentKey,
        public readonly string $xmlFileName,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            errorMessages: array_map(
                static fn (DOMElement $item) => $item->textContent,
                XmlReader::nestedElements($el, 'ErrorMessage', 'string'),
            ),
            isValid: XmlReader::bool($el, 'IsValid'),
            statusCode: XmlReader::text($el, 'StatusCode'),
            statusDescription: XmlReader::text($el, 'StatusDescription'),
            statusMessage: XmlReader::text($el, 'StatusMessage'),
            xmlBase64Bytes: XmlReader::text($el, 'XmlBase64Bytes'),
            xmlBytes: XmlReader::text($el, 'XmlBytes'),
            xmlDocumentKey: XmlReader::text($el, 'XmlDocumentKey'),
            xmlFileName: XmlReader::text($el, 'XmlFileName'),
        );
    }
}
