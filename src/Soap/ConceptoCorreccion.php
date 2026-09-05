<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/** A Credit/Debit Note's correction-concept catalog entry. Mirrors soap.ConceptoCorreccion. */
final class ConceptoCorreccion
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $descripcion,
        public readonly string $nombre,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            codigo: XmlReader::text($el, 'Codigo'),
            descripcion: XmlReader::text($el, 'Descripcion'),
            nombre: XmlReader::text($el, 'Nombre'),
        );
    }
}
