<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap\Internal;

use Cofacture\Soap\AcquirerResponse;
use Cofacture\Soap\DianResponse;
use Cofacture\Soap\DocumentInfoResponse;
use Cofacture\Soap\ExchangeEmailResponse;
use Cofacture\Soap\NumberRangeResponseList;
use Cofacture\Soap\UploadDocumentResponse;
use Cofacture\Soap\XmlByDocumentKeyResponse;
use Cofacture\Xml\El;
use DOMElement;

/**
 * The individual WcfDianCustomerServices operations, mixed into Client. Mirrors soap/operations.go
 * field for field — kept as a separate trait (rather than folded into Client) for the same reason
 * the Go original keeps it in its own file: Client (Internal\Envelope + the call() plumbing) is
 * the transport-and-security layer, this is the service's actual contract.
 */
trait Operations
{
    abstract public function call(string $action, callable $bodyBuilder): DOMElement;

    /**
     * Sends a ZIP for the certification test set. Returns the UploadDocumentResponse — if
     * zipKey comes back empty, check errorMessageList (DIAN rejected the ZIP during initial
     * validation, before queueing it). The actual validation result is queried afterward with
     * getStatusZip($zipKey).
     */
    public function sendTestSetAsync(string $fileName, string $content, string $testSetId): UploadDocumentResponse
    {
        return UploadDocumentResponse::fromXml($this->call('SendTestSetAsync', static function (DOMElement $body) use ($fileName, $content, $testSetId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendTestSetAsync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:fileName', $fileName));
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
            $el->appendChild(El::create($doc, 'wcf:testSetId', $testSetId));
        }));
    }

    /**
     * Sends a ZIP with a single UBL document and returns the validation result immediately (a
     * synchronous process, Technical Annex 1.9 section 7.10) — unlike sendTestSetAsync/
     * sendBillAsync, there is no zipKey or later query step; the DianResponse itself already
     * carries statusCode/isValid.
     */
    public function sendBillSync(string $fileName, string $content): DianResponse
    {
        return DianResponse::fromXml($this->call('SendBillSync', static function (DOMElement $body) use ($fileName, $content): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendBillSync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:fileName', $fileName));
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
        }));
    }

    /**
     * Sends a ZIP with one or more UBL documents asynchronously (Technical Annex 1.9 section
     * 7.8) — same pattern as sendTestSetAsync (returns a zipKey, the actual result is queried
     * later with getStatusZip), but without testSetId: it's for normal submissions, not the
     * certification test set.
     */
    public function sendBillAsync(string $fileName, string $content): UploadDocumentResponse
    {
        return UploadDocumentResponse::fromXml($this->call('SendBillAsync', static function (DOMElement $body) use ($fileName, $content): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendBillAsync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:fileName', $fileName));
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
        }));
    }

    /**
     * Sends a ZIP asynchronously, same request/response shape as sendBillAsync (fileName +
     * contentFile -> UploadDocumentResponse, poll with getStatusZip). Confirmed present in the
     * real certification-environment WSDL as its own operation, distinct from sendBillAsync.
     */
    public function sendBillAttachmentAsync(string $fileName, string $content): UploadDocumentResponse
    {
        return UploadDocumentResponse::fromXml($this->call('SendBillAttachmentAsync', static function (DOMElement $body) use ($fileName, $content): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendBillAttachmentAsync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:fileName', $fileName));
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
        }));
    }

    /**
     * Queries the validation result of a ZIP sent via sendBillAsync or sendTestSetAsync. A ZIP
     * can contain several documents, hence the array return.
     *
     * @return DianResponse[]
     */
    public function getStatusZip(string $trackId): array
    {
        $result = $this->call('GetStatusZip', static function (DOMElement $body) use ($trackId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetStatusZip');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:trackId', $trackId));
        });
        return array_map(DianResponse::fromXml(...), XmlReader::elements($result, 'DianResponse'));
    }

    /** Queries the validation result of a single document sent via sendBillSync. */
    public function getStatus(string $trackId): DianResponse
    {
        return DianResponse::fromXml($this->call('GetStatus', static function (DOMElement $body) use ($trackId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetStatus');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:trackId', $trackId));
        }));
    }

    /**
     * Queries the numbering ranges DIAN has authorized for a given issuer + software pair.
     * $accountCode and $accountCodeT are the issuer's NIT (identical in direct integration);
     * $softwareCode is the UUID of the registered software. Returns every active and historical
     * range DIAN has for that issuer/software pair, each with its resolution, prefix, from/to,
     * validity dates and the technicalKey needed to compute the CUFE.
     */
    public function getNumberingRange(string $accountCode, string $accountCodeT, string $softwareCode): NumberRangeResponseList
    {
        return NumberRangeResponseList::fromXml($this->call('GetNumberingRange', static function (DOMElement $body) use ($accountCode, $accountCodeT, $softwareCode): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetNumberingRange');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:accountCode', $accountCode));
            $el->appendChild(El::create($doc, 'wcf:accountCodeT', $accountCodeT));
            $el->appendChild(El::create($doc, 'wcf:softwareCode', $softwareCode));
        }));
    }

    /**
     * Sends a ZIP with a signed NominaIndividual (or NominaIndividualDeAjuste) and returns the
     * validation result synchronously (Payroll Technical Annex, section 9.7). Unlike
     * sendBillSync, it only takes contentFile (no fileName).
     */
    public function sendNominaSync(string $content): DianResponse
    {
        return DianResponse::fromXml($this->call('SendNominaSync', static function (DOMElement $body) use ($content): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendNominaSync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
        }));
    }

    /** Sends a payroll ZIP to the certification test set. Combines sendNominaSync's logic with
     *  the testSetId required for certification. */
    public function sendNominaSyncTestSet(string $content, string $testSetId): DianResponse
    {
        return DianResponse::fromXml($this->call('SendNominaSync', static function (DOMElement $body) use ($content, $testSetId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendNominaSync');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
            $el->appendChild(El::create($doc, 'wcf:testSetId', $testSetId));
        }));
    }

    /**
     * Submits a signed ApplicationResponse event (Acuse de Recibo, Reclamo, Recibo del Bien,
     * Aceptación Expresa/Tácita) and returns the validation result synchronously, same response
     * shape as sendBillSync/sendNominaSync. Confirmed present in the real certification-
     * environment WSDL as its own operation.
     */
    public function sendEventUpdateStatus(string $content): DianResponse
    {
        return DianResponse::fromXml($this->call('SendEventUpdateStatus', static function (DOMElement $body) use ($content): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:SendEventUpdateStatus');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:contentFile', base64_encode($content)));
        }));
    }

    /** Queries the validation result of an event sent via sendEventUpdateStatus, same pattern
     *  as getStatus for sendBillSync. */
    public function getStatusEvent(string $trackId): DianResponse
    {
        return DianResponse::fromXml($this->call('GetStatusEvent', static function (DOMElement $body) use ($trackId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetStatusEvent');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:trackId', $trackId));
        }));
    }

    /**
     * Queries DIAN's exchange/notification registry for a third party — see AcquirerResponse.
     * An optional aid when capturing a NIT, never blocking: an empty result (no receiverName/
     * receiverEmail) is normal and expected for most identification numbers.
     */
    public function getAcquirer(string $identificationType, string $identificationNumber): AcquirerResponse
    {
        return AcquirerResponse::fromXml($this->call('GetAcquirer', static function (DOMElement $body) use ($identificationType, $identificationNumber): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetAcquirer');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:identificationType', $identificationType));
            $el->appendChild(El::create($doc, 'wcf:identificationNumber', $identificationNumber));
        }));
    }

    /**
     * Retrieves the original signed XML of a document previously submitted via sendBillSync/
     * sendBillAsync/sendTestSetAsync, given the trackId/document key returned at submission time.
     */
    public function getXmlByDocumentKey(string $trackId): XmlByDocumentKeyResponse
    {
        return XmlByDocumentKeyResponse::fromXml($this->call('GetXmlByDocumentKey', static function (DOMElement $body) use ($trackId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetXmlByDocumentKey');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:trackId', $trackId));
        }));
    }

    /**
     * Queries which Credit/Debit Notes reference the document identified by $trackId — a
     * reverse lookup. Its result is shaped as DianResponse in the real WSDL (the same type
     * sendBillSync/getStatus use), which is unusual for a query operation; this has not been
     * exercised against DIAN's certification environment, so which of DianResponse's fields
     * actually carry data here (as opposed to being left empty) is unconfirmed.
     */
    public function getReferenceNotes(string $trackId): DianResponse
    {
        return DianResponse::fromXml($this->call('GetReferenceNotes', static function (DOMElement $body) use ($trackId): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetReferenceNotes');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:trackId', $trackId));
        }));
    }

    /**
     * Queries full metadata for a document identified by UUID (parties, taxes, referencing
     * notes/events, validations) without downloading its XML — a heavier query than getStatus(),
     * lighter than getXmlByDocumentKey(). See DocumentInfoResponse's doc comment for the same
     * not-yet-confirmed-against-real-DIAN caveat as getReferenceNotes().
     */
    public function getDocumentInfo(string $documentUuid): DocumentInfoResponse
    {
        return DocumentInfoResponse::fromXml($this->call('GetDocumentInfo', static function (DOMElement $body) use ($documentUuid): void {
            $doc = $body->ownerDocument;
            $el = El::create($doc, 'wcf:GetDocumentInfo');
            $body->appendChild($el);
            $el->appendChild(El::create($doc, 'wcf:uuid', $documentUuid));
        }));
    }

    /**
     * Queries the email addresses configured to exchange electronic documents with DIAN's
     * registry, as a base64-encoded CSV. Takes no parameters — confirmed in the real WSDL's
     * schema (an empty sequence).
     */
    public function getExchangeEmails(): ExchangeEmailResponse
    {
        return ExchangeEmailResponse::fromXml($this->call('GetExchangeEmails', static function (DOMElement $body): void {
            $body->appendChild(El::create($body->ownerDocument, 'wcf:GetExchangeEmails'));
        }));
    }
}
