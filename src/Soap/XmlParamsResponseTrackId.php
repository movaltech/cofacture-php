<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * An initial ZIP validation error (it never made it into the validation queue). Mirrors
 * soap.XMLParamsResponseTrackId (soap/types.go).
 */
final class XmlParamsResponseTrackId
{
    public function __construct(
        public readonly string $documentKey,
        public readonly string $processedMessage,
        public readonly string $senderCode,
        public readonly bool $success,
        public readonly string $xmlFileName,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            documentKey: XmlReader::text($el, 'DocumentKey'),
            processedMessage: XmlReader::text($el, 'ProcessedMessage'),
            senderCode: XmlReader::text($el, 'SenderCode'),
            success: XmlReader::bool($el, 'Success'),
            xmlFileName: XmlReader::text($el, 'XmlFileName'),
        );
    }
}
