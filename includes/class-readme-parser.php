<?php
/**
 * Parse a plugin's `readme.txt` into a {@see \BrianHenryIE\Plugin_Meta_Kit\Model\Readme}.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Model\Readme;
use Syntatis\WPPluginReadMeParser\Parser;

/**
 * Extends the syntatis/wp-plugin-readme-parser parser, which models WordPress.org's, to accept any readme a plugin
 * might ship rather than only what the directory allows, and to return a typed object.
 */
class Readme_Parser extends Parser {
	/**
	 * Constructor.
	 *
	 * The parent constructor is not called: it parses the string it is given straight away.
	 */
	public function __construct() {
	}

	/**
	 * Parse a readme.
	 *
	 * @param string $body The contents of `readme.txt`.
	 */
	public function parse( string $body ): Readme {
		$this->allow_all_sections( $body );

		$this->parse_readme_contents( $body );

		return new Readme(
			name: (string) $this->name,
			requires: (string) $this->requires,
			tested: (string) $this->tested,
			requires_php: (string) $this->requires_php,
			stable_tag: (string) $this->stable_tag,
			contributors: array_values( array_filter( $this->contributors, is_string( ... ) ) ),
			donate_link: (string) $this->donate_link,
			short_description: (string) $this->short_description,
			sections: $this->strings_by_key( $this->sections ),
			upgrade_notices: $this->strings_by_key( $this->upgrade_notice ),
		);
	}

	/**
	 * Keep the entries which are strings, with string keys: a version such as `2` would otherwise be an integer key.
	 *
	 * @param array<array-key, mixed> $values Sections by key, or upgrade notices by version.
	 *
	 * @return array<string, string>
	 */
	protected function strings_by_key( array $values ): array {
		$strings = array();

		foreach ( $values as $key => $value ) {
			if ( is_string( $value ) ) {
				$strings[ (string) $key ] = trim( $value );
			}
		}

		return $strings;
	}

	/**
	 * The parent only keeps the sections WordPress.org allows. Plugins add their own, so every `== Heading ==` in the
	 * file is allowed. `\R` rather than `\n`, so a readme written on Windows keeps its sections.
	 *
	 * @param string $body The contents of `readme.txt`.
	 */
	protected function allow_all_sections( string $body ): void {
		$lines = preg_split( '/\R/', $body );

		if ( false === $lines ) {
			return;
		}

		foreach ( $lines as $line ) {
			if ( 1 !== preg_match( '/^==\s*[^=]*\s*==$/', $line ) ) {
				continue;
			}

			$section = sanitize_key( str_replace( ' ', '_', trim( $line, "= \n\r\t\v\0" ) ) );
			$section = $this->alias_sections[ $section ] ?? $section;

			if ( ! in_array( $section, $this->expected_sections, true ) ) {
				$this->expected_sections[] = $section;
			}
		}
	}

	/**
	 * The parent removes contributors who are not users of this site. They are WordPress.org usernames, so they are
	 * all kept.
	 *
	 * @param array<array-key, mixed> $users The `Contributors` names.
	 *
	 * @return array<array-key, string>
	 */
	protected function sanitize_contributors( $users ) {
		$contributors = array();

		foreach ( $users as $index => $name ) {
			if ( is_string( $name ) ) {
				$contributors[ $index ] = ltrim( $name, '@' );
			}
		}

		return $contributors;
	}
}
