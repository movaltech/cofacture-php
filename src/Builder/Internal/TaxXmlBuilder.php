<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Xml\El;
use DOMElement;

/** Mirrors builder/tax.go. */
final class TaxXmlBuilder
{
    private function __construct()
    {
    }

    /**
     * Appends a cac:WithholdingTaxTotal for each withholding on the Support Document
     * (documentTypeCode "05") or its Adjustment Note ("95"). Unlike cac:TaxTotal, where every
     * subtype goes under the same element, each withholding generates its own
     * cac:WithholdingTaxTotal with exactly one cac:TaxSubtotal.
     *
     * @param \Cofacture\Domain\Tax[] $taxes
     */
    public static function appendWithholdingTaxTotal(DOMElement $parent, array $taxes, string $currency): void
    {
        $doc = $parent->ownerDocument;
        foreach ($taxes as $tax) {
            $wtt = El::create($doc, 'cac:WithholdingTaxTotal');
            $parent->appendChild($wtt);

            $amt = El::create($doc, 'cbc:TaxAmount', XmlFormat::amount($tax->taxAmountCents));
            $amt->setAttribute('currencyID', $currency);
            $wtt->appendChild($amt);

            $sub = El::create($doc, 'cac:TaxSubtotal');
            $wtt->appendChild($sub);

            $taxable = El::create($doc, 'cbc:TaxableAmount', XmlFormat::amount($tax->taxableAmountCents));
            $taxable->setAttribute('currencyID', $currency);
            $sub->appendChild($taxable);

            $taxAmt = El::create($doc, 'cbc:TaxAmount', XmlFormat::amount($tax->taxAmountCents));
            $taxAmt->setAttribute('currencyID', $currency);
            $sub->appendChild($taxAmt);

            $category = El::create($doc, 'cac:TaxCategory');
            $sub->appendChild($category);
            $category->appendChild(El::create($doc, 'cbc:Percent', XmlFormat::percent($tax->percent)));
            $category->appendChild(El::create($doc, 'cbc:TaxExemptionReason', '01'));
            $scheme = El::create($doc, 'cac:TaxScheme');
            $category->appendChild($scheme);
            $scheme->appendChild(El::create($doc, 'cbc:ID', $tax->typeCode));
            $scheme->appendChild(El::create($doc, 'cbc:Name', $tax->typeName));
        }
    }

    /**
     * Appends cac:TaxTotal (header or line level). Adds nothing if $taxes is empty, matching
     * the technical annex's requirement that TaxTotal is optional when no tax applies.
     *
     * @param \Cofacture\Domain\Tax[] $taxes
     */
    public static function appendTaxTotal(DOMElement $parent, array $taxes, string $currency): void
    {
        if ($taxes === []) {
            return;
        }
        $doc = $parent->ownerDocument;

        $totalCents = 0;
        foreach ($taxes as $tax) {
            $totalCents += $tax->taxAmountCents;
        }

        $taxTotal = El::create($doc, 'cac:TaxTotal');
        $parent->appendChild($taxTotal);
        $amt = El::create($doc, 'cbc:TaxAmount', XmlFormat::amount($totalCents));
        $amt->setAttribute('currencyID', $currency);
        $taxTotal->appendChild($amt);

        foreach ($taxes as $tax) {
            $sub = El::create($doc, 'cac:TaxSubtotal');
            $taxTotal->appendChild($sub);

            $taxable = El::create($doc, 'cbc:TaxableAmount', XmlFormat::amount($tax->taxableAmountCents));
            $taxable->setAttribute('currencyID', $currency);
            $sub->appendChild($taxable);

            $taxAmt = El::create($doc, 'cbc:TaxAmount', XmlFormat::amount($tax->taxAmountCents));
            $taxAmt->setAttribute('currencyID', $currency);
            $sub->appendChild($taxAmt);

            $category = El::create($doc, 'cac:TaxCategory');
            $sub->appendChild($category);
            $category->appendChild(El::create($doc, 'cbc:Percent', XmlFormat::percent($tax->percent)));
            $scheme = El::create($doc, 'cac:TaxScheme');
            $category->appendChild($scheme);
            $scheme->appendChild(El::create($doc, 'cbc:ID', $tax->typeCode));
            $scheme->appendChild(El::create($doc, 'cbc:Name', $tax->typeName));
        }
    }
}
