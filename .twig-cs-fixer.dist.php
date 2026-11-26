<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Rules\Function\MacroArgumentNameRule;
use TwigCsFixer\Rules\Variable\VariableNameRule;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

$ruleset = new Ruleset();
$ruleset->addStandard(new TwigCsFixer());
// Variablen- und Macro-Argumentnamen nicht prüfen: die Templates mischen camelCase (footerSettings) und snake_case
// (common_settings), und Variablen reichen über Includes hinweg – Umbenennen hieße alle Stellen zugleich ändern.
$ruleset->removeRule(VariableNameRule::class);
$ruleset->removeRule(MacroArgumentNameRule::class);

$finder = new Finder();
$finder->in('templates/');

$config = new Config();
$config->allowNonFixableRules();
$config->setRuleset($ruleset);
$config->setFinder($finder);

// $config->addTwigExtension(new Sulu\Twig\Extensions\PortalExtension());
// $config->addTokenParser(new Sulu\Twig\Extensions\TokenParser\PortalTokenParser());

return $config;
