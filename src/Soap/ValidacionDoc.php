<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/** One validation entry DIAN ran against a document (or one of its events). Mirrors soap.ValidacionDoc. */
final class ValidacionDoc
{
    public function __construct(
        public readonly bool $isNotification,
        public readonly bool $isValida,
        public readonly string $mensajeError,
        public readonly string $nombre,
        public readonly string $status,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            isNotification: XmlReader::bool($el, 'IsNotification'),
            isValida: XmlReader::bool($el, 'IsValida'),
            mensajeError: XmlReader::text($el, 'MensajeError'),
            nombre: XmlReader::text($el, 'Nombre'),
            status: XmlReader::text($el, 'Status'),
        );
    }
}
