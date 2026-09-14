<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Xml;

/**
 * UBL 2.1 + DIAN extension namespace constants shared by all builders.
 *
 * Mirrors github.com/movaltech/cofacture/xml/namespaces.go — same constant names/values on
 * purpose, so the two implementations stay directly comparable field by field.
 */
final class Namespaces
{
    private function __construct()
    {
        // Static-only class, not meant to be instantiated — no equivalent to Go's package-level
        // constants exists in PHP, this is the closest idiomatic substitute.
    }

    public const NS_INVOICE_DEFAULT = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    public const NS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
    public const NS_DEBIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2';
    public const NS_ATTACHED_DOCUMENT = 'urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2';
    public const NS_APPLICATION_RESPONSE = 'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2';
    public const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    public const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    public const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    public const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    public const NS_STS = 'dian:gov:co:facturaelectronica:Structures-2-1';
    public const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    public const NS_XADES141 = 'http://uri.etsi.org/01903/v1.4.1#';
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public const SCHEMA_LOCATION_INVOICE = self::NS_INVOICE_DEFAULT
        . ' http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd';
    public const SCHEMA_LOCATION_CREDIT_NOTE = self::NS_CREDIT_NOTE
        . ' http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd';
    public const SCHEMA_LOCATION_DEBIT_NOTE = self::NS_DEBIT_NOTE
        . ' http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-DebitNote-2.1.xsd';
    /**
     * Follows the standard UBL 2.1 maindoc namespace pattern (same shape as
     * SCHEMA_LOCATION_INVOICE) — unlike the rest of this file, it has not been cross-checked
     * against a real, DIAN-accepted ApplicationResponse event document, only against the
     * Technical Annex's field tables (section 6.5.4) and the OASIS UBL 2.1 schema. Matches the
     * same caveat in the Go original.
     */
    public const SCHEMA_LOCATION_APPLICATION_RESPONSE = self::NS_APPLICATION_RESPONSE
        . ' http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-ApplicationResponse-2.1.xsd';

    /** DIAN's fixed NIT as the authorization provider. */
    public const DIAN_AUTHORIZATION_PROVIDER_ID = '800197268';
    /** Identifies DIAN as the schema (catalog) agency. */
    public const DIAN_SCHEME_AGENCY_ID = '195';
    public const DIAN_SCHEME_AGENCY_NAME = 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)';

    /** The catalog code that triggers the check digit. */
    public const IDENTIFICATION_TYPE_NIT = '31';
}
