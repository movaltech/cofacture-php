<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The result of SendBillAsync/SendTestSetAsync/SendBillAttachmentAsync: either there are
 * initial errors ($errorMessageList) or there is a $zipKey to query later with GetStatusZip.
 * Mirrors soap.UploadDocumentResponse (soap/types.go).
 */
final class UploadDocumentResponse
{
    /** @param XmlParamsResponseTrackId[] $errorMessageList */
    public function __construct(
        public readonly array $errorMessageList,
        public readonly string $zipKey,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            errorMessageList: array_map(
                XmlParamsResponseTrackId::fromXml(...),
                XmlReader::nestedElements($el, 'ErrorMessageList', 'XmlParamsResponseTrackId'),
            ),
            zipKey: XmlReader::text($el, 'ZipKey'),
        );
    }
}
