<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Tests\Unit;

use Cofacture\Dian\Message;
use Cofacture\Dian\Result;
use Cofacture\Soap\DianResponse;
use PHPUnit\Framework\TestCase;

/** Mirrors dian/parser_test.go. */
final class DianResultTest extends TestCase
{
    /** @return array<string,array{0:string,1:Message}> */
    public static function messageCases(): array
    {
        return [
            // Real messages returned by DIAN (certification/testing environment).
            'rechazo' => [
                'Regla: ZE02, Rechazo: Valor de la firma inválido.',
                new Message(rule: 'ZE02', severity: 'Rechazo', text: 'Valor de la firma inválido.', raw: 'Regla: ZE02, Rechazo: Valor de la firma inválido.'),
            ],
            'notificacion' => [
                'Regla: FAJ43b, Notificación: Nombre informado No corresponde al registrado en el RUT con respecto al Nit suminstrado.',
                new Message(
                    rule: 'FAJ43b',
                    severity: 'Notificación',
                    text: 'Nombre informado No corresponde al registrado en el RUT con respecto al Nit suminstrado.',
                    raw: 'Regla: FAJ43b, Notificación: Nombre informado No corresponde al registrado en el RUT con respecto al Nit suminstrado.',
                ),
            ],
            'unrecognized format' => [
                // Text that doesn't follow the pattern: preserved in raw, the rest left empty
                // instead of failing.
                'algo que no sigue el formato esperado',
                new Message(raw: 'algo que no sigue el formato esperado'),
            ],
        ];
    }

    /** @dataProvider messageCases */
    public function testParseMessage(string $raw, Message $want): void
    {
        $got = Message::parse($raw);
        self::assertSame($want->rule, $got->rule);
        self::assertSame($want->severity, $got->severity);
        self::assertSame($want->text, $got->text);
        self::assertSame($want->raw, $got->raw);
    }

    public function testMessageIsRejection(): void
    {
        self::assertTrue((new Message(severity: 'Rechazo'))->isRejection());
        self::assertFalse((new Message(severity: 'Notificación'))->isRejection());
    }

    public function testInterpretDecodesBase64ApplicationResponse(): void
    {
        // xmlBase64Bytes arrives as base64 text — the SOAP layer never decodes it on its own
        // (Soap\DianResponse keeps it raw), so Result::interpret()'s one base64_decode call is
        // the only decode step.
        $innerXml = '<?xml version="1.0" encoding="utf-8"?><ApplicationResponse><cbc:UUID>cude-de-prueba</cbc:UUID></ApplicationResponse>';
        $encoded = base64_encode($innerXml);

        $resp = new DianResponse(
            errorMessages: [],
            isValid: true,
            statusCode: '00',
            statusDescription: '',
            statusMessage: 'ha sido autorizada',
            xmlBase64Bytes: $encoded,
            xmlBytes: '',
            xmlDocumentKey: 'cufe-de-prueba',
            xmlFileName: '',
        );

        $result = Result::interpret($resp);

        self::assertSame($innerXml, $result->applicationResponseXml);
        self::assertTrue($result->isValid);
        self::assertSame('00', $result->statusCode);
    }

    public function testInterpretNoEmbeddedXml(): void
    {
        $resp = new DianResponse(
            errorMessages: [],
            isValid: false,
            statusCode: '99',
            statusDescription: '',
            statusMessage: '',
            xmlBase64Bytes: '',
            xmlBytes: '',
            xmlDocumentKey: '',
            xmlFileName: '',
        );

        $result = Result::interpret($resp);

        self::assertSame('', $result->applicationResponseXml);
    }

    public function testHasRejectionsAndToValidationResult(): void
    {
        $result = new Result(
            isValid: true,
            statusCode: '00',
            messages: [Message::parse('Regla: FAJ43b, Notificación: algo informativo.')],
            xmlDocumentKey: 'cufe-123',
            applicationResponseXml: '<ApplicationResponse/>',
        );
        self::assertFalse($result->hasRejections(), 'una notificación sola no debería contar como rechazo');

        $result = new Result(
            isValid: $result->isValid,
            statusCode: $result->statusCode,
            messages: [...$result->messages, Message::parse('Regla: ZE02, Rechazo: firma inválida.')],
            xmlDocumentKey: $result->xmlDocumentKey,
            applicationResponseXml: $result->applicationResponseXml,
        );
        self::assertTrue($result->hasRejections(), 'debería detectar el rechazo agregado');

        $vr = $result->toValidationResult('1', 'SETP1', 'CUFE-SHA384', '2026-01-20', '2026-01-20', '10:00:00-05:00');
        self::assertSame('cufe-123', $vr->documentCufe);
        self::assertSame(Result::VALIDATOR_ID, $vr->validatorId);
        self::assertSame('<ApplicationResponse/>', $vr->applicationResponseXml);
    }

    public function testIsTestSetClosed(): void
    {
        // Real response that produced the rejection in Technical Annex section 9.43 — no
        // messages, just statusDescription.
        $closed = new Result(
            statusCode: '2',
            statusDescription: 'Set de prueba con identificador 653bf9d9-b2b1-44ae-a66d-3b9cdc4271c3 se encuentra Aceptado.',
        );
        self::assertTrue($closed->isTestSetClosed());

        // A normal content rejection must not be confused with this, even though it also has
        // statusCode "2".
        $contentRejection = new Result(
            statusCode: '2',
            statusDescription: 'Documento rechazado',
            messages: [Message::parse('Regla: ZE02, Rechazo: Valor de la firma inválido.')],
        );
        self::assertFalse($contentRejection->isTestSetClosed());
    }
}
