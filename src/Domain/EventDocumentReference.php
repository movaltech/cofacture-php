<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * Identifies the document (Invoice/CreditNote/DebitNote/SupportDocument) an event applies to
 * (cac:DocumentResponse/cac:DocumentReference, sections 6.5.4/6.5.5). Mirrors
 * domain.EventDocumentReference.
 */
final class EventDocumentReference
{
    public function __construct(
        public string $prefix = '',
        public string $number = '',
        /** Or CUDE/CUDS, depending on the referenced document's own type. */
        public string $cufe = '',
        /** "CUFE-SHA384" / "CUDE-SHA384" / "CUDS-SHA384". */
        public string $hashType = '',
        /** The referenced document's own type code, e.g. "01". */
        public DocumentType $documentTypeCode = DocumentType::Invoice,
    ) {
    }
}
