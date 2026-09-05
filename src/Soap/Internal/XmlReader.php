<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Soap\Internal;

use DOMElement;

/**
 * Reads direct children of a response element by local name only, ignoring whatever prefix/
 * namespace DIAN's schema happens to assign it (b:, c:, i:, ... — confirmed to vary per
 * operation in the real WSDL). Mirrors the effect of Go's encoding/xml, which matches an
 * unqualified struct tag by local name regardless of the source element's namespace — see the
 * doc comment at the top of soap/types.go.
 */
final class XmlReader
{
    private function __construct()
    {
    }

    public static function text(DOMElement $parent, string $localName): string
    {
        return self::element($parent, $localName)?->textContent ?? '';
    }

    public static function bool(DOMElement $parent, string $localName): bool
    {
        return strtolower(self::text($parent, $localName)) === 'true';
    }

    public static function int(DOMElement $parent, string $localName): int
    {
        $text = self::text($parent, $localName);
        return $text === '' ? 0 : (int) $text;
    }

    public static function float(DOMElement $parent, string $localName): float
    {
        $text = self::text($parent, $localName);
        return $text === '' ? 0.0 : (float) $text;
    }

    public static function bytesFromBase64(DOMElement $parent, string $localName): string
    {
        $text = self::text($parent, $localName);
        return $text === '' ? '' : (base64_decode($text, true) ?: '');
    }

    public static function element(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Like element(), but returns a detached, empty element instead of null when $localName
     * isn't present — every text()/bool()/int() read on it comes back empty/false/0, mirroring
     * Go's encoding/xml, which leaves a missing nested struct as its zero value rather than a
     * pointer that needs a nil check. Lets a fromXml() factory be called unconditionally on a
     * response fragment DIAN may omit entirely.
     */
    public static function elementOrEmpty(DOMElement $parent, string $localName): DOMElement
    {
        return self::element($parent, $localName) ?? $parent->ownerDocument->createElement($localName);
    }

    /** @return DOMElement[] */
    public static function elements(DOMElement $parent, string $localName): array
    {
        $found = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $found[] = $child;
            }
        }
        return $found;
    }

    /** Direct children of $parent's single $wrapperLocalName child, by local name. */
    public static function nestedElements(DOMElement $parent, string $wrapperLocalName, string $itemLocalName): array
    {
        $wrapper = self::element($parent, $wrapperLocalName);
        return $wrapper === null ? [] : self::elements($wrapper, $itemLocalName);
    }
}
