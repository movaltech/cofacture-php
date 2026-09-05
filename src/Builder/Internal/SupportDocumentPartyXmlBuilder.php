<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\Party;
use Cofacture\Xml\Namespaces as NS;
use Cofacture\Xml\El;
use DOMElement;

/**
 * Support Document party builders — deliberately not PartyXmlBuilder::appendAccountingParty,
 * which is the Invoice/Credit Note/Debit Note shape. Shared by SupportDocumentBuilder and
 * AdjustmentNoteBuilder (both use the same inverted-roles, simpler party structure). Mirrors the
 * appendDSSupplierParty/appendDSCustomerParty functions in builder/support_document.go.
 */
final class SupportDocumentPartyXmlBuilder
{
    private function __construct()
    {
    }

    /**
     * Builds AccountingSupplierParty for the Support Document's non-obligated third party (SNO).
     * DIAN requires schemeName="31" (NIT) for the SNO's CompanyID in all cases — including
     * natural persons — based on behavior verified (in the Go original) against a real,
     * DIAN-accepted Support Document. TaxLevelCode has no listName attribute (also verified
     * against the real document) — unlike PartyXmlBuilder, which does set one.
     */
    public static function appendSupplierParty(DOMElement $root, Party $p): void
    {
        $doc = $root->ownerDocument;
        $ap = El::create($doc, 'cac:AccountingSupplierParty');
        $root->appendChild($ap);
        $ap->appendChild(El::create($doc, 'cbc:AdditionalAccountID', $p->entityTypeCode));
        $party = El::create($doc, 'cac:Party');
        $ap->appendChild($party);

        $partyName = El::create($doc, 'cac:PartyName');
        $party->appendChild($partyName);
        $partyName->appendChild(El::create($doc, 'cbc:Name', $p->name));

        if ($p->address->line !== '') {
            $physicalLocation = El::create($doc, 'cac:PhysicalLocation');
            $party->appendChild($physicalLocation);
            $address = El::create($doc, 'cac:Address');
            $physicalLocation->appendChild($address);
            PartyXmlBuilder::appendAddressFields($address, $p->address);
        }

        $taxScheme = El::create($doc, 'cac:PartyTaxScheme');
        $party->appendChild($taxScheme);
        $taxScheme->appendChild(El::create($doc, 'cbc:RegistrationName', $p->name));
        $companyId = El::create($doc, 'cbc:CompanyID', $p->identification->number);
        $taxScheme->appendChild($companyId);
        $companyId->setAttribute('schemeAgencyID', NS::DIAN_SCHEME_AGENCY_ID);
        $companyId->setAttribute('schemeAgencyName', NS::DIAN_SCHEME_AGENCY_NAME);
        // DIAN requires schemeName="31" (NIT) for the non-obligated third party; schemeID is
        // the check digit.
        if ($p->identification->verificationCode !== '') {
            $companyId->setAttribute('schemeID', $p->identification->verificationCode);
        }
        $companyId->setAttribute('schemeName', '31');
        if ($p->liabilityCodes !== []) {
            $taxScheme->appendChild(El::create($doc, 'cbc:TaxLevelCode', implode(';', $p->liabilityCodes)));
        }
        $scheme = El::create($doc, 'cac:TaxScheme');
        $taxScheme->appendChild($scheme);
        $scheme->appendChild(El::create($doc, 'cbc:ID', $p->taxSchemeCode));
        $scheme->appendChild(El::create($doc, 'cbc:Name', $p->taxSchemeName));
    }

    /**
     * Builds AccountingCustomerParty for the Support Document's purchasing/issuing company
     * (ABS). The issuer carries ONLY PartyTaxScheme (plus PartyName/PhysicalLocation) — no
     * PartyIdentification, RegistrationAddress, PartyLegalEntity, or Contact. TaxLevelCode
     * carries no listName attribute. Verified (in the Go original) against DIAN's Support
     * Document Toolkit v1.1.
     */
    public static function appendCustomerParty(DOMElement $root, Party $p): void
    {
        $doc = $root->ownerDocument;
        $ap = El::create($doc, 'cac:AccountingCustomerParty');
        $root->appendChild($ap);
        $ap->appendChild(El::create($doc, 'cbc:AdditionalAccountID', $p->entityTypeCode));
        $party = El::create($doc, 'cac:Party');
        $ap->appendChild($party);

        $partyName = El::create($doc, 'cac:PartyName');
        $party->appendChild($partyName);
        $partyName->appendChild(El::create($doc, 'cbc:Name', $p->name));

        if ($p->address->line !== '') {
            $physicalLocation = El::create($doc, 'cac:PhysicalLocation');
            $party->appendChild($physicalLocation);
            $address = El::create($doc, 'cac:Address');
            $physicalLocation->appendChild($address);
            PartyXmlBuilder::appendAddressFields($address, $p->address);
        }

        $taxScheme = El::create($doc, 'cac:PartyTaxScheme');
        $party->appendChild($taxScheme);
        $taxScheme->appendChild(El::create($doc, 'cbc:RegistrationName', $p->name));
        $companyId = El::create($doc, 'cbc:CompanyID', $p->identification->number);
        $taxScheme->appendChild($companyId);
        PartyXmlBuilder::setIdentificationAttrs($companyId, $p->identification);
        if ($p->liabilityCodes !== []) {
            $taxScheme->appendChild(El::create($doc, 'cbc:TaxLevelCode', implode(';', $p->liabilityCodes)));
        }
        $scheme = El::create($doc, 'cac:TaxScheme');
        $taxScheme->appendChild($scheme);
        $scheme->appendChild(El::create($doc, 'cbc:ID', $p->taxSchemeCode));
        $scheme->appendChild(El::create($doc, 'cbc:Name', $p->taxSchemeName));
    }
}
