<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\Address;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Party;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMElement;

/** Mirrors builder/party.go. */
final class PartyXmlBuilder
{
    private function __construct()
    {
    }

    /**
     * Appends AccountingSupplierParty or AccountingCustomerParty. $showMerchantRegistration
     * should only be true for the supplier (CorporateRegistrationScheme is the Chamber of
     * Commerce registration, it doesn't apply to the customer). $invoicePrefix is ignored when
     * $showMerchantRegistration is false.
     *
     * Verified (in the Go original) against real invoices: cac:CorporateRegistrationScheme/
     * cbc:ID is the invoice prefix (not something intrinsic to the third party), and cbc:Name
     * is the mercantile registration number — omitted if the third party doesn't have one.
     */
    public static function appendAccountingParty(
        DOMElement $parent,
        string $node,
        Party $party,
        bool $showMerchantRegistration,
        string $invoicePrefix,
        bool $showPhysicalLocation,
    ): void {
        $doc = $parent->ownerDocument;
        $root = El::create($doc, 'cac:' . $node);
        $parent->appendChild($root);
        $root->appendChild(El::create($doc, 'cbc:AdditionalAccountID', $party->entityTypeCode));

        $partyEl = El::create($doc, 'cac:Party');
        $root->appendChild($partyEl);

        // cbc:IndustryClassificationCode (CIIU) goes before PartyIdentification/PartyName in
        // the cac:Party sequence — confirmed against UBL-CommonAggregateComponents-2.1.xsd in
        // the Go original. Only the supplier carries it.
        if ($showMerchantRegistration && $party->industryClassificationCodes !== []) {
            $partyEl->appendChild(El::create(
                $doc,
                'cbc:IndustryClassificationCode',
                implode(';', $party->industryClassificationCodes),
            ));
        }

        if ($party->entityTypeCode === '2') {
            $partyIdentification = El::create($doc, 'cac:PartyIdentification');
            $partyEl->appendChild($partyIdentification);
            $partyId = El::create($doc, 'cbc:ID', $party->identification->number);
            self::setIdentificationAttrs($partyId, $party->identification);
            $partyIdentification->appendChild($partyId);
        }

        $partyName = El::create($doc, 'cac:PartyName');
        $partyEl->appendChild($partyName);
        $partyName->appendChild(El::create($doc, 'cbc:Name', $party->name));

        if ($showPhysicalLocation && $party->address->line !== '') {
            $physicalLocation = El::create($doc, 'cac:PhysicalLocation');
            $partyEl->appendChild($physicalLocation);
            $address = El::create($doc, 'cac:Address');
            $physicalLocation->appendChild($address);
            self::appendAddressFields($address, $party->address);
        }

        $taxScheme = El::create($doc, 'cac:PartyTaxScheme');
        $partyEl->appendChild($taxScheme);
        $taxScheme->appendChild(El::create($doc, 'cbc:RegistrationName', $party->name));
        $companyId = El::create($doc, 'cbc:CompanyID', $party->identification->number);
        self::setIdentificationAttrs($companyId, $party->identification);
        $taxScheme->appendChild($companyId);
        if ($party->liabilityCodes !== []) {
            $tlc = El::create($doc, 'cbc:TaxLevelCode', implode(';', $party->liabilityCodes));
            $tlc->setAttribute('listName', $party->taxRegimeCode);
            $taxScheme->appendChild($tlc);
        }
        if ($party->address->line !== '') {
            $regAddr = El::create($doc, 'cac:RegistrationAddress');
            $taxScheme->appendChild($regAddr);
            self::appendAddressFields($regAddr, $party->address);
        }
        $scheme = El::create($doc, 'cac:TaxScheme');
        $taxScheme->appendChild($scheme);
        $scheme->appendChild(El::create($doc, 'cbc:ID', $party->taxSchemeCode));
        $scheme->appendChild(El::create($doc, 'cbc:Name', $party->taxSchemeName));

        $legal = El::create($doc, 'cac:PartyLegalEntity');
        $partyEl->appendChild($legal);
        $legal->appendChild(El::create($doc, 'cbc:RegistrationName', $party->name));
        $legalId = El::create($doc, 'cbc:CompanyID', $party->identification->number);
        self::setIdentificationAttrs($legalId, $party->identification);
        $legal->appendChild($legalId);
        if ($showMerchantRegistration) {
            $crs = El::create($doc, 'cac:CorporateRegistrationScheme');
            $legal->appendChild($crs);
            $crs->appendChild(El::create($doc, 'cbc:ID', $invoicePrefix));
            if ($party->merchantRegistrationNumber !== null) {
                $crs->appendChild(El::create($doc, 'cbc:Name', $party->merchantRegistrationNumber));
            }
        }

        $contact = El::create($doc, 'cac:Contact');
        $partyEl->appendChild($contact);
        $contact->appendChild(El::create($doc, 'cbc:Telephone', $party->phone));
        $contact->appendChild(El::create($doc, 'cbc:ElectronicMail', $party->email));
    }

    public static function setIdentificationAttrs(DOMElement $element, Identification $identification): void
    {
        $element->setAttribute('schemeAgencyID', NS::DIAN_SCHEME_AGENCY_ID);
        $element->setAttribute('schemeAgencyName', NS::DIAN_SCHEME_AGENCY_NAME);
        if ($identification->typeCode === NS::IDENTIFICATION_TYPE_NIT) {
            $element->setAttribute('schemeID', $identification->verificationCode);
        }
        $element->setAttribute('schemeName', $identification->typeCode);
    }

    public static function appendAddressFields(DOMElement $addr, Address $address): void
    {
        $doc = $addr->ownerDocument;
        if ($address->cityCode !== '') {
            $addr->appendChild(El::create($doc, 'cbc:ID', $address->cityCode));
            $addr->appendChild(El::create($doc, 'cbc:CityName', $address->cityName));
        }
        if ($address->postalZone !== '') {
            $addr->appendChild(El::create($doc, 'cbc:PostalZone', $address->postalZone));
        }
        if ($address->stateCode !== '') {
            $addr->appendChild(El::create($doc, 'cbc:CountrySubentity', $address->stateName));
            $addr->appendChild(El::create($doc, 'cbc:CountrySubentityCode', $address->stateCode));
        }
        $addressLine = El::create($doc, 'cac:AddressLine');
        $addr->appendChild($addressLine);
        $addressLine->appendChild(El::create($doc, 'cbc:Line', $address->line));
        if ($address->countryCode !== '') {
            $country = El::create($doc, 'cac:Country');
            $addr->appendChild($country);
            $country->appendChild(El::create($doc, 'cbc:IdentificationCode', $address->countryCode));
            $name = El::create($doc, 'cbc:Name', $address->countryName);
            $name->setAttribute('languageID', 'es');
            $country->appendChild($name);
        }
    }
}
