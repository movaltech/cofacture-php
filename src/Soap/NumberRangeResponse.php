<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * A single numbering range returned by GetNumberingRange. Mirrors soap.NumberRangeResponse
 * (soap/types.go). Dates are plain strings exactly as DIAN returns them
 * ("2019-01-19T00:00:00") — the caller converts as needed.
 */
final class NumberRangeResponse
{
    public function __construct(
        public readonly string $resolutionNumber,
        public readonly string $resolutionDate,
        public readonly string $prefix,
        public readonly int $fromNumber,
        public readonly int $toNumber,
        public readonly string $validDateFrom,
        public readonly string $validDateTo,
        public readonly string $technicalKey,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            resolutionNumber: XmlReader::text($el, 'ResolutionNumber'),
            resolutionDate: XmlReader::text($el, 'ResolutionDate'),
            prefix: XmlReader::text($el, 'Prefix'),
            fromNumber: XmlReader::int($el, 'FromNumber'),
            toNumber: XmlReader::int($el, 'ToNumber'),
            validDateFrom: XmlReader::text($el, 'ValidDateFrom'),
            validDateTo: XmlReader::text($el, 'ValidDateTo'),
            technicalKey: XmlReader::text($el, 'TechnicalKey'),
        );
    }
}
