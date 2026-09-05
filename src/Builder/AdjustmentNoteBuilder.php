<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder;

use Cofacture\Builder\Internal\ExtensionsXmlBuilder;
use Cofacture\Builder\Internal\LineItemXmlBuilder;
use Cofacture\Builder\Internal\MonetaryTotalXmlBuilder;
use Cofacture\Builder\Internal\NotesXmlBuilder;
use Cofacture\Builder\Internal\PaymentMeanXmlBuilder;
use Cofacture\Builder\Internal\SupportDocumentPartyXmlBuilder;
use Cofacture\Builder\Internal\TaxXmlBuilder;
use Cofacture\Domain\AdjustmentNote;
use Cofacture\Domain\Identification;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;

/**
 * Builds the XML tree of an Adjustment Note to the Support Document (documentTypeCode "95").
 * Uses CreditNote as the root element (same as a regular Credit Note/91) with the CreditNote-2
 * namespace — verified (in the Go original) against DIAN's official NotaDeAjuste.xml example.
 * Roles are inverted just like the Support Document: Supplier = SNO (non-obligated third
 * party), Customer = ABS. Mirrors builder.BuildAdjustmentNote (builder/adjustment_note.go).
 */
final class AdjustmentNoteBuilder
{
    private function __construct()
    {
    }

    public static function build(AdjustmentNote $an): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = false;

        $root = $doc->createElementNS(NS::NS_CREDIT_NOTE, 'CreditNote');
        $doc->appendChild($root);
        El::declareNamespace($root, '', NS::NS_CREDIT_NOTE);
        El::declareNamespace($root, 'cac', NS::NS_CAC);
        El::declareNamespace($root, 'cbc', NS::NS_CBC);
        El::declareNamespace($root, 'ds', NS::NS_DS);
        El::declareNamespace($root, 'ext', NS::NS_EXT);
        El::declareNamespace($root, 'sts', NS::NS_STS);
        El::declareNamespace($root, 'xades', NS::NS_XADES);
        El::declareNamespace($root, 'xades141', NS::NS_XADES141);
        El::declareNamespace($root, 'xsi', NS::NS_XSI);
        $root->setAttributeNS(NS::NS_XSI, 'xsi:schemaLocation', NS::SCHEMA_LOCATION_CREDIT_NOTE);

        // appendUBLExtensions handles InvoiceControl for documentTypeCode "05"/"95" (Support
        // Document family) — ExtensionsXmlBuilder gates on "01"/"05" only, so InvoiceControl is
        // correctly omitted here (documentTypeCode "95"), matching the Go original.
        ExtensionsXmlBuilder::appendUBLExtensions($root, $an);

        $root->appendChild(El::create($doc, 'cbc:UBLVersionID', 'UBL 2.1'));
        $root->appendChild(El::create($doc, 'cbc:CustomizationID', $an->operationTypeCode));
        $root->appendChild(El::create($doc, 'cbc:ProfileID', $an->profileId));
        $root->appendChild(El::create($doc, 'cbc:ProfileExecutionID', $an->environmentCode->value));
        $root->appendChild(El::create($doc, 'cbc:ID', $an->prefix . $an->number));

        $uuid = El::create($doc, 'cbc:UUID', $an->cufe);
        $root->appendChild($uuid);
        $uuid->setAttribute('schemeID', $an->environmentCode->value);
        $uuid->setAttribute('schemeName', $an->hashType); // "CUDS-SHA384"

        $root->appendChild(El::create($doc, 'cbc:IssueDate', $an->issueDate));
        $root->appendChild(El::create($doc, 'cbc:IssueTime', $an->issueTime));
        $root->appendChild(El::create($doc, 'cbc:CreditNoteTypeCode', $an->documentTypeCode->value)); // "95"
        if ($an->note !== '') {
            $root->appendChild(El::create($doc, 'cbc:Note', $an->note));
        }

        // N/A: DocumentCurrencyCode with no list attributes (same as the Support Document — DSFC03).
        $root->appendChild(El::create($doc, 'cbc:DocumentCurrencyCode', $an->currencyCode));

        $root->appendChild(El::create($doc, 'cbc:LineCountNumeric', (string) count($an->lines)));

        // DiscrepancyResponse goes before BillingReference (same order as Credit/Debit Note).
        if ($an->discrepancyResponse !== null) {
            NotesXmlBuilder::appendDiscrepancyResponse($root, $an->discrepancyResponse);
        }
        // BillingReference points to the original Support Document — the UUID carries
        // schemeName="CUDS-SHA384".
        NotesXmlBuilder::appendSupportDocumentBillingReference($root, $an->billingReference);

        // Roles inverted just like the Support Document: Supplier = non-obligated third party,
        // Customer = ABS.
        SupportDocumentPartyXmlBuilder::appendSupplierParty($root, $an->supplier);
        SupportDocumentPartyXmlBuilder::appendCustomerParty($root, $an->customer);

        foreach ($an->paymentMeans as $paymentMean) {
            PaymentMeanXmlBuilder::appendPaymentMean($root, $paymentMean);
        }

        TaxXmlBuilder::appendTaxTotal($root, $an->headerTaxes, $an->currencyCode);
        TaxXmlBuilder::appendWithholdingTaxTotal($root, $an->withholdingTaxes, $an->currencyCode);
        MonetaryTotalXmlBuilder::appendMonetaryTotal($root, 'LegalMonetaryTotal', $an->totals, $an->currencyCode, $an->documentTypeCode);

        $linePeriodDate = $an->periodStartDate !== '' ? $an->periodStartDate : $an->issueDate;
        $emptyMandanteId = new Identification();
        foreach ($an->lines as $i => $line) {
            LineItemXmlBuilder::appendDocumentLine(
                $root,
                'CreditNoteLine',
                'CreditedQuantity',
                $i + 1,
                $line,
                $an->currencyCode,
                $an->documentTypeCode,
                $emptyMandanteId,
                $linePeriodDate,
            );
        }

        return $doc;
    }
}
