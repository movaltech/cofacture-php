<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/** A document's series/folio and its issuance/signature dates. Mirrors soap.NumeroDocumento. */
final class NumeroDocumento
{
    public function __construct(
        public readonly string $fechaEmision,
        public readonly string $fechaFirma,
        public readonly string $folio,
        public readonly string $serie,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            fechaEmision: XmlReader::text($el, 'FechaEmision'),
            fechaFirma: XmlReader::text($el, 'FechaFirma'),
            folio: XmlReader::text($el, 'Folio'),
            serie: XmlReader::text($el, 'Serie'),
        );
    }
}
