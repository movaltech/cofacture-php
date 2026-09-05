<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\DocumentType;
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
        DocumentType $documentTypeCode,
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

        if ($documentTypeCode !== DocumentType::DebitNote) {
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
        // The closed set of document types whose Item carries InformationContentProviderParty/
        // PowerOfAttorney/AgentParty (the "Mandante"/principal's identification) instead of
        // StandardItemIdentification: Credit Note, Debit Note, and their Documento Equivalente
        // Electrónico Adjustment Note equivalents (PosDebitAdjustment/PosCreditAdjustment,
        // Technical Annex Documento Equivalente Electrónico V1.0 section 16.3). Every other
        // document type — Invoice, Support Document, Adjustment Note to the Support Document,
        // and every Documento Equivalente Electrónico type not yet modeled as its own
        // DocumentType case (20/25/27/30/35/40/45/50/55/60, same annex) — uses
        // StandardItemIdentification instead. This mirrors a real fix in the Go original: it used
        // to be an allowlist ("01"/"05"/"95" defaulting everything else to Mandante), which
        // silently broke the first time a new primary-document type code was introduced
        // (Documento Equivalente Electrónico "20" fell into the Mandante branch by accident —
        // caught by Go's builder/pos_test.go, not by inspection). MVP scope only builds "01"
        // today, but this `match` with an explicit `default => false` keeps the same
        // closed-allowlist shape (and the same landmine avoidance) as the array it replaces.
        $usesMandante = match ($documentTypeCode) {
            DocumentType::CreditNote, DocumentType::DebitNote, DocumentType::PosDebitAdjustment, DocumentType::PosCreditAdjustment => true,
            default => false,
        };
        if ($usesMandante) {
            // Each level is appended to its (already-attached) parent immediately, before its
            // own children are created — never build the whole chain detached and attach only
            // the outermost element at the end. Confirmed by direct experiment: a detached
            // createElementNS() node keeps its own namespace declaration even after being
            // reattached under an ancestor that already declares the same prefix/URI, so
            // building bottom-up-then-attach-once produced a real, confirmed divergence from
            // Go's output — a redundant xmlns:cbc repeated on every level of this exact chain
            // (PartyIdentification, AgentParty, PowerOfAttorney, cbc:ID) — even though the
            // schema-valid XML never affects signing (only the document root/KeyInfo/
            // SignedProperties subtrees are ever canonicalized).
            $infoProvider = El::create($doc, 'cac:InformationContentProviderParty');
            $item->appendChild($infoProvider);
            $powerOfAttorney = El::create($doc, 'cac:PowerOfAttorney');
            $infoProvider->appendChild($powerOfAttorney);
            $agentParty = El::create($doc, 'cac:AgentParty');
            $powerOfAttorney->appendChild($agentParty);
            $partyIdentification = El::create($doc, 'cac:PartyIdentification');
            $agentParty->appendChild($partyIdentification);
            $agentId = El::create($doc, 'cbc:ID', $mandanteId->number);
            PartyXmlBuilder::setIdentificationAttrs($agentId, $mandanteId);
            $partyIdentification->appendChild($agentId);
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
