<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Event;

/**
 * Computes the Unique Electronic Document Code (CUDE) for ApplicationResponse events per DIAN's
 * Technical Annex 1.9, section 11.5. This is a different formula from the CUDE Cude\Cude
 * computes for Credit/Debit Notes — DIAN reuses the same name ("CUDE") for both, but the field
 * composition is not the same, so they live in separate classes. Mirrors event/event.go.
 */
final class Event
{
    /**
     * DIAN's response-code catalog for events (Technical Annex 1.9, section 13.3.1) — the five
     * values this library's Builder\EventBuilder knows how to produce. Not a general-purpose
     * catalog: it's exactly the five constants build*() each hardcodes internally.
     */
    public const RESPONSE_CODE_ACUSE_RECIBO = '030';
    public const RESPONSE_CODE_RECLAMO = '031';
    public const RESPONSE_CODE_RECIBIDO_BIEN = '032';
    public const RESPONSE_CODE_ACEPTACION_EXPRESA = '033';
    public const RESPONSE_CODE_ACEPTACION_TACITA = '034';

    private function __construct()
    {
    }

    /**
     * Calculates the CUDE of an ApplicationResponse event.
     *
     * $senderNit is the event generator's identification (Sender); $receiverNit is the
     * recipient's (Receiver). $documentId and $documentTypeCode belong to the REFERENCED
     * document (prefix+number and its own type code, e.g. "01"), not to the event itself.
     * $softwarePin is the DIAN-assigned software PIN, never part of the XML.
     *
     * Formula (section 11.5): SHA-384(Num_DE+Fec_Emi+Hor_Emi+NitFE+DocAdq+ResponseCode+ID+
     * DocumentTypeCode+Software-PIN). Verified against the annex's own worked example (11.5.1)
     * — see tests/Unit/EventTest.php.
     */
    public static function compute(
        string $numDe,
        string $issueDate,
        string $issueTime,
        string $senderNit,
        string $receiverNit,
        string $responseCode,
        string $documentId,
        string $documentTypeCode,
        string $softwarePin,
    ): string {
        $seed = $numDe . $issueDate . $issueTime . $senderNit . $receiverNit
            . $responseCode . $documentId . $documentTypeCode . $softwarePin;
        return hash('sha384', $seed);
    }

    /**
     * Builds the sworn-statement text DIAN requires in cbc:Note when registering Aceptación
     * Tácita (event "034"), section 6.5.5.7 — the "sin mandatario" template (a natural or legal
     * person sending the event directly, not through a proxy/mandatario). The annex also defines
     * a second "con mandatario" template for when a proxy sends the event on the issuer's
     * behalf; that variant isn't covered here yet, same as the Go original.
     *
     * $recibidoBienId/$recibidoBienCude identify the earlier Recibo del Bien y/o Servicio event
     * (responseCode "032") this statement refers to — DIAN requires that event to already exist
     * before Aceptación Tácita can be registered. $acquirerName/$acquirerNit identify the
     * acquirer who neither accepted, rejected, nor claimed against the invoice within the 3
     * business days.
     */
    public static function tacitAcceptanceNote(string $recibidoBienId, string $recibidoBienCude, string $acquirerName, string $acquirerNit): string
    {
        return sprintf(
            'Manifiesto bajo la gravedad de juramento que transcurridos 3 días hábiles contados desde la creación del Recibo de bienes y servicios %s con CUDE %s, el adquirente %s identificado con NIT %s no manifestó expresamente la aceptación o rechazo de la referida factura, ni reclamó en contra de su contenido.',
            $recibidoBienId,
            $recibidoBienCude,
            $acquirerName,
            $acquirerNit,
        );
    }
}
