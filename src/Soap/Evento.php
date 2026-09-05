<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * One lifecycle event recorded against a document (e.g. Acuse de Recibo, Aceptación Tácita) as
 * summarized by getDocumentInfo() — not to be confused with a RADIAN event this library builds
 * before submission; this type only describes what DIAN already has on file. Mirrors soap.Evento.
 */
final class Evento
{
    /** @param ReferenciaDocumento[] $referenciasDocumento
     *  @param ValidacionDoc[] $validacionesDoc */
    public function __construct(
        public readonly string $codigo,
        public readonly string $descripcion,
        public readonly Entidad $emisor,
        public readonly NumeroDocumento $numeroDocumento,
        public readonly Entidad $receptor,
        public readonly array $referenciasDocumento,
        public readonly string $uuid,
        public readonly array $validacionesDoc,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            codigo: XmlReader::text($el, 'Codigo'),
            descripcion: XmlReader::text($el, 'Descripcion'),
            emisor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Emisor')),
            numeroDocumento: NumeroDocumento::fromXml(XmlReader::elementOrEmpty($el, 'NumeroDocumento')),
            receptor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Receptor')),
            referenciasDocumento: array_map(
                ReferenciaDocumento::fromXml(...),
                XmlReader::nestedElements($el, 'ReferenciasDocumento', 'ReferenciaDocumento'),
            ),
            uuid: XmlReader::text($el, 'UUID'),
            validacionesDoc: array_map(
                ValidacionDoc::fromXml(...),
                XmlReader::nestedElements($el, 'ValidacionesDoc', 'ValidacionDoc'),
            ),
        );
    }
}
