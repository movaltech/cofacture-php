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
use Cofacture\Domain\DebitNote;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;

/**
 * Builds the XML tree of a Debit Note. Nearly identical to CreditNoteBuilder — the real
 * differences are: there's no equivalent to cbc:CreditNoteTypeCode, the totals block is named
 * cac:RequestedMonetaryTotal instead of cac:LegalMonetaryTotal, and the customer does carry
 * PhysicalLocation (unlike Invoice/CreditNote, where it doesn't apply). Mirrors
 * builder.BuildDebitNote (builder/debit_note.go).
 */
final class DebitNoteBuilder
{
    private function __construct()
    {
    }

    public static function build(DebitNote $dn): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = false;

        $root = $doc->createElementNS(NS::NS_DEBIT_NOTE, 'DebitNote');
        $doc->appendChild($root);
        El::declareNamespace($root, '', NS::NS_DEBIT_NOTE);
        El::declareNamespace($root, 'cac', NS::NS_CAC);
        El::declareNamespace($root, 'cbc', NS::NS_CBC);
        El::declareNamespace($root, 'ds', NS::NS_DS);
        El::declareNamespace($root, 'ext', NS::NS_EXT);
        El::declareNamespace($root, 'sts', NS::NS_STS);
        El::declareNamespace($root, 'xades', NS::NS_XADES);
        El::declareNamespace($root, 'xades141', NS::NS_XADES141);
        El::declareNamespace($root, 'xsi', NS::NS_XSI);
        $root->setAttributeNS(NS::NS_XSI, 'xsi:schemaLocation', NS::SCHEMA_LOCATION_DEBIT_NOTE);

        ExtensionsXmlBuilder::appendUBLExtensions($root, $dn);

        $root->appendChild(El::create($doc, 'cbc:UBLVersionID', 'UBL 2.1'));
        $root->appendChild(El::create($doc, 'cbc:CustomizationID', $dn->operationTypeCode));
        $root->appendChild(El::create($doc, 'cbc:ProfileID', $dn->profileId));
        $root->appendChild(El::create($doc, 'cbc:ProfileExecutionID', $dn->environmentCode));
        $root->appendChild(El::create($doc, 'cbc:ID', $dn->prefix . $dn->number));

        $uuid = El::create($doc, 'cbc:UUID', $dn->cufe);
        $root->appendChild($uuid);
        $uuid->setAttribute('schemeID', $dn->environmentCode);
        $uuid->setAttribute('schemeName', $dn->hashType);

        $root->appendChild(El::create($doc, 'cbc:IssueDate', $dn->issueDate));
        $root->appendChild(El::create($doc, 'cbc:IssueTime', $dn->issueTime));
        if ($dn->note !== '') {
            $root->appendChild(El::create($doc, 'cbc:Note', $dn->note));
        }

        $currency = El::create($doc, 'cbc:DocumentCurrencyCode', $dn->currencyCode);
        $root->appendChild($currency);
        $currency->setAttribute('listAgencyID', '6');
        $currency->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $currency->setAttribute('listID', 'ISO 4217 Alpha');

        $root->appendChild(El::create($doc, 'cbc:LineCountNumeric', (string) count($dn->lines)));

        if ($dn->discrepancyResponse !== null) {
            NotesXmlBuilder::appendDiscrepancyResponse($root, $dn->discrepancyResponse);
        }
        NotesXmlBuilder::appendBillingReference($root, 'InvoiceDocumentReference', $dn->billingReference);

        PartyXmlBuilder::appendAccountingParty($root, 'AccountingSupplierParty', $dn->supplier, true, $dn->prefix, true);
        PartyXmlBuilder::appendAccountingParty($root, 'AccountingCustomerParty', $dn->customer, false, '', true);

        foreach ($dn->paymentMeans as $paymentMean) {
            PaymentMeanXmlBuilder::appendPaymentMean($root, $paymentMean);
        }

        TaxXmlBuilder::appendTaxTotal($root, $dn->headerTaxes, $dn->currencyCode);
        MonetaryTotalXmlBuilder::appendMonetaryTotal($root, 'RequestedMonetaryTotal', $dn->totals, $dn->currencyCode, $dn->documentTypeCode);

        foreach ($dn->lines as $i => $line) {
            LineItemXmlBuilder::appendDocumentLine(
                $root,
                'DebitNoteLine',
                'DebitedQuantity',
                $i + 1,
                $line,
                $dn->currencyCode,
                $dn->documentTypeCode,
                $dn->supplier->identification,
                '',
            );
        }

        return $doc;
    }
}
