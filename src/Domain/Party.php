<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * A supplier or customer (AccountingSupplierParty / AccountingCustomerParty).
 * Mirrors domain.Party (domain/types.go).
 */
final class Party
{
    /**
     * @param string[] $liabilityCodes DIAN tax_level_codes catalog (e.g. O-13, O-15, O-47).
     *   DIAN allows more than one per third party — serialized joined with ";" in a single
     *   cbc:TaxLevelCode by the builder, not one element per code.
     * @param string[] $industryClassificationCodes CIIU codes (DANE catalog, not DIAN). Only
     *   applies to the supplier, never the customer. Serialized joined with ";" by the builder.
     */
    public function __construct(
        /** "1" natural person, "2" legal entity */
        public string $entityTypeCode = '',
        public Identification $identification = new Identification(),
        public string $name = '',
        /** Omitted from the XML if line is empty */
        public Address $address = new Address(),
        /** type_regimes catalog, used as the listName of TaxLevelCode */
        public string $taxRegimeCode = '',
        public array $liabilityCodes = [],
        public array $industryClassificationCodes = [],
        public string $taxSchemeCode = '',
        public string $taxSchemeName = '',
        public string $phone = '',
        public string $email = '',
        /**
         * Chamber of Commerce ("Cámara de Comercio") registration number. Null if not
         * applicable (always null for the customer). The cbc:ID of CorporateRegistrationScheme
         * is NOT this number: it is the invoice prefix.
         */
        public ?string $merchantRegistrationNumber = null,
    ) {
    }
}
