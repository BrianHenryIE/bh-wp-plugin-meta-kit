<?php
/**
 * What a plugin's `readme.txt` says, as the fields the plugin details modal can use.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Model;

/**
 * The parsed readme. Sections are HTML: the parser renders the readme's Markdown.
 */
readonly class Readme {
	/**
	 * Constructor.
	 *
	 * @param string                $name              The `=== Name ===` heading.
	 * @param string                $requires          `Requires at least`.
	 * @param string                $tested            `Tested up to`.
	 * @param string                $requires_php      `Requires PHP`.
	 * @param string                $stable_tag        `Stable tag`.
	 * @param string[]              $contributors      `Contributors`, without `@`.
	 * @param string                $donate_link       `Donate link`.
	 * @param string                $short_description The paragraph before the first section.
	 * @param array<string, string> $sections          Section key, e.g. `description`, `changelog`, to its HTML.
	 * @param array<string, string> $upgrade_notices   Version to its upgrade notice's HTML.
	 */
	public function __construct(
		public string $name = '',
		public string $requires = '',
		public string $tested = '',
		public string $requires_php = '',
		public string $stable_tag = '',
		public array $contributors = array(),
		public string $donate_link = '',
		public string $short_description = '',
		public array $sections = array(),
		public array $upgrade_notices = array(),
	) {
	}
}
