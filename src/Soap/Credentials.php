<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap;

/**
 * Exactly what Soap needs to sign its own WS-Security header — nothing more. Owned by Soap
 * itself (not Signer\Credentials, which Soap used to import directly) so Soap never has to
 * know Signer exists: anything that can hand over a certificate + private key satisfies this,
 * regardless of who built it or why. Signer\Credentials implements this — a one-way,
 * interface-only link, the mirror image of the coupling this replaces.
 */
interface Credentials
{
    /** Base64-encoded DER of the certificate, for wsse:BinarySecurityToken. */
    public function certificateDerBase64(): string;

    /** The private key, for signing the WS-Security header (a different signature than the
     *  XAdES one Signer applies to the document itself — Soap only ever needs this one). */
    public function signingKey(): \OpenSSLAsymmetricKey;
}
