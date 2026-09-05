<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder;

use Cofacture\Xml\Namespaces as NS;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Locates the empty ext:ExtensionContent reserved for the XAdES signature (the last
 * ext:UBLExtension of ext:UBLExtensions) in a document already built by InvoiceBuilder::build()
 * or an equivalent. Signer::sign() uses it as the insertion point. Mirrors
 * builder.SignaturePlaceholder (builder/extensions.go).
 */
final class SignaturePlaceholder
{
    private function __construct()
    {
    }

    public static function find(DOMDocument $doc): DOMElement
    {
        $extensionsList = $doc->getElementsByTagNameNS(NS::NS_EXT, 'UBLExtensions');
        if ($extensionsList->length === 0) {
            throw new RuntimeException('builder: the document has no ext:UBLExtensions');
        }
        $extensions = $extensionsList->item(0);

        $children = [];
        foreach ($extensions->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === NS::NS_EXT && $child->localName === 'UBLExtension') {
                $children[] = $child;
            }
        }
        if ($children === []) {
            throw new RuntimeException('builder: ext:UBLExtensions has no ext:UBLExtension');
        }

        $last = $children[count($children) - 1];
        $content = null;
        foreach ($last->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === NS::NS_EXT && $child->localName === 'ExtensionContent') {
                $content = $child;
                break;
            }
        }
        if ($content === null) {
            throw new RuntimeException('builder: the last ext:UBLExtension has no ext:ExtensionContent');
        }
        if ($content->childNodes->length > 0) {
            throw new RuntimeException('builder: the signature placeholder already has content');
        }
        return $content;
    }
}
