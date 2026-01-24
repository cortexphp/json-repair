<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSeparationFixer;
use Symplify\EasyCodingStandard\ValueObject\Option;
use PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer;
use PhpCsFixer\Fixer\Comment\SingleLineCommentSpacingFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\FunctionNotation\FunctionDeclarationFixer;
use PhpCsFixer\Fixer\Comment\NoTrailingWhitespaceInCommentFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBetweenImportGroupsFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use Symplify\CodingStandard\Fixer\Annotation\RemovePHPStormAnnotationFixer;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRootFiles()
    ->withSpacing(indentation: Option::INDENTATION_SPACES)
    ->withEditorConfig()
    ->withPreparedSets(
        psr12: true,
        common: true,
        cleanCode: true,
        strict: true,
    )
    ->withPhpCsFixerSets(
        php83Migration: true,
    )
    ->withRules([
        NotOperatorWithSuccessorSpaceFixer::class,
        RemovePHPStormAnnotationFixer::class,
        SingleLineCommentSpacingFixer::class,
        NoTrailingWhitespaceInCommentFixer::class,
        BlankLineBetweenImportGroupsFixer::class,
        SingleLineEmptyBodyFixer::class,
    ])
    ->withConfiguredRule(FunctionDeclarationFixer::class, [
        'closure_fn_spacing' => FunctionDeclarationFixer::SPACING_NONE,
    ])
    ->withConfiguredRule(TrailingCommaInMultilineFixer::class, [
        'after_heredoc' => true,
        'elements' => [
            TrailingCommaInMultilineFixer::ELEMENTS_ARGUMENTS,
            TrailingCommaInMultilineFixer::ELEMENTS_ARRAYS,
            TrailingCommaInMultilineFixer::ELEMENTS_PARAMETERS,
        ],
    ])
    ->withConfiguredRule(OrderedImportsFixer::class, [
        'sort_algorithm' => OrderedImportsFixer::SORT_LENGTH,
        'imports_order' => [
            OrderedImportsFixer::IMPORT_TYPE_CLASS,
            OrderedImportsFixer::IMPORT_TYPE_FUNCTION,
            OrderedImportsFixer::IMPORT_TYPE_CONST,
        ],
    ])
    ->withConfiguredRule(ClassAttributesSeparationFixer::class, [
        'elements' => [
            'property' => ClassAttributesSeparationFixer::SPACING_ONE,
            'method' => ClassAttributesSeparationFixer::SPACING_ONE,
            'trait_import' => ClassAttributesSeparationFixer::SPACING_NONE,
        ],
    ])
    ->withConfiguredRule(ClassDefinitionFixer::class, [
        'inline_constructor_arguments' => false,
        'space_before_parenthesis' => true,
    ])
    ->withConfiguredRule(PhpdocSeparationFixer::class, [
        'groups' => [
            ['deprecated', 'link', 'see', 'since'],
            ['author', 'copyright', 'license'],
            ['category', 'package', 'subpackage'],
            ['property', 'property-read', 'property-write'],
            ['param'],
            ['throws'],
            ['return'],
        ],
    ])
    ->withConfiguredRule(BlankLineBeforeStatementFixer::class, [
        'statements' => ['return', 'throw', 'if', 'switch', 'do', 'yield', 'try'],
    ])
    ->withConfiguredRule(LineLengthFixer::class, [
        LineLengthFixer::LINE_LENGTH => 120,
        LineLengthFixer::INLINE_SHORT_LINES => false,
    ]);
