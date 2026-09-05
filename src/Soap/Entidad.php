<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * Identifies a party (issuer or receiver) in getDocumentInfo()'s response — a much smaller
 * shape than Domain\Party, since this is DIAN's own summary, not the full UBL party. Mirrors
 * soap.Entidad (soap/types.go).
 */
final class Entidad
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $numeroDoc,
        public readonly string $procedencia,
        public readonly string $tipoDoc,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            nombre: XmlReader::text($el, 'Nombre'),
            numeroDoc: XmlReader::text($el, 'NumeroDoc'),
            procedencia: XmlReader::text($el, 'Procedencia'),
            tipoDoc: XmlReader::text($el, 'TipoDoc'),
        );
    }
}
