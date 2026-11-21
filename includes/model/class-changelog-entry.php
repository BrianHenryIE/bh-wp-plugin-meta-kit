<?php
/**
 * One version's entry in a changelog.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Model;

/**
 * What changed in a version, as the lines under its heading.
 */
readonly class Changelog_Entry {
	/**
	 * Constructor.
	 *
	 * @param string   $version The version, e.g. `1.2.0`.
	 * @param string[] $lines   The Markdown lines under the version's heading: `* A change`, `**Added**`, or plain text.
	 * @param string   $date    The release date as written in the heading, e.g. `2026-01-31`, or empty.
	 */
	public function __construct(
		public string $version,
		public array $lines = array(),
		public string $date = '',
	) {
	}
}
