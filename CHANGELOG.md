# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-09-04

### Changed

- Clarified the `profileId` values used in the README example and test fixtures (e.g.
  `"DIAN 2.1"` → `"DIAN 2.1: Factura Electrónica de Venta"`) and expanded the SOAP client
  feature bullet to enumerate all 16 `WcfDianCustomerServices` operations it implements.

### Documentation

- Documented a DIAN business rule (confirmed against real submissions DAJ48, DAJ39, DSAK25,
  DSAK24b, DSAD06) on `DebitNote` and `SupportDocumentBuilder`: the issuer's own
  `supplier->identification->typeCode` must be `"31"` (NIT) — unlike `Invoice`/`CreditNote`,
  which also accept `"13"` (cédula) — and `supplier->taxSchemeCode`/`taxSchemeName` must be a
  real tax regime once identified via NIT (`"ZZ"`/"No aplica" is rejected). Doc comments only;
  no behavior change, since the package does not enforce DIAN business rules itself.

## [0.1.0] - 2026-09-04

### Added

- Initial release: a full-coverage PHP toolkit for Colombian DIAN electronic invoicing —
  UBL 2.1 XML generation, XAdES-EPES digital signing, and a SOAP 1.2 + WS-Security client for
  `WcfDianCustomerServices`. No database, no framework dependency — plain PHP objects in,
  signed XML out; the caller owns numbering, persistence, and orchestration.
- **Document coverage**: Electronic Sales Invoice (01); Credit Note (91) and Debit Note (92);
  Support Document (05) and its Adjustment Note (95), including withholding tax
  (`WithholdingTaxTotal`); Attached Document (the signed container delivered to the acquirer
  after DIAN validates the wrapped document); the five RADIAN events (Acuse de Recibo, Reclamo,
  Recibo del Bien, Aceptación Expresa, Aceptación Tácita); and Individual Electronic Payroll
  with its Adjustment (102/103/104), a distinct non-UBL XML schema with its own CUNE formula.
- **XAdES-EPES signing** — inclusive C14N 1.0 canonicalization, RSA-SHA256, DIAN's fixed
  signature policy, built from PEM or PKCS#12 (`.p12`/`.pfx`) certificates.
- **Hash formulas verified against DIAN's own published worked examples** (Technical Annex
  1.9): CUFE, CUDE, CUDS, and the RADIAN event CUDE each have a dedicated test reproducing
  DIAN's official example.
- **SOAP 1.2 + WS-Security client** for the `WcfDianCustomerServices` contract: submission
  (`sendBillSync`, `sendBillAsync`, `sendTestSetAsync`, `sendNominaSync`,
  `sendEventUpdateStatus`), status/status-zip polling, numbering-range and acquirer queries,
  and document lookups.
- **Response interpretation** — DIAN's validation messages are parsed into structured
  rejections vs. informational notices, ready to branch on.
- **Namespace-correct XML by construction** — every element is created through a single
  namespace-aware factory, avoiding a common PHP DOM pitfall where a manually-prefixed
  element silently breaks canonicalization and produces an invalid digest.

[0.1.1]: https://github.com/diegofxm/cofacture-php/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/diegofxm/cofacture-php/releases/tag/v0.1.0
