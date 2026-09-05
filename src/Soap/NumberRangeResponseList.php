<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The full result of GetNumberingRange — every active and historical numbering range DIAN has
 * authorized for a given issuer + software pair. Mirrors soap.NumberRangeResponseList
 * (soap/types.go). $operationCode "0" means success; any other code indicates a DIAN-side
 * error.
 */
final class NumberRangeResponseList
{
    /** @param NumberRangeResponse[] $responseList */
    public function __construct(
        public readonly string $operationCode,
        public readonly string $operationDescription,
        public readonly array $responseList,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            operationCode: XmlReader::text($el, 'OperationCode'),
            operationDescription: XmlReader::text($el, 'OperationDescription'),
            responseList: array_map(
                NumberRangeResponse::fromXml(...),
                XmlReader::nestedElements($el, 'ResponseList', 'NumberRangeResponse'),
            ),
        );
    }
}
