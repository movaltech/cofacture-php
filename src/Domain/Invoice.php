<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * The model for an Electronic Sales Invoice (Factura Electrónica de Venta, InvoiceTypeCode
 * "01"). Mirrors domain.Invoice (domain/invoice.go) field for field.
 *
 * cufe, softwareSecurityCode and qrUrl are left empty when building the document: they are
 * computed in later pipeline steps (Cufe, Qr) from these same fields and injected into the
 * already-built XML before signing — see the Go original's package doc comment in
 * domain/types.go for the full boundary this class mirrors: no catalog validation happens
 * here, this package only knows where a code goes in the XML, not whether it is valid.
 *
 * Unlike the smaller value objects in this namespace, Invoice is intentionally mutable
 * (plain public properties, not readonly) because the pipeline sets cufe/softwareSecurityCode/
 * qrUrl on it after construction — exactly like the Go struct does.
 *
 * Not final: CreditNote and DebitNote extend it. Go's domain.CreditNote/domain.DebitNote embed
 * domain.Invoice specifically to promote its ~20 fields instead of repeating them (see their
 * own doc comments) — PHP has no struct embedding, so inheritance is the closest equivalent
 * that keeps the same direct-field-access ergonomics ($creditNote->supplier, not
 * $creditNote->invoice->supplier) everywhere else in this codebase already uses.
 */
class Invoice
{
    /**
     * @param PaymentMean[] $paymentMeans
     * @param Tax[] $headerTaxes Header-level TaxTotal entries (one per tax type, aggregating
     *   all lines). Computing these totals is the responsibility of whoever builds the model.
     * @param Tax[] $withholdingTaxes Withholdings (cac:WithholdingTaxTotal) — exclusive to the
     *   Support Document (documentTypeCode "05") and its Adjustment Note ("95"). Each element
     *   generates an independent cac:WithholdingTaxTotal (one per type: ReteIVA="05",
     *   ReteRenta="06"). Ignored for Invoice/CreditNote/DebitNote.
     * @param Line[] $lines
     */
    public function __construct(
        public string $profileId = '',
        /** "1" production, "2" certification/testing (also used as the UUID's schemeID) */
        public string $environmentCode = '',
        /** Operation type catalog, e.g. "10" = Standard */
        public string $operationTypeCode = '',
        /** "01" national sales invoice */
        public string $documentTypeCode = '',
        /** "CUFE-SHA384", used as the UUID's schemeName */
        public string $hashType = '',
        public string $prefix = '',
        public string $number = '',
        /** YYYY-MM-DD */
        public string $issueDate = '',
        /** HH:MM:SS-05:00 */
        public string $issueTime = '',
        public string $dueDate = '',
        /** YYYY-MM-DD. The Support Document's acquisition period start (cac:InvoicePeriod).
         *  Empty → the builder falls back to issueDate. Ignored for Invoice/CreditNote/DebitNote. */
        public string $periodStartDate = '',
        /** YYYY-MM-DD. Currently unused by any builder (cac:InvoicePeriod only ever serializes
         *  a start date, per the technical annex) — kept for parity with the Go original's field. */
        public string $periodEndDate = '',
        public string $note = '',
        /** currency_codes catalog, ISO 4217, e.g. "COP" */
        public string $currencyCode = '',
        public string $orderReferenceNumber = '',
        public Party $supplier = new Party(),
        public Party $customer = new Party(),
        public array $paymentMeans = [],
        public array $headerTaxes = [],
        public array $withholdingTaxes = [],
        public Totals $totals = new Totals(),
        public array $lines = [],
        public NumberingRange $numberingRange = new NumberingRange(),
        public SoftwareProvider $softwareProvider = new SoftwareProvider(),
        public string $cufe = '',
        public string $softwareSecurityCode = '',
        public string $qrUrl = '',
    ) {
    }
}
