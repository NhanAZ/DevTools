<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin'])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'final_class' => true,
        'fully_qualified_strict_types' => true,
        'no_superfluous_phpdoc_tags' => true,
        'ordered_imports' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'single_quote' => true,
    ])
    ->setFinder($finder);
