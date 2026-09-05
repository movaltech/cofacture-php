<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The result of Client::getDocumentInfo() — full metadata (parties, taxes, notes, referencing
 * events, validations) for a document identified by UUID. Mirrors soap.DocumentInfoResponse
 * (soap/types.go). This is a heavier query than getStatus() and has not been exercised against
 * DIAN's certification environment — the shape mirrors the WSDL's own schema exactly, but which
 * fields DIAN actually populates in practice is unconfirmed.
 */
final class DocumentInfoResponse
{
    /** @param Documento[] $documentInfo */
    public function __construct(
        public readonly string $compressedDocumentInfo,
        public readonly array $documentInfo,
        public readonly string $statusCode,
        public readonly string $statusDescription,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            compressedDocumentInfo: XmlReader::text($el, 'CompressedDocumentInfo'),
            documentInfo: array_map(Documento::fromXml(...), XmlReader::nestedElements($el, 'DocumentInfo', 'Documento')),
            statusCode: XmlReader::text($el, 'StatusCode'),
            statusDescription: XmlReader::text($el, 'StatusDescription'),
        );
    }
}
