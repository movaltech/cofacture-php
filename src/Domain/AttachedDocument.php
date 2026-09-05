<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The electronic container delivered to the acquirer: it wraps the signed document
 * (Invoice/CreditNote/DebitNote) together with DIAN's validation response. Mirrors
 * domain.AttachedDocument.
 */
final class AttachedDocument
{
    /** @param ValidationResult[] $validationResults */
    public function __construct(
        public Environment $environmentCode = Environment::Habilitacion,
        /**
         * The generator's own consecutive number (AE04b) — it is NOT the wrapped document's
         * CUFE; the two are different values even though some providers conflate them in
         * practice.
         */
        public string $id = '',
        /** Container generation date (>= the wrapped document's issueDate). */
        public string $issueDate = '',
        public string $issueTime = '',
        /** The cbc:ID of the wrapped document (prefix+number), not of the container itself. */
        public string $parentDocumentId = '',
        public AttachedPartyInfo $sender = new AttachedPartyInfo(),
        public AttachedPartyInfo $receiver = new AttachedPartyInfo(),
        /**
         * The signed XML of the wrapped document (Invoice/CreditNote/DebitNote), verbatim, for
         * the CDATA of cac:Attachment/cac:ExternalReference/cbc:Description.
         */
        public string $attachmentXml = '',
        public array $validationResults = [],
    ) {
    }
}
