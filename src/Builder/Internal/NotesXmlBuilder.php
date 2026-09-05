<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\BillingReference;
use Cofacture\Domain\DiscrepancyResponse;
use Cofacture\Xml\El;
use DOMElement;

/** Mirrors builder/notes.go. */
final class NotesXmlBuilder
{
    private function __construct()
    {
    }

    /** Appends cac:DiscrepancyResponse — the note's reason. Only called when the note actually
     *  carries one (operation "20" for Credit Note, "30" for Debit Note). */
    public static function appendDiscrepancyResponse(DOMElement $parent, DiscrepancyResponse $dr): void
    {
        $doc = $parent->ownerDocument;
        $el = El::create($doc, 'cac:DiscrepancyResponse');
        $parent->appendChild($el);
        $el->appendChild(El::create($doc, 'cbc:ReferenceID', $dr->referenceId));
        $el->appendChild(El::create($doc, 'cbc:ResponseCode', $dr->responseCode));
        $el->appendChild(El::create($doc, 'cbc:Description', $dr->description));
    }

    /**
     * Appends cac:BillingReference — the reference to the invoice this note corrects. $node is
     * "InvoiceDocumentReference" for both Credit Note and Debit Note. The UUID's schemeName
     * comes from $br->hashType because the referenced document is not always a regular Invoice
     * (CUFE-SHA384) — a Credit/Debit Note adjusting a Documento Equivalente Electrónico
     * references a CUDE-SHA384 document instead.
     */
    public static function appendBillingReference(DOMElement $parent, string $node, BillingReference $br): void
    {
        $doc = $parent->ownerDocument;
        $billingRef = El::create($doc, 'cac:BillingReference');
        $parent->appendChild($billingRef);
        $ref = El::create($doc, 'cac:' . $node);
        $billingRef->appendChild($ref);
        $ref->appendChild(El::create($doc, 'cbc:ID', $br->prefix . $br->number));
        $uuid = El::create($doc, 'cbc:UUID', $br->cufe);
        $ref->appendChild($uuid);
        $uuid->setAttribute('schemeName', $br->hashType);
        $ref->appendChild(El::create($doc, 'cbc:IssueDate', $br->issueDate));
    }

    /**
     * Appends cac:BillingReference to the original Support Document, for the Adjustment Note.
     * Unlike appendBillingReference(), the UUID's schemeName is hardcoded to "CUDS-SHA384"
     * rather than taken from $br->hashType — the Adjustment Note can only ever reference a
     * Support Document (never a regular Invoice), so there is nothing to select between. Mirrors
     * appendNABillingReference (builder/adjustment_note.go), which makes the same deliberate
     * choice instead of trusting the caller to set hashType correctly.
     */
    public static function appendSupportDocumentBillingReference(DOMElement $parent, BillingReference $br): void
    {
        $doc = $parent->ownerDocument;
        $billingRef = El::create($doc, 'cac:BillingReference');
        $parent->appendChild($billingRef);
        $ref = El::create($doc, 'cac:InvoiceDocumentReference');
        $billingRef->appendChild($ref);
        $ref->appendChild(El::create($doc, 'cbc:ID', $br->prefix . $br->number));
        $uuid = El::create($doc, 'cbc:UUID', $br->cufe);
        $ref->appendChild($uuid);
        $uuid->setAttribute('schemeName', 'CUDS-SHA384');
        $ref->appendChild(El::create($doc, 'cbc:IssueDate', $br->issueDate));
    }
}
