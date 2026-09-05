<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * DIAN's own document type code (cbc:InvoiceTypeCode and equivalents). Scoped to only the codes
 * this library actually builds or branches on today — Invoice ("01"), the Documento Equivalente
 * Electrónico POS invoice built via the same InvoiceBuilder ("20" — confirmed built and
 * byte-for-byte golden-tested, see GoldenXmlTest::samplePOSInvoice()), Documento Soporte ("05"),
 * Credit Note ("91"), Debit Note ("92"), their Documento Equivalente Electrónico POS
 * adjustment-note equivalents ("93"/"94"), and the Support Document's own Adjustment Note
 * ("95"). Technical Annex Documento Equivalente Electrónico V1.0 section 16.3 reserves several
 * more primary-document codes (25/27/30/35/40/45/50/55/60) that this library does not yet build
 * or individually distinguish anywhere — deliberately left out rather than guessed at, matching
 * this project's own rule against encoding a DIAN business fact without real evidence for it
 * (see docs/dian-rejection-log.md's own history). Add a case here once one of them is actually
 * implemented and needs its own identity.
 */
enum DocumentType: string
{
    case Invoice = '01';
    case Pos = '20';
    case SupportDocument = '05';
    case CreditNote = '91';
    case DebitNote = '92';
    case PosDebitAdjustment = '93';
    case PosCreditAdjustment = '94';
    case AdjustmentNote = '95';
}
