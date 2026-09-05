<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\DocumentType;
use Cofacture\Domain\Totals;
use Cofacture\Xml\El;
use DOMElement;

/** Mirrors builder/monetary_total.go. */
final class MonetaryTotalXmlBuilder
{
    private function __construct()
    {
    }

    /**
     * Appends the totals block. $node is "LegalMonetaryTotal" for Invoice/CreditNote,
     * "RequestedMonetaryTotal" for DebitNote (same content, different element name).
     */
    public static function appendMonetaryTotal(
        DOMElement $parent,
        string $node,
        Totals $totals,
        string $currency,
        DocumentType $documentTypeCode,
    ): void {
        $doc = $parent->ownerDocument;
        $el = El::create($doc, 'cac:' . $node);
        $parent->appendChild($el);

        $line = El::create($doc, 'cbc:LineExtensionAmount', XmlFormat::amount($totals->lineExtensionCents));
        $line->setAttribute('currencyID', $currency);
        $el->appendChild($line);

        $taxExcl = El::create($doc, 'cbc:TaxExclusiveAmount', XmlFormat::amount($totals->taxExclusiveCents));
        $taxExcl->setAttribute('currencyID', $currency);
        $el->appendChild($taxExcl);

        $taxIncl = El::create($doc, 'cbc:TaxInclusiveAmount', XmlFormat::amount($totals->taxInclusiveCents));
        $taxIncl->setAttribute('currencyID', $currency);
        $el->appendChild($taxIncl);

        if ($documentTypeCode === DocumentType::Invoice && $totals->prepaidCents > 0) {
            $prepaid = El::create($doc, 'cbc:PrepaidAmount', XmlFormat::amount($totals->prepaidCents));
            $prepaid->setAttribute('currencyID', $currency);
            $el->appendChild($prepaid);
        }

        // PayableRoundingAmount is the only monetary field the annex allows negative (e.g. a
        // POS total rounded to the nearest 50/100 pesos) — omitted entirely when zero, same as
        // PrepaidAmount.
        if ($totals->roundingCents !== 0) {
            $rounding = El::create($doc, 'cbc:PayableRoundingAmount', XmlFormat::amount($totals->roundingCents));
            $rounding->setAttribute('currencyID', $currency);
            $el->appendChild($rounding);
        }

        $payable = El::create($doc, 'cbc:PayableAmount', XmlFormat::amount($totals->payableCents));
        $payable->setAttribute('currencyID', $currency);
        $el->appendChild($payable);
    }
}
