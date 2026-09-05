<?php
// Copyright (c) 2026 Diego Montoya
// SPDX-License-Identifier: AGPL-3.0

declare(strict_types=1);

namespace Cofacture\Domain;

/**
 * DIAN's environment code — "1" production, "2" certification/testing (habilitación). Also
 * used as the UUID's schemeID in the signed XML, so ->value is what actually needs to reach the
 * document, not just this enum's case name.
 */
enum Environment: string
{
    case Produccion = '1';
    case Habilitacion = '2';
}
