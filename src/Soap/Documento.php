<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

use Cofacture\Soap\Internal\IntStringPair;
use Cofacture\Soap\Internal\XmlReader;
use DOMElement;

/** One entry of DocumentInfoResponse::$documentInfo. Mirrors soap.Documento (soap/types.go). */
final class Documento
{
    /**
     * @param Nota[] $documentTags
     * @param IntStringPair[] $estado
     * @param Evento[] $eventos
     * @param ReferenciaDocumento[] $referencias
     * @param ValidacionDoc[] $validacionesDoc
     */
    public function __construct(
        public readonly string $documentCode,
        public readonly string $documentDescription,
        public readonly array $documentTags,
        public readonly string $documentTypeId,
        public readonly string $documentTypeName,
        public readonly Entidad $emisor,
        public readonly array $estado,
        public readonly array $eventos,
        public readonly LegitimoTenedor $legitimoTenedor,
        public readonly NumeroDocumento $numeroDocumento,
        public readonly Entidad $receptor,
        public readonly array $referencias,
        public readonly TotalEImpuestos $totalEImpuestos,
        public readonly string $uuid,
        public readonly array $validacionesDoc,
    ) {
    }

    public static function fromXml(DOMElement $el): self
    {
        return new self(
            documentCode: XmlReader::text($el, 'DocumentCode'),
            documentDescription: XmlReader::text($el, 'DocumentDescription'),
            documentTags: array_map(Nota::fromXml(...), XmlReader::nestedElements($el, 'DocumentTags', 'Nota')),
            documentTypeId: XmlReader::text($el, 'DocumentTypeId'),
            documentTypeName: XmlReader::text($el, 'DocumentTypeName'),
            emisor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Emisor')),
            estado: array_map(IntStringPair::fromXml(...), XmlReader::nestedElements($el, 'Estado', 'KeyValueOfintstring')),
            eventos: array_map(Evento::fromXml(...), XmlReader::nestedElements($el, 'Eventos', 'Evento')),
            legitimoTenedor: LegitimoTenedor::fromXml(XmlReader::elementOrEmpty($el, 'LegitimoTenedor')),
            numeroDocumento: NumeroDocumento::fromXml(XmlReader::elementOrEmpty($el, 'NumeroDocumento')),
            receptor: Entidad::fromXml(XmlReader::elementOrEmpty($el, 'Receptor')),
            referencias: array_map(ReferenciaDocumento::fromXml(...), XmlReader::nestedElements($el, 'Referencias', 'ReferenciaDocumento')),
            totalEImpuestos: TotalEImpuestos::fromXml(XmlReader::elementOrEmpty($el, 'TotalEImpuestos')),
            uuid: XmlReader::text($el, 'UUID'),
            validacionesDoc: array_map(ValidacionDoc::fromXml(...), XmlReader::nestedElements($el, 'ValidacionesDoc', 'ValidacionDoc')),
        );
    }
}
