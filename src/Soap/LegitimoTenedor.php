<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * The "legitimate holder" of a title-value document (factura como título valor) — populated
 * only after it has been transferred/endorsed. Mirrors soap.LegitimoTenedor.
 */
final class LegitimoTenedor
{
    public function __construct(
        public readonly string $fechaInscripcionComoTituloValor,
        public readonly string $nombre,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            fechaInscripcionComoTituloValor: XmlReader::text($el, 'FechaInscripcionComoTituloValor'),
            nombre: XmlReader::text($el, 'Nombre'),
        );
    }
}
