<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * A document's IVA and total amount, as DIAN's own summary reports them — a bare float from the
 * wire, not this library's int-cents convention: there is nothing to truncate here, this is
 * DIAN's own already-formatted response, not a value this library constructs. Mirrors
 * soap.TotalEImpuestos.
 */
final class TotalEImpuestos
{
    public function __construct(
        public readonly float $iva,
        public readonly float $total,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            iva: XmlReader::float($el, 'Iva'),
            total: XmlReader::float($el, 'Total'),
        );
    }
}
