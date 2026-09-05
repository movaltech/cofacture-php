<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Internal;

/**
 * RFC 4122 version 4 UUID generation — shared by Signer (ds:Signature/@Id, xades:... ids) and
 * Soap (wsa:MessageID, wsse:.../@wsu:Id), which previously each carried their own byte-for-byte
 * identical copy of this. The Go original never had this duplication: both signer.go and
 * soap/envelope.go call the same external uuid.New() (github.com/google/uuid). A single copy
 * here means a future fix to the version/variant bit-setting can't silently drift between two
 * independent copies.
 */
final class UuidV4
{
    private function __construct()
    {
    }

    public static function generate(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
