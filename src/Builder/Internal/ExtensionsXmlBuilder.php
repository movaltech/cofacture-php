<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\DocumentType;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\NumberingRange;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMElement;

/** Mirrors builder/extensions.go. */
final class ExtensionsXmlBuilder
{
    private function __construct()
    {
    }

    /**
     * Appends ext:UBLExtensions with the DIAN extensions (sts:DianExtensions) and a second,
     * empty ext:UBLExtension, reserved for the XAdES signature that Signer::sign() adds in a
     * later pipeline step.
     */
    public static function appendUBLExtensions(DOMElement $parent, Invoice $inv): void
    {
        $doc = $parent->ownerDocument;
        $extensions = El::create($doc, 'ext:UBLExtensions');
        $parent->appendChild($extensions);

        $firstExtension = El::create($doc, 'ext:UBLExtension');
        $extensions->appendChild($firstExtension);
        $extensionContent = El::create($doc, 'ext:ExtensionContent');
        $firstExtension->appendChild($extensionContent);
        $dianExt = El::create($doc, 'sts:DianExtensions');
        $extensionContent->appendChild($dianExt);

        if ($inv->documentTypeCode === DocumentType::Invoice || $inv->documentTypeCode === DocumentType::SupportDocument) {
            self::appendInvoiceControl($dianExt, $inv->numberingRange);
        }

        $invoiceSource = El::create($doc, 'sts:InvoiceSource');
        $dianExt->appendChild($invoiceSource);
        $source = El::create($doc, 'cbc:IdentificationCode', 'CO');
        $invoiceSource->appendChild($source);
        $source->setAttribute('listAgencyID', '6');
        $source->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $source->setAttribute('listSchemeURI', 'urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.1');

        $swProvider = El::create($doc, 'sts:SoftwareProvider');
        $dianExt->appendChild($swProvider);
        $providerId = El::create($doc, 'sts:ProviderID', $inv->softwareProvider->providerIdentification->number);
        $swProvider->appendChild($providerId);
        $providerId->setAttribute('schemeAgencyID', NS::DIAN_SCHEME_AGENCY_ID);
        $providerId->setAttribute('schemeAgencyName', NS::DIAN_SCHEME_AGENCY_NAME);
        if ($inv->softwareProvider->providerIdentification->typeCode === NS::IDENTIFICATION_TYPE_NIT) {
            $providerId->setAttribute('schemeID', $inv->softwareProvider->providerIdentification->verificationCode);
        }
        $providerId->setAttribute('schemeName', $inv->softwareProvider->providerIdentification->typeCode);

        $softwareId = El::create($doc, 'sts:SoftwareID', $inv->softwareProvider->softwareId);
        $swProvider->appendChild($softwareId);
        $softwareId->setAttribute('schemeAgencyID', NS::DIAN_SCHEME_AGENCY_ID);
        $softwareId->setAttribute('schemeAgencyName', NS::DIAN_SCHEME_AGENCY_NAME);

        $secCode = El::create($doc, 'sts:SoftwareSecurityCode', $inv->softwareSecurityCode);
        $dianExt->appendChild($secCode);
        $secCode->setAttribute('schemeAgencyID', NS::DIAN_SCHEME_AGENCY_ID);
        $secCode->setAttribute('schemeAgencyName', NS::DIAN_SCHEME_AGENCY_NAME);

        $authProvider = El::create($doc, 'sts:AuthorizationProvider');
        $dianExt->appendChild($authProvider);
        $authProviderId = El::create($doc, 'sts:AuthorizationProviderID', NS::DIAN_AUTHORIZATION_PROVIDER_ID);
        $authProvider->appendChild($authProviderId);
        $authProviderId->setAttribute('schemeAgencyID', NS::DIAN_SCHEME_AGENCY_ID);
        $authProviderId->setAttribute('schemeAgencyName', NS::DIAN_SCHEME_AGENCY_NAME);
        $authProviderId->setAttribute('schemeID', '4');
        $authProviderId->setAttribute('schemeName', '31');

        $dianExt->appendChild(El::create($doc, 'sts:QRCode', $inv->qrUrl));

        // Reserved for the XAdES signature (Signer::sign() fills it in later).
        $secondExtension = El::create($doc, 'ext:UBLExtension');
        $extensions->appendChild($secondExtension);
        $secondExtension->appendChild(El::create($doc, 'ext:ExtensionContent'));
    }

    private static function appendInvoiceControl(DOMElement $parent, NumberingRange $nr): void
    {
        $doc = $parent->ownerDocument;
        $control = El::create($doc, 'sts:InvoiceControl');
        $parent->appendChild($control);
        $control->appendChild(El::create($doc, 'sts:InvoiceAuthorization', $nr->authorizedCode));

        $period = El::create($doc, 'sts:AuthorizationPeriod');
        $control->appendChild($period);
        $period->appendChild(El::create($doc, 'cbc:StartDate', $nr->startDate));
        $period->appendChild(El::create($doc, 'cbc:EndDate', $nr->endDate));

        $authorized = El::create($doc, 'sts:AuthorizedInvoices');
        $control->appendChild($authorized);
        if ($nr->prefix !== '') {
            $authorized->appendChild(El::create($doc, 'sts:Prefix', $nr->prefix));
        }
        $authorized->appendChild(El::create($doc, 'sts:From', $nr->startNumber));
        $authorized->appendChild(El::create($doc, 'sts:To', $nr->endNumber));
    }
}
