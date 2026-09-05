<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Builder\AdjustmentNoteBuilder;
use Cofacture\Builder\AttachedDocumentBuilder;
use Cofacture\Builder\CreditNoteBuilder;
use Cofacture\Builder\DebitNoteBuilder;
use Cofacture\Builder\EventBuilder;
use Cofacture\Builder\InvoiceBuilder;
use Cofacture\Builder\SupportDocumentBuilder;
use Cofacture\Domain\Address;
use Cofacture\Domain\AdjustmentNote;
use Cofacture\Domain\AttachedDocument;
use Cofacture\Domain\AttachedPartyInfo;
use Cofacture\Domain\BillingReference;
use Cofacture\Domain\CreditNote;
use Cofacture\Domain\DebitNote;
use Cofacture\Domain\DiscrepancyResponse;
use Cofacture\Domain\DocumentType;
use Cofacture\Domain\Environment;
use Cofacture\Domain\Event;
use Cofacture\Domain\EventDocumentReference;
use Cofacture\Domain\EventParty;
use Cofacture\Domain\EventReceiverPerson;
use Cofacture\Domain\Identification;
use Cofacture\Domain\Invoice;
use Cofacture\Domain\Line;
use Cofacture\Domain\NumberingRange;
use Cofacture\Domain\Party;
use Cofacture\Domain\PaymentMean;
use Cofacture\Domain\Reclamo;
use Cofacture\Domain\SoftwareProvider;
use Cofacture\Domain\Tax;
use Cofacture\Domain\Totals;
use Cofacture\Domain\ValidationResult;
use Cofacture\Event\Event as EventCude;
use DOMDocument;
use PHPUnit\Framework\TestCase;

/**
 * Byte-for-byte comparison of every unsigned builder output against the Go original's own golden
 * files (cofacture/builder/testdata/*.xml, copied verbatim into tests/Fixtures/golden/). Every
 * sample*() method below is a line-for-line PHP translation of the matching Go
 * sample*()/TestBuild*_Golden() pair (see cofacture/builder/*_test.go) — same field values, same
 * construction order — so that any real divergence between the two ports shows up here instead of
 * being hidden behind an XPath spot-check.
 *
 * This is deliberately NOT "same shape, different bytes" like the Signed*PipelineTest fixtures
 * (which use their own, unrelated sample data and compare structurally post-signing): an earlier
 * audit found that PHP's DOMDocument (with formatOutput=true) and Go's etree (with Indent(2))
 * produce IDENTICAL serialization — same 2-space indent, same self-closing empty elements, same
 * attribute order — for every document type here. That single fixed divergence (a
 * namespace-declaration ordering bug in LineItemXmlBuilder, affecting Credit Note/Debit
 * Note/Adjustment Note) is why this comparison is meaningful now and wasn't before.
 *
 * Comparison happens on the UNSIGNED builder output only, matching what the Go golden tests
 * themselves do (BuildXxx() -> Indent(2) -> WriteToString(), no Signer involved) — a real
 * ds:Signature block is never byte-identical across runs (fresh RSA operations), so it is
 * correctly out of scope for both sides. Signing itself is covered by the independent
 * openssl_verify() checks in the Signed*PipelineTest suite.
 */
final class GoldenXmlTest extends TestCase
{
    private static function assertMatchesGolden(string $goldenFileName, DOMDocument $doc): void
    {
        $doc->formatOutput = true;
        $got = $doc->saveXML();
        self::assertIsString($got);

        $goldenPath = __DIR__ . '/../Fixtures/golden/' . $goldenFileName;
        $want = file_get_contents($goldenPath);
        self::assertIsString($want, "could not read golden fixture $goldenPath");

        self::assertSame($want, $got, "generated XML does not match $goldenFileName");
    }

    // ---- Invoice (cofacture/builder/invoice_builder_test.go: sampleInvoice) ----

    private static function sampleInvoice(): Invoice
    {
        return new Invoice(
            profileId: 'DIAN 2.1: Factura Electrónica de Venta',
            environmentCode: Environment::Habilitacion,
            operationTypeCode: '10',
            documentTypeCode: DocumentType::Invoice,
            hashType: 'CUFE-SHA384',
            prefix: 'SETP',
            number: '1',
            issueDate: '2024-01-20',
            issueTime: '10:00:00-05:00',
            currencyCode: 'COP',
            supplier: new Party(
                entityTypeCode: '2',
                identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                name: 'MI EMPRESA S.A.S.',
                address: new Address(
                    line: 'Calle 123 #45-67',
                    cityCode: '11001',
                    cityName: 'Bogotá D.C.',
                    stateCode: '11',
                    stateName: 'Bogotá D.C.',
                    countryCode: 'CO',
                    countryName: 'Colombia',
                ),
                taxRegimeCode: '48',
                liabilityCodes: ['O-13', 'O-15', 'O-23'],
                industryClassificationCodes: ['4661', '4669'],
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
                phone: '3001234567',
                email: 'contacto@miempresa.com',
                merchantRegistrationNumber: '12345',
            ),
            customer: new Party(
                entityTypeCode: '1',
                identification: new Identification(number: '1234567890', typeCode: '13'),
                name: 'Juan Pérez',
                taxRegimeCode: '49',
                liabilityCodes: ['O-47'],
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
                phone: '3009876543',
                email: 'juan.perez@example.com',
            ),
            paymentMeans: [new PaymentMean(code: '1', paymentMethodCode: '10')],
            headerTaxes: [new Tax(taxableAmountCents: 126050420, taxAmountCents: 23949580, percent: 19.0, typeCode: '01', typeName: 'IVA')],
            totals: new Totals(
                lineExtensionCents: 126050420,
                taxExclusiveCents: 126050420,
                taxInclusiveCents: 150000000,
                payableCents: 150000000,
            ),
            lines: [
                new Line(
                    description: 'Laptop Dell Inspiron 15, 8GB RAM, 256GB SSD',
                    quantity: 1.0,
                    unitCode: '94',
                    lineExtensionCents: 126050420,
                    unitPriceCents: 126050420,
                    itemCode: 'PROD001',
                    itemTypeCode: '999',
                    itemTypeName: 'Estándar de adopción del contribuyente',
                    // itemTypeAgencyId intentionally left empty — row "999" of table 13.3.5 in the
                    // Technical Annex requires that @schemeAgencyID not be used at all, not that
                    // it be sent with a value of "0". Matches the Go original's own comment.
                    taxes: [new Tax(taxableAmountCents: 126050420, taxAmountCents: 23949580, percent: 19.0, typeCode: '01', typeName: 'IVA')],
                ),
            ],
            numberingRange: new NumberingRange(
                authorizedCode: '18760000001',
                prefix: 'SETP',
                startNumber: '1',
                endNumber: '5000',
                startDate: '2024-01-15',
                endDate: '2026-01-15',
            ),
            softwareProvider: new SoftwareProvider(
                providerIdentification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                softwareId: 'fac4203d-2451-4806-8a3e-000000000001',
            ),
        );
    }

    public function testBuildInvoiceGolden(): void
    {
        self::assertMatchesGolden('invoice_golden.xml', InvoiceBuilder::build(self::sampleInvoice()));
    }

    // ---- Credit Note (credit_note_test.go: sampleCreditNote) ----

    private static function sampleCreditNote(): CreditNote
    {
        $inv = self::sampleInvoice();
        return new CreditNote(
            profileId: 'DIAN 2.1: Nota Crédito de Factura Electrónica de Venta',
            environmentCode: $inv->environmentCode,
            operationTypeCode: '20', // credit note referencing a specific invoice
            documentTypeCode: DocumentType::CreditNote,
            hashType: 'CUDE-SHA384',
            prefix: 'SETPNC',
            number: '1',
            issueDate: $inv->issueDate,
            issueTime: $inv->issueTime,
            currencyCode: $inv->currencyCode,
            supplier: $inv->supplier,
            customer: $inv->customer,
            paymentMeans: $inv->paymentMeans,
            headerTaxes: $inv->headerTaxes,
            totals: $inv->totals,
            lines: $inv->lines,
            numberingRange: $inv->numberingRange,
            softwareProvider: $inv->softwareProvider,
            creditNoteTypeCode: '91', // fixed DIAN code for a Credit Note (document type); the List 22 concept goes in DiscrepancyResponse
            billingReference: new BillingReference(
                prefix: 'SETP',
                number: '1',
                cufe: '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
                issueDate: '2024-01-20',
                hashType: 'CUFE-SHA384', // references a regular Invoice
            ),
            discrepancyResponse: new DiscrepancyResponse(
                referenceId: 'SETP1',
                responseCode: '2',
                description: 'Anulación de factura electrónica',
            ),
        );
    }

    public function testBuildCreditNoteGolden(): void
    {
        self::assertMatchesGolden('credit_note_golden.xml', CreditNoteBuilder::build(self::sampleCreditNote()));
    }

    // ---- Debit Note (debit_note_test.go: sampleDebitNote) ----

    private static function sampleDebitNote(): DebitNote
    {
        $inv = self::sampleInvoice();
        return new DebitNote(
            profileId: 'DIAN 2.1: Nota Débito de Factura Electrónica de Venta',
            environmentCode: $inv->environmentCode,
            operationTypeCode: '30', // debit note referencing a specific invoice
            documentTypeCode: DocumentType::DebitNote,
            hashType: 'CUDE-SHA384',
            prefix: 'SETPND',
            number: '1',
            issueDate: $inv->issueDate,
            issueTime: $inv->issueTime,
            currencyCode: $inv->currencyCode,
            supplier: $inv->supplier,
            customer: $inv->customer,
            paymentMeans: $inv->paymentMeans,
            headerTaxes: $inv->headerTaxes,
            totals: $inv->totals,
            lines: $inv->lines,
            numberingRange: $inv->numberingRange,
            softwareProvider: $inv->softwareProvider,
            billingReference: new BillingReference(
                prefix: 'SETP',
                number: '1',
                cufe: '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
                issueDate: '2024-01-20',
                hashType: 'CUFE-SHA384', // references a regular Invoice
            ),
            discrepancyResponse: new DiscrepancyResponse(
                referenceId: 'SETP1',
                responseCode: '1',
                description: 'Intereses por mora',
            ),
        );
    }

    public function testBuildDebitNoteGolden(): void
    {
        self::assertMatchesGolden('debit_note_golden.xml', DebitNoteBuilder::build(self::sampleDebitNote()));
    }

    // ---- Support Document (support_document_test.go: sampleSupportDocument) ----

    private static function sampleSupportDocument(): Invoice
    {
        $sup = self::sampleInvoice();
        $sup->operationTypeCode = '10'; // Resident
        $sup->documentTypeCode = DocumentType::SupportDocument;
        $sup->hashType = 'CUDS-SHA384';
        $sup->profileId = 'DIAN 2.1: documento soporte en adquisiciones efectuadas a no obligados a facturar.';
        $sup->prefix = 'DS';
        $sup->number = '1';

        // Roles reversed: Supplier = third party not obligated to invoice (SNO), Customer =
        // issuer. DIAN requires schemeName="31" (NIT) for the SNO.
        $sup->supplier = new Party(
            entityTypeCode: '2',
            identification: new Identification(number: '1020304050', typeCode: '31', verificationCode: '8'),
            name: 'María García',
            taxSchemeCode: 'ZZ',
            taxSchemeName: 'No aplica',
            liabilityCodes: ['R-99-PN'],
            taxRegimeCode: '49',
            address: new Address(
                line: 'Vereda El Rosal',
                cityCode: '05001',
                cityName: 'Medellín',
                stateCode: '05',
                stateName: 'Antioquia',
                countryCode: 'CO',
                countryName: 'Colombia',
            ),
        );
        // Customer = the issuing company (same data as the original Supplier in sampleInvoice()).
        $sup->customer = new Party(
            entityTypeCode: '2',
            identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '8'),
            name: 'MI EMPRESA S.A.S.',
            address: new Address(
                line: 'Calle 123 #45-67',
                cityCode: '11001',
                cityName: 'Bogotá D.C.',
                stateCode: '11',
                stateName: 'Bogotá D.C.',
                countryCode: 'CO',
                countryName: 'Colombia',
            ),
            taxRegimeCode: '48',
            liabilityCodes: ['O-13', 'O-15', 'O-23'],
            taxSchemeCode: '01',
            taxSchemeName: 'IVA',
        );

        // A ReteRenta withholding of 3.5% on a base of 126,050,420.
        $sup->withholdingTaxes = [
            new Tax(typeCode: '06', typeName: 'ReteRenta', taxableAmountCents: 126_050_420, taxAmountCents: 4_411_765, percent: 3.5),
        ];

        return $sup;
    }

    public function testBuildSupportDocumentGolden(): void
    {
        self::assertMatchesGolden('support_document_golden.xml', SupportDocumentBuilder::build(self::sampleSupportDocument()));
    }

    // ---- Adjustment Note (adjustment_note_test.go: sampleAdjustmentNote) ----

    private static function sampleAdjustmentNote(): AdjustmentNote
    {
        $inv = self::sampleSupportDocument();
        return new AdjustmentNote(
            profileId: 'DIAN 2.1: Nota de ajuste al documento soporte en adquisiciones efectuadas a sujetos no obligados a expedir factura o documento equivalente',
            environmentCode: $inv->environmentCode,
            operationTypeCode: $inv->operationTypeCode,
            documentTypeCode: DocumentType::AdjustmentNote,
            hashType: 'CUDS-SHA384',
            prefix: 'NAP',
            number: '1',
            issueDate: $inv->issueDate,
            issueTime: $inv->issueTime,
            currencyCode: $inv->currencyCode,
            supplier: $inv->supplier,
            customer: $inv->customer,
            paymentMeans: $inv->paymentMeans,
            headerTaxes: $inv->headerTaxes,
            withholdingTaxes: $inv->withholdingTaxes,
            totals: $inv->totals,
            lines: $inv->lines,
            numberingRange: $inv->numberingRange,
            softwareProvider: $inv->softwareProvider,
            billingReference: new BillingReference(
                prefix: 'SEDS',
                number: '1',
                cufe: '18015e1f4f6b1eb55cf6d5eaa1f752bed3b0402e0cf11eb515c1ce5ccbe9bca120cd4776ee3b1e5c281e0fd2711d40d1',
                issueDate: '2024-01-20',
                hashType: 'CUDS-SHA384', // references the original Support Document (unused by appendSupportDocumentBillingReference, which hardcodes this correctly — set here for clarity)
            ),
            discrepancyResponse: new DiscrepancyResponse(
                referenceId: 'SEDS1',
                responseCode: '1',
                description: 'Corrección de valores',
            ),
        );
    }

    public function testBuildAdjustmentNoteGolden(): void
    {
        self::assertMatchesGolden('adjustment_note_golden.xml', AdjustmentNoteBuilder::build(self::sampleAdjustmentNote()));
    }

    // ---- Attached Document (attached_document_test.go: sampleAttachedDocument) ----

    private static function sampleAttachedDocument(): AttachedDocument
    {
        return new AttachedDocument(
            environmentCode: Environment::Habilitacion,
            id: '1',
            issueDate: '2024-01-20',
            issueTime: '10:05:00-05:00',
            parentDocumentId: 'SETP1',
            sender: new AttachedPartyInfo(
                name: 'MI EMPRESA S.A.S.',
                identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                taxRegimeCode: '48',
                liabilityCodes: ['O-13', 'O-15', 'O-23'],
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            receiver: new AttachedPartyInfo(
                name: 'Juan Pérez',
                identification: new Identification(number: '1234567890', typeCode: '13'),
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            attachmentXml: '<Invoice>contenido simplificado para esta prueba, no se valida aquí</Invoice>',
            validationResults: [
                new ValidationResult(
                    lineId: '1',
                    documentId: 'SETP1',
                    documentCufe: '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
                    documentHashType: 'CUFE-SHA384',
                    documentIssueDate: '2024-01-20',
                    applicationResponseXml: '<ApplicationResponse>contenido simplificado para esta prueba</ApplicationResponse>',
                    validatorId: 'Unidad Especial Dirección de Impuestos y Aduanas Nacionales',
                    validationResultCode: '02',
                    validationDate: '2024-01-20',
                    validationTime: '10:10:00-05:00',
                ),
            ],
        );
    }

    public function testBuildInvoiceAttachedDocumentGolden(): void
    {
        self::assertMatchesGolden('attached_document_golden.xml', AttachedDocumentBuilder::buildForInvoice(self::sampleAttachedDocument()));
    }

    // ---- Documento Equivalente POS (pos_test.go / pos_rounding_test.go / pos_adjustment_note_test.go) ----

    /** samplePOSInvoice() overrides only the four fields the Technical Annex actually changes
     *  for the POS document (InvoiceTypeCode 20): ProfileID, CustomizationID, DocumentTypeCode,
     *  HashType — everything else is identical to a regular Invoice. */
    private static function samplePOSInvoice(): Invoice
    {
        $inv = self::sampleInvoice();
        $inv->profileId = 'DIAN 2.1: Documento Equivalente POS';
        $inv->operationTypeCode = '10';
        $inv->documentTypeCode = DocumentType::Pos;
        $inv->hashType = 'CUDE-SHA384';
        return $inv;
    }

    public function testBuildInvoicePosGolden(): void
    {
        self::assertMatchesGolden('pos_invoice_golden.xml', InvoiceBuilder::build(self::samplePOSInvoice()));
    }

    /** Confirms PayableRoundingAmount is serialized (negative sign preserved) when
     *  Totals::$roundingCents is non-zero. */
    public function testBuildInvoicePosRoundingGolden(): void
    {
        $inv = self::samplePOSInvoice();
        $inv->totals = new Totals(
            lineExtensionCents: $inv->totals->lineExtensionCents,
            taxExclusiveCents: $inv->totals->taxExclusiveCents,
            taxInclusiveCents: $inv->totals->taxInclusiveCents,
            prepaidCents: $inv->totals->prepaidCents,
            roundingCents: -50_00, // -50.00 pesos, a downward rounding adjustment
            payableCents: $inv->totals->payableCents,
        );
        self::assertMatchesGolden('pos_invoice_rounding_golden.xml', InvoiceBuilder::build($inv));
    }

    /**
     * samplePOSCreditAdjustment() builds a "Nota de Ajuste de tipo crédito al Documento
     * Equivalente" (DocumentTypeCode/CreditNoteTypeCode "94") referencing the POS ticket from
     * samplePOSInvoice(). OperationTypeCode here is the REFERENCED document's own InvoiceTypeCode
     * ("20" for POS) — not a generic "references some invoice" code like a regular Credit Note
     * uses; same digits, two different DIAN catalogs.
     */
    private static function samplePOSCreditAdjustment(): CreditNote
    {
        $cn = self::sampleCreditNote();
        $cn->profileId = 'DIAN 2.1: Nota de ajuste crédito al documento equivalente';
        $cn->operationTypeCode = '20';
        $cn->documentTypeCode = DocumentType::PosCreditAdjustment;
        $cn->creditNoteTypeCode = '94';
        $cn->billingReference = new BillingReference(
            prefix: 'SETP',
            number: '1',
            cufe: 'db07502cd11c006f4666e2e299fd77e5a47bd790d9da18786dace4b4d0c4b8972643843e8b7444fe23a0fc8aa1fdf5f2',
            issueDate: '2024-01-20',
            hashType: 'CUDE-SHA384', // references a Documento Equivalente Electrónico (POS), not an Invoice
        );
        return $cn;
    }

    public function testBuildCreditNotePosAdjustmentGolden(): void
    {
        self::assertMatchesGolden('pos_credit_adjustment_golden.xml', CreditNoteBuilder::build(self::samplePOSCreditAdjustment()));
    }

    /** samplePOSDebitAdjustment() is the "Nota de Ajuste de tipo débito" (DocumentTypeCode "93")
     *  sibling of samplePOSCreditAdjustment() — see that method's doc comment. */
    private static function samplePOSDebitAdjustment(): DebitNote
    {
        $dn = self::sampleDebitNote();
        $dn->profileId = 'DIAN 2.1: Nota de ajuste débito al documento equivalente';
        $dn->operationTypeCode = '20';
        $dn->documentTypeCode = DocumentType::PosDebitAdjustment;
        $dn->billingReference = new BillingReference(
            prefix: 'SETP',
            number: '1',
            cufe: 'db07502cd11c006f4666e2e299fd77e5a47bd790d9da18786dace4b4d0c4b8972643843e8b7444fe23a0fc8aa1fdf5f2',
            issueDate: '2024-01-20',
            hashType: 'CUDE-SHA384', // references a Documento Equivalente Electrónico (POS), not an Invoice
        );
        return $dn;
    }

    public function testBuildDebitNotePosAdjustmentGolden(): void
    {
        self::assertMatchesGolden('pos_debit_adjustment_golden.xml', DebitNoteBuilder::build(self::samplePOSDebitAdjustment()));
    }

    // ---- RADIAN events (event_test.go: sampleEvent) ----

    private static function sampleEvent(): Event
    {
        return new Event(
            environmentCode: Environment::Habilitacion,
            id: '1',
            issueDate: '2024-02-05',
            issueTime: '09:00:00-05:00',
            documentReference: new EventDocumentReference(
                prefix: 'SETP',
                number: '990068706',
                cufe: '853657dcf2841c55c04338b24cc4db9dfbf87042f1ce1798e53f7b1f0502d00df9bd3f371dea47b02766424976d60ba2',
                hashType: 'CUFE-SHA384',
                documentTypeCode: DocumentType::Invoice,
            ),
            sender: new EventParty(
                name: 'Consumidor Final',
                identification: new Identification(number: '222222222222', typeCode: '13'),
                taxSchemeCode: 'ZZ',
                taxSchemeName: 'No aplica',
            ),
            receiver: new EventParty(
                name: 'MI EMPRESA S.A.S.',
                identification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                taxSchemeCode: '01',
                taxSchemeName: 'IVA',
            ),
            softwareProvider: new SoftwareProvider(
                providerIdentification: new Identification(number: '900123456', typeCode: '31', verificationCode: '3'),
                softwareId: '12345678-1234-1234-1234-123456789012',
            ),
            cude: '0d91ba25b01f5e7dbda870a11b274501d3a62a73e91932c473c86c93f12a142a2ac45876efcde3e679024a01c0be41f9',
            softwareSecurityCode: 'abc123',
            qrUrl: 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=853657dcf2841c55c04338b24cc4db9dfbf87042f1ce1798e53f7b1f0502d00df9bd3f371dea47b02766424976d60ba2',
        );
    }

    public function testBuildAcuseReciboGolden(): void
    {
        $ev = self::sampleEvent();
        $ev->receiverPerson = new EventReceiverPerson(
            identification: new Identification(number: '1234567890', typeCode: '13'),
            firstName: 'Juan',
            familyName: 'Pérez',
            jobTitle: 'Gerente de Compras',
        );
        self::assertMatchesGolden('acuse_recibo_golden.xml', EventBuilder::buildAcuseRecibo($ev));
    }

    public function testBuildRecibidoBienGolden(): void
    {
        self::assertMatchesGolden('recibido_bien_golden.xml', EventBuilder::buildRecibidoBien(self::sampleEvent()));
    }

    public function testBuildAceptacionExpresaGolden(): void
    {
        self::assertMatchesGolden('aceptacion_expresa_golden.xml', EventBuilder::buildAceptacionExpresa(self::sampleEvent()));
    }

    public function testBuildAceptacionTacitaGolden(): void
    {
        $ev = self::sampleEvent();
        // Aceptación Tácita is issuer-generated: DIAN is the recipient of the event, and the
        // issuer (Receiver in the referenced invoice) is who sends it — roles are swapped vs. the
        // other four events, which is why Sender/Receiver are reversed here.
        [$ev->sender, $ev->receiver] = [$ev->receiver, $ev->sender];
        $ev->note = EventCude::tacitAcceptanceNote('1', $ev->cude, 'Consumidor Final', '222222222222');
        self::assertMatchesGolden('aceptacion_tacita_golden.xml', EventBuilder::buildAceptacionTacita($ev));
    }

    public function testBuildReclamoGolden(): void
    {
        $base = self::sampleEvent();
        $r = new Reclamo(
            environmentCode: $base->environmentCode,
            id: $base->id,
            issueDate: $base->issueDate,
            issueTime: $base->issueTime,
            documentReference: $base->documentReference,
            sender: $base->sender,
            receiver: $base->receiver,
            softwareProvider: $base->softwareProvider,
            cude: $base->cude,
            softwareSecurityCode: $base->softwareSecurityCode,
            qrUrl: $base->qrUrl,
            rejectionListId: '2',
            rejectionName: 'Reclamo',
        );
        self::assertMatchesGolden('reclamo_golden.xml', EventBuilder::buildReclamo($r));
    }
}
