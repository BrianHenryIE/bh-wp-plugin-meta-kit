<?php
/**
 * Something the plugin needs which the site might not have.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Model;

use Closure;

/**
 * A requirement, e.g. a PHP extension or another plugin, and how to tell whether the site meets it.
 *
 * ```
 * new Dependency(
 *     name: 'GMP (GNU Multiple Precision Arithmetic Library) PHP extension',
 *     is_satisfied: fn (): bool => extension_loaded( 'gmp' ),
 *     description: 'It is needed for the calculations to derive Bitcoin payment addresses. Please contact your hosting provider for support.',
 *     url: 'https://www.php.net/manual/en/book.gmp.php',
 * );
 * ```
 */
readonly class Dependency {
	/**
	 * Constructor.
	 *
	 * @param string          $name         What is needed, as it should read in "… is required", already translated.
	 * @param Closure(): bool $is_satisfied Returns true when the site has it. Called on every wp-admin page, so keep it cheap.
	 * @param string          $description  Why it is needed and what to do about it, already translated. May contain links, `<strong>` and `<em>`.
	 * @param string          $url          Where to read about it; the name is linked to it.
	 */
	public function __construct(
		public string $name,
		public Closure $is_satisfied,
		public string $description = '',
		public string $url = '',
	) {
	}
}
