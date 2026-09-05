<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder;

use Cofacture\Builder\Internal\PartyXmlBuilder;
use Cofacture\Domain\AttachedDocument;
use Cofacture\Domain\AttachedPartyInfo;
use Cofacture\Domain\ValidationResult;
use Cofacture\Xml\El;
use Cofacture\Xml\Namespaces as NS;
use DOMDocument;
use DOMElement;

/**
 * Builds the AttachedDocument (electronic container) that wraps an already-signed document
 * together with DIAN's validation response. Mirrors builder/attached_document.go.
 *
 * It only makes sense to call one of these after receiving that response (getStatusZip): the
 * technical annex requires at least one cac:ParentDocumentLineReference ("1..N") — the
 * AttachedDocument is an artifact produced after validation, not something built before it. The
 * AttachedDocument itself is never sent to DIAN via sendBillSync/sendBillAsync (that's the
 * signed Invoice/CreditNote/DebitNote, inside a ZIP); it's what gets delivered to the acquirer
 * afterward, and it carries its own XAdES-EPES signature with an empty ClaimedRole (pass "" as
 * $role to Signer::sign(), not "supplier").
 */
final class AttachedDocumentBuilder
{
    private const CUSTOMIZATION_ID = 'Documentos adjuntos';

    private const INVOICE_PROFILE_ID = 'Factura Electrónica de Venta';
    private const INVOICE_DOCUMENT_TYPE = 'Contenedor de Factura Electrónica';
    private const CREDIT_NOTE_PROFILE_ID = 'Nota de Crédito de Venta';
    private const CREDIT_NOTE_DOCUMENT_TYPE = 'Contenedor de Nota de Crédito';
    private const DEBIT_NOTE_PROFILE_ID = 'Nota de Débito de Venta';
    private const DEBIT_NOTE_DOCUMENT_TYPE = 'Contenedor de Nota de Débito';

    private function __construct()
    {
    }

    /** Builds the AttachedDocument for an already-signed Electronic Sales Invoice. */
    public static function buildForInvoice(AttachedDocument $ad): DOMDocument
    {
        return self::build($ad, self::INVOICE_PROFILE_ID, self::INVOICE_DOCUMENT_TYPE);
    }

    /** Builds the AttachedDocument for an already-signed and DIAN-accepted Credit Note. */
    public static function buildForCreditNote(AttachedDocument $ad): DOMDocument
    {
        return self::build($ad, self::CREDIT_NOTE_PROFILE_ID, self::CREDIT_NOTE_DOCUMENT_TYPE);
    }

    /** Builds the AttachedDocument for an already-signed and DIAN-accepted Debit Note. */
    public static function buildForDebitNote(AttachedDocument $ad): DOMDocument
    {
        return self::build($ad, self::DEBIT_NOTE_PROFILE_ID, self::DEBIT_NOTE_DOCUMENT_TYPE);
    }

    private static function build(AttachedDocument $ad, string $profileId, string $docType): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = false;

        $root = $doc->createElementNS(NS::NS_ATTACHED_DOCUMENT, 'AttachedDocument');
        $doc->appendChild($root);
        El::declareNamespace($root, '', NS::NS_ATTACHED_DOCUMENT);
        El::declareNamespace($root, 'cac', NS::NS_CAC);
        El::declareNamespace($root, 'cbc', NS::NS_CBC);
        El::declareNamespace($root, 'ds', NS::NS_DS);
        El::declareNamespace($root, 'ext', NS::NS_EXT);
        El::declareNamespace($root, 'xades', NS::NS_XADES);
        El::declareNamespace($root, 'xades141', NS::NS_XADES141);

        // Placeholder for the AttachedDocument's own signature (Signer::sign() with role="").
        // Unlike Invoice/CreditNote/DebitNote/Support Document, there is only one
        // ext:UBLExtension (no DianExtensions/InvoiceControl — the container carries no
        // numbering-range data of its own).
        $extensions = El::create($doc, 'ext:UBLExtensions');
        $root->appendChild($extensions);
        $extension = El::create($doc, 'ext:UBLExtension');
        $extensions->appendChild($extension);
        $extension->appendChild(El::create($doc, 'ext:ExtensionContent'));

        $root->appendChild(El::create($doc, 'cbc:UBLVersionID', 'UBL 2.1'));
        $root->appendChild(El::create($doc, 'cbc:CustomizationID', self::CUSTOMIZATION_ID));
        $root->appendChild(El::create($doc, 'cbc:ProfileID', $profileId));
        $root->appendChild(El::create($doc, 'cbc:ProfileExecutionID', $ad->environmentCode));
        $root->appendChild(El::create($doc, 'cbc:ID', $ad->id));
        $root->appendChild(El::create($doc, 'cbc:IssueDate', $ad->issueDate));
        $root->appendChild(El::create($doc, 'cbc:IssueTime', $ad->issueTime));
        $root->appendChild(El::create($doc, 'cbc:DocumentType', $docType));
        $root->appendChild(El::create($doc, 'cbc:ParentDocumentID', $ad->parentDocumentId));

        self::appendAttachedParty($root, 'SenderParty', $ad->sender);
        self::appendAttachedParty($root, 'ReceiverParty', $ad->receiver);

        $attachment = El::create($doc, 'cac:Attachment');
        $root->appendChild($attachment);
        $externalRef = El::create($doc, 'cac:ExternalReference');
        $attachment->appendChild($externalRef);
        $externalRef->appendChild(El::create($doc, 'cbc:MimeCode', 'text/xml'));
        $externalRef->appendChild(El::create($doc, 'cbc:EncodingCode', 'UTF-8'));
        $description = El::create($doc, 'cbc:Description');
        $externalRef->appendChild($description);
        $description->appendChild($doc->createCDATASection($ad->attachmentXml));

        foreach ($ad->validationResults as $validationResult) {
            self::appendParentDocumentLineReference($root, $validationResult);
        }

        return $doc;
    }

    private static function appendAttachedParty(DOMElement $parent, string $node, AttachedPartyInfo $p): void
    {
        $doc = $parent->ownerDocument;
        $partyNode = El::create($doc, 'cac:' . $node);
        $parent->appendChild($partyNode);
        $taxScheme = El::create($doc, 'cac:PartyTaxScheme');
        $partyNode->appendChild($taxScheme);
        $taxScheme->appendChild(El::create($doc, 'cbc:RegistrationName', $p->name));
        $companyId = El::create($doc, 'cbc:CompanyID', $p->identification->number);
        $taxScheme->appendChild($companyId);
        PartyXmlBuilder::setIdentificationAttrs($companyId, $p->identification);
        if ($p->liabilityCodes !== []) {
            $tlc = El::create($doc, 'cbc:TaxLevelCode', implode(';', $p->liabilityCodes));
            $tlc->setAttribute('listName', $p->taxRegimeCode);
            $taxScheme->appendChild($tlc);
        }
        $scheme = El::create($doc, 'cac:TaxScheme');
        $taxScheme->appendChild($scheme);
        $scheme->appendChild(El::create($doc, 'cbc:ID', $p->taxSchemeCode));
        $scheme->appendChild(El::create($doc, 'cbc:Name', $p->taxSchemeName));
    }

    private static function appendParentDocumentLineReference(DOMElement $parent, ValidationResult $vr): void
    {
        $doc = $parent->ownerDocument;
        $pdlr = El::create($doc, 'cac:ParentDocumentLineReference');
        $parent->appendChild($pdlr);
        $pdlr->appendChild(El::create($doc, 'cbc:LineID', $vr->lineId));

        $docRef = El::create($doc, 'cac:DocumentReference');
        $pdlr->appendChild($docRef);
        $docRef->appendChild(El::create($doc, 'cbc:ID', $vr->documentId));
        $uuid = El::create($doc, 'cbc:UUID', $vr->documentCufe);
        $docRef->appendChild($uuid);
        $uuid->setAttribute('schemeName', $vr->documentHashType);
        $docRef->appendChild(El::create($doc, 'cbc:IssueDate', $vr->documentIssueDate));
        $docRef->appendChild(El::create($doc, 'cbc:DocumentType', 'ApplicationResponse'));

        $attachment = El::create($doc, 'cac:Attachment');
        $docRef->appendChild($attachment);
        $externalRef = El::create($doc, 'cac:ExternalReference');
        $attachment->appendChild($externalRef);
        $externalRef->appendChild(El::create($doc, 'cbc:MimeCode', 'text/xml'));
        $externalRef->appendChild(El::create($doc, 'cbc:EncodingCode', 'UTF-8'));
        $description = El::create($doc, 'cbc:Description');
        $externalRef->appendChild($description);
        $description->appendChild($doc->createCDATASection($vr->applicationResponseXml));

        $result = El::create($doc, 'cac:ResultOfVerification');
        $docRef->appendChild($result);
        $result->appendChild(El::create($doc, 'cbc:ValidatorID', $vr->validatorId));
        $result->appendChild(El::create($doc, 'cbc:ValidationResultCode', $vr->validationResultCode));
        $result->appendChild(El::create($doc, 'cbc:ValidationDate', $vr->validationDate));
        $result->appendChild(El::create($doc, 'cbc:ValidationTime', $vr->validationTime));
    }
}
