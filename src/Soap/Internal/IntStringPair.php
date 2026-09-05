<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap\Internal;

use DOMElement;

/**
 * Documento::$estado's dictionary entry (ArrayOfKeyValueOfintstring in the WSDL — DIAN
 * represents Estado as an int-keyed dictionary, not a plain string). Mirrors soap.intStringPair
 * (soap/types.go) — kept internal like its Go original (lowercase there), unlike every other
 * type in this response, which are part of the public soap.* API.
 */
final class IntStringPair
{
    public function __construct(
        public readonly int $key,
        public readonly string $value,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            key: XmlReader::int($el, 'Key'),
            value: XmlReader::text($el, 'Value'),
        );
    }
}
