<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * DIAN's validation response for the wrapped document, embedded in
 * cac:ParentDocumentLineReference. The technical annex requires at least one occurrence — the
 * AttachedDocument is an artifact produced after validation, not something built before it.
 * Mirrors domain.ValidationResult.
 */
final class ValidationResult
{
    public function __construct(
        /** Sequence number, normally "1". */
        public string $lineId = '',
        /** ID of the referenced document (prefix+number). */
        public string $documentId = '',
        /** Or CUDE. */
        public string $documentCufe = '',
        /** "CUFE-SHA384" or "CUDE-SHA384". */
        public string $documentHashType = '',
        public string $documentIssueDate = '',
        /** The full ApplicationResponse DIAN returned, verbatim, for the CDATA of
         *  cac:Attachment/cac:ExternalReference/cbc:Description. */
        public string $applicationResponseXml = '',
        /** Fixed: "Unidad Especial Dirección de Impuestos y Aduanas Nacionales". */
        public string $validatorId = '',
        /** e.g. "02". */
        public string $validationResultCode = '',
        public string $validationDate = '',
        public string $validationTime = '',
    ) {
    }
}
