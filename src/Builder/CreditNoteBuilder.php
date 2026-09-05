<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder;

use Cofacture\Builder\Internal\ExtensionsXmlBuilder;
use Cofacture\Builder\Internal\LineItemXmlBuilder;
use Cofacture\Builder\Internal\MonetaryTotalXmlBuilder;
use Cofacture\Builder\Internal\NotesXmlBuilder;
use Cofacture\Builder\Internal\PartyXmlBuilder;
use Cofacture\Builder\Internal\PaymentMeanXmlBuilder;
use Cofacture\Builder\Internal\TaxXmlBuilder;
use Cofacture\Domain\CreditNote;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;

/**
 * Builds the XML tree of a Credit Note. Reuses the same infrastructure as Invoice (DIAN
 * extensions, parties, taxes, totals, lines) — the structure is nearly identical; what changes
 * is the root element, the document type, and the required reference to the corrected invoice.
 * Mirrors builder.BuildCreditNote (builder/credit_note.go).
 */
final class CreditNoteBuilder
{
    private function __construct()
    {
    }

    public static function build(CreditNote $cn): DOMDocument
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

        ExtensionsXmlBuilder::appendUBLExtensions($root, $cn);

        $root->appendChild(El::create($doc, 'cbc:UBLVersionID', 'UBL 2.1'));
        $root->appendChild(El::create($doc, 'cbc:CustomizationID', $cn->operationTypeCode));
        $root->appendChild(El::create($doc, 'cbc:ProfileID', $cn->profileId));
        $root->appendChild(El::create($doc, 'cbc:ProfileExecutionID', $cn->environmentCode));
        $root->appendChild(El::create($doc, 'cbc:ID', $cn->prefix . $cn->number));

        $uuid = El::create($doc, 'cbc:UUID', $cn->cufe);
        $root->appendChild($uuid);
        $uuid->setAttribute('schemeID', $cn->environmentCode);
        $uuid->setAttribute('schemeName', $cn->hashType);

        $root->appendChild(El::create($doc, 'cbc:IssueDate', $cn->issueDate));
        $root->appendChild(El::create($doc, 'cbc:IssueTime', $cn->issueTime));
        $root->appendChild(El::create($doc, 'cbc:CreditNoteTypeCode', $cn->creditNoteTypeCode));
        if ($cn->note !== '') {
            $root->appendChild(El::create($doc, 'cbc:Note', $cn->note));
        }

        $currency = El::create($doc, 'cbc:DocumentCurrencyCode', $cn->currencyCode);
        $root->appendChild($currency);
        $currency->setAttribute('listAgencyID', '6');
        $currency->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $currency->setAttribute('listID', 'ISO 4217 Alpha');

        $root->appendChild(El::create($doc, 'cbc:LineCountNumeric', (string) count($cn->lines)));

        if ($cn->discrepancyResponse !== null) {
            NotesXmlBuilder::appendDiscrepancyResponse($root, $cn->discrepancyResponse);
        }
        NotesXmlBuilder::appendBillingReference($root, 'InvoiceDocumentReference', $cn->billingReference);

        PartyXmlBuilder::appendAccountingParty($root, 'AccountingSupplierParty', $cn->supplier, true, $cn->prefix, true);
        PartyXmlBuilder::appendAccountingParty($root, 'AccountingCustomerParty', $cn->customer, false, '', false);

        foreach ($cn->paymentMeans as $paymentMean) {
            PaymentMeanXmlBuilder::appendPaymentMean($root, $paymentMean);
        }

        TaxXmlBuilder::appendTaxTotal($root, $cn->headerTaxes, $cn->currencyCode);
        MonetaryTotalXmlBuilder::appendMonetaryTotal($root, 'LegalMonetaryTotal', $cn->totals, $cn->currencyCode, $cn->documentTypeCode);

        foreach ($cn->lines as $i => $line) {
            LineItemXmlBuilder::appendDocumentLine(
                $root,
                'CreditNoteLine',
                'CreditedQuantity',
                $i + 1,
                $line,
                $cn->currencyCode,
                $cn->documentTypeCode,
                $cn->supplier->identification,
                '',
            );
        }

        return $doc;
    }
}
