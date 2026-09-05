<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/**
 * An entry of Documento::$documentTags. Despite the generic name, the real WSDL gives it full
 * party and correction-concept data — it is not free-text. Mirrors soap.Nota.
 */
final class Nota
{
    /** @param ValidacionDoc[] $validacionesDoc */
    public function __construct(
        public readonly ConceptoCorreccion $conceptoCorreccion,
        public readonly Entidad $emisor,
        public readonly LegitimoTenedor $legitimoTenedor,
        public readonly string $nombreTipoDocumento,
        public readonly NumeroDocumento $numeroDocumento,
        public readonly Entidad $receptor,
        public readonly string $uuid,
        public readonly array $validacionesDoc,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            conceptoCorreccion: ConceptoCorreccion::fromXml(XmlReader::elementOrEmpty($el, 'ConceptoCorreccion')),
            emisor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Emisor')),
            legitimoTenedor: LegitimoTenedor::fromXml(XmlReader::elementOrEmpty($el, 'LegitimoTenedor')),
            nombreTipoDocumento: XmlReader::text($el, 'NombreTipoDocumento'),
            numeroDocumento: NumeroDocumento::fromXml(XmlReader::elementOrEmpty($el, 'NumeroDocumento')),
            receptor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Receptor')),
            uuid: XmlReader::text($el, 'UUID'),
            validacionesDoc: array_map(
                ValidacionDoc::fromXml(...),
                XmlReader::nestedElements($el, 'ValidacionesDoc', 'ValidacionDoc'),
            ),
        );
    }
}
