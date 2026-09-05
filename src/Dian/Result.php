<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Dian;

use Cofacture\Domain\ValidationResult;
use Cofacture\Soap\DianResponse;
use RuntimeException;

/**
 * Interprets DIAN's validation responses (Soap\DianResponse) and turns them into something the
 * rest of the pipeline can use directly. Mirrors the dian package (dian/parser.go).
 *
 * Verified (in the Go original) against a real GetStatusZip response: xmlBase64Bytes arrives as
 * base64 text that must be decoded to reach the actual ApplicationResponse — DIAN's own
 * character data is never auto-decoded by the SOAP layer (see Soap\DianResponse's doc comment),
 * so this class's decode step is the only place that happens. xmlBytes, when DIAN uses it
 * instead of xmlBase64Bytes, arrives already as the final content and needs no decode step.
 */
final class Result
{
    /** The fixed literal DIAN uses as the validator identifier, confirmed both in the technical
     *  annex example and in a real response. */
    public const VALIDATOR_ID = 'Unidad Especial Dirección de Impuestos y Aduanas Nacionales';

    /** @param Message[] $messages */
    public function __construct(
        public readonly bool $isValid = false,
        public readonly string $statusCode = '',
        public readonly string $statusDescription = '',
        public readonly string $statusMessage = '',
        public readonly array $messages = [],
        /** The identifier of the document this response applies to (the CUFE/CUDE of the
         *  validated invoice/note), not the embedded ApplicationResponse's own identifier. */
        public readonly string $xmlDocumentKey = '',
        public readonly string $xmlFileName = '',
        /**
         * The decoded XML of the ApplicationResponse DIAN issued during validation, ready to
         * use as ValidationResult::$applicationResponseXml. Empty string if the response
         * carried none.
         */
        public readonly string $applicationResponseXml = '',
    ) {
    }

    /** True if any message is an actual rejection (not just a notice). */
    public function hasRejections(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->isRejection()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detects DIAN's specific response for when the test-set identifier (used in
     * sendTestSetAsync) has already been certified/closed on their end — distinct from an
     * actual rejection of the document's content, which arrives via $messages, not here.
     * Confirmed (in the Go original) against a real response: no messages, statusCode "2",
     * statusDescription "Set de prueba con identificador <uuid> se encuentra Aceptado." —
     * callers should retry via sendBillSync instead of treating this as a genuine document
     * rejection.
     */
    public function isTestSetClosed(): bool
    {
        return str_contains($this->statusDescription, 'Set de prueba')
            && str_contains($this->statusDescription, 'se encuentra Aceptado');
    }

    /**
     * Converts a Soap\DianResponse (one element of what getStatusZip() returns, or the result
     * of getStatus()) into a Result.
     */
    public static function interpret(DianResponse $resp): self
    {
        return new self(
            isValid: $resp->isValid,
            statusCode: $resp->statusCode,
            statusDescription: $resp->statusDescription,
            statusMessage: $resp->statusMessage,
            messages: array_map(Message::parse(...), $resp->errorMessages),
            xmlDocumentKey: $resp->xmlDocumentKey,
            xmlFileName: $resp->xmlFileName,
            applicationResponseXml: self::decodeEmbeddedXml($resp),
        );
    }

    private static function decodeEmbeddedXml(DianResponse $resp): string
    {
        if ($resp->xmlBase64Bytes !== '') {
            $decoded = base64_decode($resp->xmlBase64Bytes, true);
            if ($decoded === false) {
                throw new RuntimeException('dian: decode embedded XML: xmlBase64Bytes is not valid base64');
            }
            return $decoded;
        }
        if ($resp->xmlBytes !== '') {
            return $resp->xmlBytes;
        }
        return '';
    }

    /**
     * Builds a ValidationResult ready for Builder\AttachedDocumentBuilder. DIAN's response does
     * not repeat the document's own ID/date nor the moment the query was made, so the caller
     * supplies them.
     */
    public function toValidationResult(
        string $lineId,
        string $documentId,
        string $documentHashType,
        string $documentIssueDate,
        string $validationDate,
        string $validationTime,
    ): ValidationResult {
        return new ValidationResult(
            lineId: $lineId,
            documentId: $documentId,
            documentCufe: $this->xmlDocumentKey,
            documentHashType: $documentHashType,
            documentIssueDate: $documentIssueDate,
            applicationResponseXml: $this->applicationResponseXml,
            validatorId: self::VALIDATOR_ID,
            validationResultCode: $this->statusCode,
            validationDate: $validationDate,
            validationTime: $validationTime,
        );
    }
}
