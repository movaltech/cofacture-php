<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\Identification;
use Cofacture\Domain\Line;
use Cofacture\Xml\El;
use DOMElement;

/**
 * Mirrors builder/line_items.go. MVP scope: the Invoice-only path
 * (documentTypeCode === "01", StandardItemIdentification branch). The "mandante" branch for
 * notes and the invoicePeriodStartDate branch for the Support Document arrive with their
 * respective document types — the parameters are already shaped to accept them so
 * InvoiceBuilder doesn't need to change when that happens.
 */
final class LineItemXmlBuilder
{
    /**
     * The closed set of document types whose Item carries InformationContentProviderParty/
     * PowerOfAttorney/AgentParty (the "Mandante"/principal's identification) instead of
     * StandardItemIdentification: Credit Note ("91"), Debit Note ("92"), and their Documento
     * Equivalente Electrónico Adjustment Note equivalents ("93"/"94", Technical Annex Documento
     * Equivalente Electrónico V1.0 section 16.3). Every other document type — Invoice, Support
     * Document, Adjustment Note to the Support Document, and every Documento Equivalente
     * Electrónico type (20/25/27/30/35/40/45/50/55/60, same annex) — uses
     * StandardItemIdentification instead. This mirrors a real fix in the Go original: it used to
     * be an allowlist ("01"/"05"/"95" defaulting everything else to Mandante), which silently
     * broke the first time a new primary-document type code was introduced (Documento
     * Equivalente Electrónico "20" fell into the Mandante branch by accident — caught by Go's
     * builder/pos_test.go, not by inspection). MVP scope only builds "01" today, but this keeps
     * the same landmine from being ported along with everything else.
     */
    private const DOCUMENT_TYPE_CODES_USING_MANDANTE = ['91' => true, '92' => true, '93' => true, '94' => true];

    private function __construct()
    {
    }

    public static function appendDocumentLine(
        DOMElement $parent,
        string $node,
        string $nodeQty,
        int $index,
        Line $line,
        string $currency,
        string $documentTypeCode,
        Identification $mandanteId,
        string $invoicePeriodStartDate,
    ): void {
        $doc = $parent->ownerDocument;
        $el = El::create($doc, 'cac:' . $node);
        $parent->appendChild($el);
        $el->appendChild(El::create($doc, 'cbc:ID', (string) $index));

        $qty = El::create($doc, 'cbc:' . $nodeQty, XmlFormat::quantity($line->quantity));
        $qty->setAttribute('unitCode', $line->unitCode);
        $el->appendChild($qty);

        $lineExt = El::create($doc, 'cbc:LineExtensionAmount', XmlFormat::amount($line->lineExtensionCents));
        $lineExt->setAttribute('currencyID', $currency);
        $el->appendChild($lineExt);

        if ($documentTypeCode !== '92') {
            $el->appendChild(El::create($doc, 'cbc:FreeOfChargeIndicator', XmlFormat::bool($line->freeOfCharge)));
        }

        if ($line->freeOfCharge && $line->referencePrice !== null) {
            $pricingRef = El::create($doc, 'cac:PricingReference');
            $el->appendChild($pricingRef);
            $altPrice = El::create($doc, 'cac:AlternativeConditionPrice');
            $pricingRef->appendChild($altPrice);
            $price = El::create($doc, 'cbc:PriceAmount', XmlFormat::amount($line->referencePrice->priceAmountCents));
            $price->setAttribute('currencyID', $currency);
            $altPrice->appendChild($price);
            $altPrice->appendChild(El::create($doc, 'cbc:PriceTypeCode', $line->referencePrice->typeCode));
        }

        if ($invoicePeriodStartDate !== '') {
            $ip = El::create($doc, 'cac:InvoicePeriod');
            $el->appendChild($ip);
            $ip->appendChild(El::create($doc, 'cbc:StartDate', $invoicePeriodStartDate));
            $ip->appendChild(El::create($doc, 'cbc:DescriptionCode', '1'));
            $ip->appendChild(El::create($doc, 'cbc:Description', 'Por operación'));
        }

        TaxXmlBuilder::appendTaxTotal($el, $line->taxes, $currency);

        $item = El::create($doc, 'cac:Item');
        $el->appendChild($item);
        $item->appendChild(El::create($doc, 'cbc:Description', $line->description));
        if (self::DOCUMENT_TYPE_CODES_USING_MANDANTE[$documentTypeCode] ?? false) {
            $agentId = El::create($doc, 'cbc:ID', $mandanteId->number);
            PartyXmlBuilder::setIdentificationAttrs($agentId, $mandanteId);
            $partyIdentification = El::create($doc, 'cac:PartyIdentification');
            $partyIdentification->appendChild($agentId);
            $agentParty = El::create($doc, 'cac:AgentParty');
            $agentParty->appendChild($partyIdentification);
            $powerOfAttorney = El::create($doc, 'cac:PowerOfAttorney');
            $powerOfAttorney->appendChild($agentParty);
            $infoProvider = El::create($doc, 'cac:InformationContentProviderParty');
            $infoProvider->appendChild($powerOfAttorney);
            $item->appendChild($infoProvider);
        } else {
            $sii = El::create($doc, 'cbc:ID', $line->itemCode);
            $standardItemId = El::create($doc, 'cac:StandardItemIdentification');
            $item->appendChild($standardItemId);
            $standardItemId->appendChild($sii);
            $sii->setAttribute('schemeID', $line->itemTypeCode);
            $sii->setAttribute('schemeName', $line->itemTypeName);
            // schemeAgencyID is only added when provided — code "999" (the taxpayer's own
            // standard, table 13.3.5) explicitly requires this attribute be omitted entirely.
            if ($line->itemTypeAgencyId !== '') {
                $sii->setAttribute('schemeAgencyID', $line->itemTypeAgencyId);
            }
        }

        $price = El::create($doc, 'cac:Price');
        $el->appendChild($price);
        $priceAmt = El::create($doc, 'cbc:PriceAmount', XmlFormat::amount($line->unitPriceCents));
        $priceAmt->setAttribute('currencyID', $currency);
        $price->appendChild($priceAmt);
        $baseQty = El::create($doc, 'cbc:BaseQuantity', XmlFormat::quantity($line->quantity));
        $baseQty->setAttribute('unitCode', $line->unitCode);
        $price->appendChild($baseQty);
    }
}
