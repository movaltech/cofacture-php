<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * A Debit Note. Structurally identical to CreditNote — the real differences from the builder's
 * perspective are that it uses cac:RequestedMonetaryTotal instead of cac:LegalMonetaryTotal,
 * and the technical annex defines no equivalent to cbc:CreditNoteTypeCode for Debit Note
 * (verified in the Go original: no such element appears between DocumentCurrencyCode and
 * LineCountNumeric in section 8.4 of the annex). Mirrors domain.DebitNote — see CreditNote's
 * own doc comment for why this extends Invoice instead of repeating its fields.
 *
 * The inherited $cufe property holds this note's CUDE (schemeName "CUDE-SHA384").
 */
final class DebitNote extends Invoice
{
    /** @param PaymentMean[] $paymentMeans
     *  @param Tax[] $headerTaxes
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
        public BillingReference $billingReference = new BillingReference(),
        /** Required when $operationTypeCode is "30" (references a specific invoice); null
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
