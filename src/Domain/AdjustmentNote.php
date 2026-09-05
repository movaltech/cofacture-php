<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The Adjustment Note to the Support Document (documentTypeCode "95"). It is to the Support
 * Document (type "05") what Credit/Debit Notes are to the Invoice: it allows correcting or
 * voiding a previously issued Support Document. The roles are the same as in the Support
 * Document: Supplier = non-obligated third party (SNO), Customer = the purchasing/issuing
 * company (ABS). Uses CUDS-SHA384 (same formula as the Support Document). Mirrors
 * domain.AdjustmentNote — see CreditNote's own doc comment for why this extends Invoice instead
 * of repeating its fields.
 *
 * The inherited $cufe property holds this note's CUDS (schemeName "CUDS-SHA384").
 */
final class AdjustmentNote extends Invoice
{
    /** @param PaymentMean[] $paymentMeans
     *  @param Tax[] $headerTaxes
     *  @param Tax[] $withholdingTaxes
     *  @param Line[] $lines */
    public function __construct(
        string $profileId = '',
        string $environmentCode = '',
        string $operationTypeCode = '',
        string $documentTypeCode = '',
        string $hashType = '',
        string $prefix = '',
        string $number = '',
        string $issueDate = '',
        string $issueTime = '',
        string $dueDate = '',
        string $periodStartDate = '',
        string $periodEndDate = '',
        string $note = '',
        string $currencyCode = '',
        string $orderReferenceNumber = '',
        Party $supplier = new Party(),
        Party $customer = new Party(),
        array $paymentMeans = [],
        array $headerTaxes = [],
        array $withholdingTaxes = [],
        Totals $totals = new Totals(),
        array $lines = [],
        NumberingRange $numberingRange = new NumberingRange(),
        SoftwareProvider $softwareProvider = new SoftwareProvider(),
        string $cufe = '',
        string $softwareSecurityCode = '',
        string $qrUrl = '',
        /** Reference to the original Support Document (UUID with schemeName "CUDS-SHA384"). */
        public BillingReference $billingReference = new BillingReference(),
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
            periodStartDate: $periodStartDate,
            periodEndDate: $periodEndDate,
            note: $note,
            currencyCode: $currencyCode,
            orderReferenceNumber: $orderReferenceNumber,
            supplier: $supplier,
            customer: $customer,
            paymentMeans: $paymentMeans,
            headerTaxes: $headerTaxes,
            withholdingTaxes: $withholdingTaxes,
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
