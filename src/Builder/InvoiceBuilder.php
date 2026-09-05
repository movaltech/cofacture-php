<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder;

use Cofacture\Builder\Internal\ExtensionsXmlBuilder;
use Cofacture\Builder\Internal\LineItemXmlBuilder;
use Cofacture\Builder\Internal\MonetaryTotalXmlBuilder;
use Cofacture\Builder\Internal\PartyXmlBuilder;
use Cofacture\Builder\Internal\PaymentMeanXmlBuilder;
use Cofacture\Builder\Internal\TaxXmlBuilder;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;

/**
 * Assembles the UBL 2.1 + DIAN-extension XML tree from an Invoice. Does not sign, hash, or
 * send anything. Mirrors builder.BuildInvoice (builder/invoice_builder.go).
 *
 * The resulting document still has no real CUFE, SoftwareSecurityCode or QRURL if the Invoice
 * it received had them empty — those values are computed in earlier pipeline steps (Cufe, Qr)
 * from this same model and must be set before calling build().
 *
 * The root element and every descendant are created via createElementNS/Xml\El (never plain
 * createElement with a prefixed string) — see Xml\El's doc comment for why that distinction is
 * load-bearing for this port specifically, not just a style preference.
 */
final class InvoiceBuilder
{
    private function __construct()
    {
    }

    public static function build(Invoice $inv): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = false;

        $root = $doc->createElementNS(NS::NS_INVOICE_DEFAULT, 'Invoice');
        $doc->appendChild($root);
        El::declareNamespace($root, '', NS::NS_INVOICE_DEFAULT);
        El::declareNamespace($root, 'cac', NS::NS_CAC);
        El::declareNamespace($root, 'cbc', NS::NS_CBC);
        El::declareNamespace($root, 'ds', NS::NS_DS);
        El::declareNamespace($root, 'ext', NS::NS_EXT);
        El::declareNamespace($root, 'sts', NS::NS_STS);
        El::declareNamespace($root, 'xades', NS::NS_XADES);
        El::declareNamespace($root, 'xades141', NS::NS_XADES141);
        El::declareNamespace($root, 'xsi', NS::NS_XSI);
        $root->setAttributeNS(NS::NS_XSI, 'xsi:schemaLocation', NS::SCHEMA_LOCATION_INVOICE);

        ExtensionsXmlBuilder::appendUBLExtensions($root, $inv);

        $root->appendChild(El::create($doc, 'cbc:UBLVersionID', 'UBL 2.1'));
        $root->appendChild(El::create($doc, 'cbc:CustomizationID', $inv->operationTypeCode));
        $root->appendChild(El::create($doc, 'cbc:ProfileID', $inv->profileId));
        $root->appendChild(El::create($doc, 'cbc:ProfileExecutionID', $inv->environmentCode));
        $root->appendChild(El::create($doc, 'cbc:ID', $inv->prefix . $inv->number));

        $uuid = El::create($doc, 'cbc:UUID', $inv->cufe);
        $root->appendChild($uuid);
        $uuid->setAttribute('schemeID', $inv->environmentCode);
        $uuid->setAttribute('schemeName', $inv->hashType);

        $root->appendChild(El::create($doc, 'cbc:IssueDate', $inv->issueDate));
        $root->appendChild(El::create($doc, 'cbc:IssueTime', $inv->issueTime));
        if ($inv->dueDate !== '') {
            $root->appendChild(El::create($doc, 'cbc:DueDate', $inv->dueDate));
        }
        $root->appendChild(El::create($doc, 'cbc:InvoiceTypeCode', $inv->documentTypeCode));
        if ($inv->note !== '') {
            $root->appendChild(El::create($doc, 'cbc:Note', $inv->note));
        }

        $currency = El::create($doc, 'cbc:DocumentCurrencyCode', $inv->currencyCode);
        $root->appendChild($currency);
        $currency->setAttribute('listAgencyID', '6');
        $currency->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $currency->setAttribute('listID', 'ISO 4217 Alpha');

        $root->appendChild(El::create($doc, 'cbc:LineCountNumeric', (string) count($inv->lines)));

        if ($inv->orderReferenceNumber !== '') {
            $orderRef = El::create($doc, 'cac:OrderReference');
            $root->appendChild($orderRef);
            $orderRef->appendChild(El::create($doc, 'cbc:ID', $inv->orderReferenceNumber));
        }

        PartyXmlBuilder::appendAccountingParty($root, 'AccountingSupplierParty', $inv->supplier, true, $inv->prefix, true);
        PartyXmlBuilder::appendAccountingParty($root, 'AccountingCustomerParty', $inv->customer, false, '', false);

        foreach ($inv->paymentMeans as $paymentMean) {
            PaymentMeanXmlBuilder::appendPaymentMean($root, $paymentMean);
        }

        TaxXmlBuilder::appendTaxTotal($root, $inv->headerTaxes, $inv->currencyCode);
        MonetaryTotalXmlBuilder::appendMonetaryTotal($root, 'LegalMonetaryTotal', $inv->totals, $inv->currencyCode, $inv->documentTypeCode);

        $emptyMandanteId = new Identification();
        foreach ($inv->lines as $i => $line) {
            // mandanteId does not apply to Invoice (only notes use it); passed empty.
            LineItemXmlBuilder::appendDocumentLine(
                $root,
                'InvoiceLine',
                'InvoicedQuantity',
                $i + 1,
                $line,
                $inv->currencyCode,
                $inv->documentTypeCode,
                $emptyMandanteId,
                '',
            );
        }

        return $doc;
    }
}
