<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

// Enforces the module boundaries this library relies on but PHP itself cannot check on its
// own — unlike Go's compiler-enforced internal/ packages, a PHP namespace boundary is only as
// real as this file makes it. Run `vendor/bin/deptrac analyse` (or wire it into CI) to catch a
// violation before it ships, not after.
return static function (DeptracConfig $config): void {
    $config
        ->paths('./src')
        ->excludeFiles('#.*Test\.php$#')
        ->layers(
            $domain = Layer::withName('Domain')->collectors(
                ClassLikeConfig::create('^Cofacture.Domain.'),
            ),
            $xml = Layer::withName('Xml')->collectors(
                ClassLikeConfig::create('^Cofacture.Xml.'),
            ),
            $zip = Layer::withName('Zip')->collectors(
                ClassLikeConfig::create('^Cofacture.Zip.'),
            ),
            $event = Layer::withName('Event')->collectors(
                ClassLikeConfig::create('^Cofacture.Event.'),
            ),
            $securityCode = Layer::withName('SecurityCode')->collectors(
                ClassLikeConfig::create('^Cofacture.SecurityCode.'),
            ),
            // The shared hash helper Cufe/Cude both need — Cofacture\Internal\* at the top
            // level, not to be confused with each module's own <Module>\Internal\* (those get
            // bucketed into their own module's layer below, since e.g.
            // ClassLikeConfig::create('^Cofacture.Builder.') already matches
            // Cofacture\Builder\Internal\* too).
            $sharedInternal = Layer::withName('SharedInternal')->collectors(
                ClassLikeConfig::create('^Cofacture.Internal.'),
            ),
            $cufe = Layer::withName('Cufe')->collectors(
                ClassLikeConfig::create('^Cofacture.Cufe.'),
            ),
            $cude = Layer::withName('Cude')->collectors(
                ClassLikeConfig::create('^Cofacture.Cude.'),
            ),
            $cuds = Layer::withName('Cuds')->collectors(
                ClassLikeConfig::create('^Cofacture.Cuds.'),
            ),
            $qr = Layer::withName('Qr')->collectors(
                ClassLikeConfig::create('^Cofacture.Qr.'),
            ),
            $payroll = Layer::withName('Payroll')->collectors(
                ClassLikeConfig::create('^Cofacture.Payroll.'),
            ),
            $signer = Layer::withName('Signer')->collectors(
                ClassLikeConfig::create('^Cofacture.Signer.'),
            ),
            $builder = Layer::withName('Builder')->collectors(
                ClassLikeConfig::create('^Cofacture.Builder.'),
            ),
            $soap = Layer::withName('Soap')->collectors(
                ClassLikeConfig::create('^Cofacture.Soap.'),
            ),
            $dian = Layer::withName('Dian')->collectors(
                ClassLikeConfig::create('^Cofacture.Dian.'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($domain),
            Ruleset::forLayer($xml),
            Ruleset::forLayer($zip),
            Ruleset::forLayer($event),
            Ruleset::forLayer($securityCode),
            Ruleset::forLayer($sharedInternal)->accesses($domain),
            Ruleset::forLayer($cufe)->accesses($domain, $sharedInternal),
            Ruleset::forLayer($cude)->accesses($domain, $sharedInternal),
            Ruleset::forLayer($cuds)->accesses($domain),
            Ruleset::forLayer($qr)->accesses($domain),
            Ruleset::forLayer($payroll)->accesses($domain, $xml),
            // Signer\Credentials implements Soap\Credentials (see that interface's own doc
            // comment) — a deliberate, one-way link: Signer -> Soap's interface, never the
            // reverse, and never Soap -> Signer's concrete class.
            Ruleset::forLayer($signer)->accesses($xml, $soap, $sharedInternal),
            Ruleset::forLayer($builder)->accesses($domain, $xml, $event),
            // Soap must NOT access Signer — see Cofacture\Soap\Credentials, the interface Soap
            // owns instead of importing Signer\Credentials directly (fixed right after this).
            Ruleset::forLayer($soap)->accesses($xml, $sharedInternal),
            Ruleset::forLayer($dian)->accesses($domain, $soap),
        )
    ;
};
