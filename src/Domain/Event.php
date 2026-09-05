<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The shared model for every DIAN "evento" (ApplicationResponse): Acuse de Recibo, Recibo del
 * Bien y/o Servicio, Aceptación Expresa, Aceptación Tácita, and Reclamo (which extends Event —
 * see Reclamo's own doc comment). The responseCode/description literals each event type
 * requires are fixed by the annex, not caller data — Builder\EventBuilder's build*() methods set
 * them; the caller doesn't. Mirrors domain.Event.
 *
 * For all events except Aceptación Tácita ("034"), sender is the receiver/acquirer of the
 * referenced document and receiver is its issuer — roles are inverted for Aceptación Tácita,
 * where the issuer generates the event and DIAN itself is the recipient (section 6.5.5.7).
 *
 * Not final: Reclamo extends it — see CreditNote's own doc comment on Invoice for why
 * inheritance is this port's equivalent to Go's struct embedding.
 */
class Event
{
    public function __construct(
        public string $environmentCode = '',
        /** The event's own consecutive number (AAD05). */
        public string $id = '',
        public string $issueDate = '',
        public string $issueTime = '',
        /**
         * Optional for most events; DIAN requires a specific templated statement for Aceptación
         * Tácita ("034") — see Event\Event::tacitAcceptanceNote() to build it correctly.
         */
        public string $note = '',
        public EventDocumentReference $documentReference = new EventDocumentReference(),
        public EventParty $sender = new EventParty(),
        public EventParty $receiver = new EventParty(),
        /** Acuse de Recibo only; null otherwise. */
        public ?EventReceiverPerson $receiverPerson = null,
        public SoftwareProvider $softwareProvider = new SoftwareProvider(),
        public string $cude = '',
        public string $softwareSecurityCode = '',
        /**
         * Built from the REFERENCED document's CUFE (Qr::url($environmentCode,
         * $documentReference->cufe)), not from this event's own CUDE — section 6.5.4, AAB36.
         */
        public string $qrUrl = '',
    ) {
    }
}
