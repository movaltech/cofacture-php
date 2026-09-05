<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * A Credit Note. Shares almost every field with Invoice — UBL treats them as distinct
 * documents, but what actually changes is the document type, the reference to the corrected
 * invoice, and the reason. That's why this extends Invoice instead of repeating ~20 fields
 * (mirrors domain.CreditNote's use of Go struct embedding — see Invoice's own doc comment for
 * why inheritance is the PHP equivalent here).
 *
 * The inherited $cufe property actually holds this note's CUDE (the same slot DIAN uses —
 * cbc:UUID — for any of the electronic documents; $hashType is what distinguishes
 * "CUFE-SHA384" from "CUDE-SHA384", not the property name).
 */
final class CreditNote extends Invoice
{
    /** @param PaymentMean[] $paymentMeans
     *  @param Tax[] $headerTaxes
     *  @param Line[] $lines */
    public function __construct(
        string $profileId = '',
        Environment $environmentCode = Environment::Habilitacion,
        string $operationTypeCode = '',
        DocumentType $documentTypeCode = DocumentType::CreditNote,
        string $hashType = '',
        string $prefix = '',
        string $number = '',
        string $issueDate = '',
        string $issueTime = '',
        string $dueDate = '',
        string $note = '',
        string $currencyCode = '',
        string $orderReferenceNumber = '',
        Party $supplier = new Party(),
        Party $customer = new Party(),
        array $paymentMeans = [],
        array $headerTaxes = [],
        Totals $totals = new Totals(),
        array $lines = [],
        NumberingRange $numberingRange = new NumberingRange(),
        SoftwareProvider $softwareProvider = new SoftwareProvider(),
        string $cufe = '',
        string $softwareSecurityCode = '',
        string $qrUrl = '',
        /** Fixed DIAN code for a Credit Note (document type); the concept catalog entry (List
         *  22) goes in $discrepancyResponse instead. */
        public string $creditNoteTypeCode = '',
        public BillingReference $billingReference = new BillingReference(),
        /** Required when $operationTypeCode is "20" (references a specific invoice); null
         *  otherwise. */
        public ?DiscrepancyResponse $discrepancyResponse = null,
    ) {
        parent::__construct(
            profileId: $profileId,
            environmentCode: $environmentCode,
            operationTypeCode: $operationTypeCode,
            documentTypeCode: $documentTypeCode,
            hashType: $hashType,
            prefix: $prefix,
            number: $number,
            issueDate: $issueDate,
            issueTime: $issueTime,
            dueDate: $dueDate,
            note: $note,
            currencyCode: $currencyCode,
            orderReferenceNumber: $orderReferenceNumber,
            supplier: $supplier,
            customer: $customer,
            paymentMeans: $paymentMeans,
            headerTaxes: $headerTaxes,
            totals: $totals,
            lines: $lines,
            numberingRange: $numberingRange,
            softwareProvider: $softwareProvider,
            cufe: $cufe,
            softwareSecurityCode: $softwareSecurityCode,
            qrUrl: $qrUrl,
        );
    }
}
