<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The extra data the Reclamo event (responseCode "031") requires on top of Event: the rejection
 * reason, from DIAN's catalog (section 13.3.11). This library doesn't validate catalog codes —
 * same boundary as DiscrepancyResponse elsewhere. Mirrors domain.Reclamo — see CreditNote's own
 * doc comment for why this extends Event instead of repeating its fields.
 */
final class Reclamo extends Event
{
    public function __construct(
        Environment $environmentCode = Environment::Habilitacion,
        string $id = '',
        string $issueDate = '',
        string $issueTime = '',
        string $note = '',
        EventDocumentReference $documentReference = new EventDocumentReference(),
        EventParty $sender = new EventParty(),
        EventParty $receiver = new EventParty(),
        ?EventReceiverPerson $receiverPerson = null,
        SoftwareProvider $softwareProvider = new SoftwareProvider(),
        string $cude = '',
        string $softwareSecurityCode = '',
        string $qrUrl = '',
        /** cac:Response/cbc:ResponseCode/@listID */
        public string $rejectionListId = '',
        /** cac:Response/cbc:ResponseCode/@name */
        public string $rejectionName = '',
    ) {
        parent::__construct(
            environmentCode: $environmentCode,
            id: $id,
            issueDate: $issueDate,
            issueTime: $issueTime,
            note: $note,
            documentReference: $documentReference,
            sender: $sender,
            receiver: $receiver,
            receiverPerson: $receiverPerson,
            softwareProvider: $softwareProvider,
            cude: $cude,
            softwareSecurityCode: $softwareSecurityCode,
            qrUrl: $qrUrl,
        );
    }
}
