<?php

declare( strict_types = 1 );

use Aysnc\WordPress\PHPCSFixer\Config;
use BrianHenryIE\Plugin_Meta_Kit\PHPCSFixer\Fixers\LongCallToMultilineFixer;
use PhpCsFixer\Finder;

/*
 * The preset needs PHP 8.3, and the library supports 8.2, so it is installed apart from the library's own
 * dependencies: `composer install --working-dir=tools/php-cs-fixer`, which `composer php-cs-fixer` does when needed.
 */
require_once __DIR__ . '/tools/php-cs-fixer/vendor/autoload.php';
require_once __DIR__ . '/bin/php-cs-fixer/Fixers/LongCallToMultilineFixer.php';

$finder = Finder::create()
				->in(
					array(
						__DIR__ . '/bin',
						__DIR__ . '/development-plugin',
						__DIR__ . '/src',
						__DIR__ . '/tests',
					),
				)
				->append( array( __DIR__ . '/bootstrap.php' ) )
				->name( '*.php' )
				->ignoreVCS( ignoreVCS: true )
				->exclude( array( 'vendor', '_generated', '_data', '_output' ) );

$config = Config::create();

// For phpdoc_to_property_type, which php-cs-fixer marks risky because a wrong `@var` becomes a TypeError.
$config->setRiskyAllowed( true );

/*
 * The preset registers its own fixers in Config::create(); this appends to that list rather
 * than replacing it.
 */
$config->registerCustomFixers( array( new LongCallToMultilineFixer() ) );

return $config
		->setFinder( $finder )
		->setRules(
			array(
				...Config::getRules(),

				/**
				 * Break a call across lines when it makes the line longer than 80 characters.
				 *
				 * The fixer only inserts the first newline; `method_argument_space` (which it
				 * runs before) puts one argument per line and indents them.
				 */
				'BrianHenryIE/long_call_to_multiline' => true,

				/**
				 * This project does not maintain `@since`, `@version` or `@subpackage` (see CLAUDE.md).
				 * `no_empty_phpdoc` and `phpdoc_trim` then remove the docblocks and blank lines
				 * left behind.
				 */
				'general_phpdoc_annotation_remove' => array(
					'annotations'    => array( 'since', 'subpackage', 'version' ),
					'case_sensitive' => true,
				),
				'no_empty_phpdoc'                  => true,
				'phpdoc_trim'                      => true,

				/**
				 * Give properties the native type their `@var` tag declares. Risky: a property whose
				 * docblock is wrong throws a TypeError at runtime rather than silently holding the
				 * value, and one with no default must be assigned before it is read. The tests are
				 * the check.
				 */
				'phpdoc_to_property_type' => array(
					'scalar_types' => true,
					'union_types'  => true,
				),

				/**
				 * Drop `@var`, `@param` and `@return` tags which only repeat the native type and say
				 * nothing else. A generic or array shape such as `array<string, int>` is more than
				 * the native `array`, so it stays, and so does any tag with a description.
				 */
				'no_superfluous_phpdoc_tags' => array(
					'allow_mixed'       => true,
					'remove_inheritdoc' => false,
				),
				'array_syntax'                        => array(
					'syntax' => 'long',
				),

				/**
				 * Hand assignment-operator alignment to phpcbf, which was undoing this on every run.
				 *
				 * Both tools want the assignments in a block aligned, but they disagree on where a
				 * block ends: a multi-line statement closes the group for php-cs-fixer and does not
				 * for phpcbf. In the reference project the multi-line `$query = "SELECT …"` left
				 * php-cs-fixer treating the next assignment as a group of one and stripping its
				 * padding, which phpcbf then restored — the two reverting each other indefinitely.
				 *
				 * `null` means leave the operator alone. Every assignment operator is listed, not
				 * just `=`: the preset only names `=` and `=>`, so the rest inherited `default`
				 * and `.=` alignment ping-ponged in the same way.
				 *
				 * `=>` keeps the preset's alignment, which is the one case the two agree on.
				 */
				'binary_operator_spaces' => array(
					'default'   => 'single_space',
					'operators' => array(
						'='   => null,
						'+='  => null,
						'-='  => null,
						'*='  => null,
						'/='  => null,
						'.='  => null,
						'%='  => null,
						'**=' => null,
						'??=' => null,
						'|='  => null,
						'&='  => null,
						'^='  => null,
						'<<=' => null,
						'>>=' => null,
						'=>'  => 'align_single_space_minimal',
					),
				),

				/**
				 * `phpdoc_separation` puts a blank line between annotations it considers unrelated.
				 * `@hooked` is not a tag it knows, so it was separating it from the `@see` naming
				 * where that hook is fired — the two are one thought and belong together.
				 *
				 * `skip_unlisted_annotations` does not help here: `@see` is a listed tag, so it is
				 * still separated from the unlisted `@hooked` above it. The groups have to be given
				 * explicitly, and supplying `groups` replaces php-cs-fixer's defaults rather than
				 * adding to them, so the other three are restated unchanged.
				 */
				'phpdoc_separation' => array(
					'groups' => array(
						array( 'hooked', 'see', 'deprecated', 'link', 'since' ),
						array( 'author', 'copyright', 'license' ),
						array( 'category', 'package', 'subpackage' ),
						array( 'property', 'property-read', 'property-write' ),
					),
				),

				/**
				 * Corrupts generic types on `@return` and `@throws`.
				 *
				 * The rule splits the tag with `/^(\S+)\s+(.+)$/` to separate the type from its
				 * description. `\S+` cannot span the space in `array<string, string>`, so it reads
				 * the type as `array<string,`, takes `string>` for a description, and appends a
				 * full stop — producing `@return array<string, string>.`, which PHPStan cannot
				 * parse at all. Fourteen return types were being silently discarded this way.
				 *
				 * `@param` is unaffected: its pattern requires a `$variable` after the type, which
				 * a spaced generic never satisfies, so those tags are skipped rather than mangled.
				 *
				 * @see tools/php-cs-fixer/vendor/aysnc/wordpress-php-cs-fixer/src/Fixers/PhpdocParamPunctuationFixer.php:82
				 */
				'Aysnc/phpdoc_param_punctuation' => false,

				/**
				 * Ends every `//` line with a full stop, including each line of a wrapped comment.
				 *
				 * The fixer looks at one T_COMMENT token at a time, and each `//` line is its own
				 * token, so `// … has something to` / `// show …` became `… something to.` /
				 * `show ….` — a sentence broken in two.
				 *
				 * Disabled rather than rewording comments to dodge it. phpcs's
				 * Squiz.Commenting.InlineComment.InvalidEndChar still checks the end of the last
				 * line of each comment block, which is the punctuation that matters.
				 *
				 * @see tools/php-cs-fixer/vendor/aysnc/wordpress-php-cs-fixer/src/Fixers/InlineCommentPunctuationFixer.php:43
				 */
				'Aysnc/inline_comment_punctuation' => false,

				/**
				 * Fights phpcbf over the space before a close tag.
				 *
				 * In `<?php // endif; ?>` the space belongs to the comment as far as this rule is
				 * concerned, so it strips it; WPCS requires a space before `?>` and puts it back.
				 * Running the two fixers in sequence never settles.
				 *
				 * Disabled rather than fixed per-file, so the conflict cannot reappear the next
				 * time anyone writes a one-line comment before a close tag. The cost is that
				 * genuine trailing whitespace inside comments is no longer trimmed — phpcs still
				 * reports it, it just is not fixed automatically.
				 */
				'no_trailing_whitespace_in_comment' => false,

				/**
				 * Fights phpcbf over the space in an anonymous class declaration.
				 *
				 * `@PSR12` writes `new class () implements …`; WPCS wants `new class()` and phpcbf
				 * puts it back, so the two rewrite the anonymous classes in the tests on every run.
				 *
				 * Only the one option is overridden — the rest of `class_definition`, which keeps
				 * `extends`/`implements` lists tidy, is left to the preset.
				 */
				'class_definition' => array(
					'space_before_parenthesis' => false,
				),
			),
		);
