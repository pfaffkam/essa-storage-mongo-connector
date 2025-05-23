<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'no_null_property_initialization' => false,
        'single_line_empty_body' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'parameters']],
    ])
    ->setFinder($finder)
    ;
