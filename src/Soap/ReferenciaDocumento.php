<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * A reference to another document (e.g. the invoice a Credit Note corrects), as summarized by
 * getDocumentInfo()/Evento. Mirrors soap.ReferenciaDocumento.
 */
final class ReferenciaDocumento
{
    public function __construct(
        public readonly string $descripcion,
        public readonly string $documentTypeId,
        public readonly string $documentTypeName,
        public readonly Entidad $emisor,
        public readonly string $fecha,
        public readonly Entidad $receptor,
        public readonly string $uuid,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            descripcion: XmlReader::text($el, 'Descripcion'),
            documentTypeId: XmlReader::text($el, 'DocumentTypeId'),
            documentTypeName: XmlReader::text($el, 'DocumentTypeName'),
            emisor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Emisor')),
            fecha: XmlReader::text($el, 'Fecha'),
            receptor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Receptor')),
            uuid: XmlReader::text($el, 'UUID'),
        );
    }
}
