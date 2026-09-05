<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Builder\Internal;

use Cofacture\Domain\PaymentMean;
use Cofacture\Xml\El;
use DOMElement;

/** Mirrors builder/payment_means.go. */
final class PaymentMeanXmlBuilder
{
    private function __construct()
    {
    }

    public static function appendPaymentMean(DOMElement $parent, PaymentMean $paymentMean): void
    {
        $doc = $parent->ownerDocument;
        $el = El::create($doc, 'cac:PaymentMeans');
        $parent->appendChild($el);
        $el->appendChild(El::create($doc, 'cbc:ID', $paymentMean->code));
        $el->appendChild(El::create($doc, 'cbc:PaymentMeansCode', $paymentMean->paymentMethodCode));
        if ($paymentMean->code === '2') {
            $el->appendChild(El::create($doc, 'cbc:PaymentDueDate', $paymentMean->dueDate));
        }
        if ($paymentMean->paymentReference !== '') {
            $el->appendChild(El::create($doc, 'cbc:PaymentID', $paymentMean->paymentReference));
        }
    }
}
