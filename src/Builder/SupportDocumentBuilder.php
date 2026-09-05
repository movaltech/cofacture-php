<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder;

use Cofacture\Builder\Internal\ExtensionsXmlBuilder;
use Cofacture\Builder\Internal\LineItemXmlBuilder;
use Cofacture\Builder\Internal\MonetaryTotalXmlBuilder;
use Cofacture\Builder\Internal\PaymentMeanXmlBuilder;
use Cofacture\Builder\Internal\SupportDocumentPartyXmlBuilder;
use Cofacture\Builder\Internal\TaxXmlBuilder;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;

/**
 * Builds the XML tree of a Support Document for purchases made from non-obligated third parties
 * (documentTypeCode "05", CUDS-SHA384). Mirrors builder.BuildSupportDocument
 * (builder/support_document.go).
 *
 * The structure is Invoice (same root element and namespaces as InvoiceBuilder) with three key
 * differences:
 *  1. Inverted roles: Supplier = non-obligated third party (who sells to the issuer), Customer
 *     = the issuing company (who acquires and generates the document).
 *  2. cac:WithholdingTaxTotal instead of (or in addition to) cac:TaxTotal for withholdings.
 *  3. InvoiceControl present (the Support Document has its own DIAN resolution, just like the
 *     Invoice — see ExtensionsXmlBuilder, which already gates this on documentTypeCode "01" or
 *     "05").
 *
 * There is no dedicated SupportDocument domain class, same as the Go original: this takes a
 * plain Invoice (documentTypeCode "05") directly — the caller is responsible for computing the
 * CUDS (Cuds\Cuds), SoftwareSecurityCode and QR URL before calling build(); $inv's fields are
 * serialized as-is.
 *
 * DIAN business rule confirmed against a real submission (2026-09-02, rules DSAK25 "El
 * contenido de este atributo no corresponde a 31", DSAK24b "El DV del NIT del adquiriente no es
 * correcto", and DSAD06 "Valor del CUDS no está calculado correctamente"): the issuing
 * company's own $inv->customer->identification->typeCode (the "adquiriente"/acquirer, since
 * roles are inverted here) must be "31" (NIT) — same requirement as DebitNote's supplier side
 * (see Domain/DebitNote.php), but on the opposite party. The Supplier side (the SNO) does not
 * need this from the caller — SupportDocumentPartyXmlBuilder::appendSupplierParty already
 * forces schemeName="31" unconditionally. This class does not enforce it for the Customer side;
 * the caller is responsible for setting it correctly, or DIAN will also report the CUDS itself
 * as miscalculated (it's computed from the same identification fields that get serialized).
 */
final class SupportDocumentBuilder
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

        // appendUBLExtensions handles InvoiceControl for documentTypeCode "05".
        ExtensionsXmlBuilder::appendUBLExtensions($root, $inv);

        $root->appendChild(El::create($doc, 'cbc:UBLVersionID', 'UBL 2.1'));
        $root->appendChild(El::create($doc, 'cbc:CustomizationID', $inv->operationTypeCode)); // "10" or "11"
        $root->appendChild(El::create($doc, 'cbc:ProfileID', $inv->profileId));
        $root->appendChild(El::create($doc, 'cbc:ProfileExecutionID', $inv->environmentCode->value));
        $root->appendChild(El::create($doc, 'cbc:ID', $inv->prefix . $inv->number));

        $uuid = El::create($doc, 'cbc:UUID', $inv->cufe); // the CUDS is stored in cufe
        $root->appendChild($uuid);
        $uuid->setAttribute('schemeID', $inv->environmentCode->value);
        $uuid->setAttribute('schemeName', $inv->hashType); // "CUDS-SHA384"

        $root->appendChild(El::create($doc, 'cbc:IssueDate', $inv->issueDate));
        $root->appendChild(El::create($doc, 'cbc:IssueTime', $inv->issueTime));
        if ($inv->dueDate !== '') {
            $root->appendChild(El::create($doc, 'cbc:DueDate', $inv->dueDate));
        }
        $root->appendChild(El::create($doc, 'cbc:InvoiceTypeCode', $inv->documentTypeCode->value)); // "05"
        if ($inv->note !== '') {
            $root->appendChild(El::create($doc, 'cbc:Note', $inv->note));
        }

        // Support Document: DocumentCurrencyCode with no list attributes (DSFC03 — the DS schema rejects them).
        $root->appendChild(El::create($doc, 'cbc:DocumentCurrencyCode', $inv->currencyCode));

        $root->appendChild(El::create($doc, 'cbc:LineCountNumeric', (string) count($inv->lines)));

        // Inverted roles: Supplier = non-obligated third party, Customer = the purchasing/
        // issuing company. The Support Document uses simpler party structures than the
        // Invoice — dedicated builder per role.
        SupportDocumentPartyXmlBuilder::appendSupplierParty($root, $inv->supplier);
        SupportDocumentPartyXmlBuilder::appendCustomerParty($root, $inv->customer);

        foreach ($inv->paymentMeans as $paymentMean) {
            PaymentMeanXmlBuilder::appendPaymentMean($root, $paymentMean);
        }

        TaxXmlBuilder::appendTaxTotal($root, $inv->headerTaxes, $inv->currencyCode);
        TaxXmlBuilder::appendWithholdingTaxTotal($root, $inv->withholdingTaxes, $inv->currencyCode);
        MonetaryTotalXmlBuilder::appendMonetaryTotal($root, 'LegalMonetaryTotal', $inv->totals, $inv->currencyCode, $inv->documentTypeCode);

        // Per-line period date: the document's periodStartDate if specified, otherwise
        // issueDate. cac:InvoicePeriod is required on every InvoiceLine of the Support Document
        // (DSFC01).
        $linePeriodDate = $inv->periodStartDate !== '' ? $inv->periodStartDate : $inv->issueDate;
        $emptyMandanteId = new Identification();
        foreach ($inv->lines as $i => $line) {
            LineItemXmlBuilder::appendDocumentLine(
                $root,
                'InvoiceLine',
                'InvoicedQuantity',
                $i + 1,
                $line,
                $inv->currencyCode,
                $inv->documentTypeCode,
                $emptyMandanteId,
                $linePeriodDate,
            );
        }

        return $doc;
    }
}
