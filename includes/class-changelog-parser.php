<?php
/**
 * Parse a changelog, from `CHANGELOG.md` or a `readme.txt`, into its versions.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Model\Changelog_Entry;

/**
 * Understands version headings written as `## [1.2.0] - 2026-01-31`, `## 1.2.0`, `### v1.2.0` and `= 1.2.0 =`.
 */
class Changelog_Parser {
	/** A heading whose text begins with a version: `1.2.0`, `[1.2.0]`, `v1.2.0`, `1.2.0-beta.1`. */
	protected const VERSION_PATTERN = '/^\[?v?(\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.\-]+)?)\]?(?=$|[\s:,(\-–—])/';

	/**
	 * Parse a changelog.
	 *
	 * Headings which are not versions, e.g. `# Changelog`, `## [Unreleased]`, are not entries, and what is under
	 * `Unreleased` is left out, since it is not in any version a site could have.
	 *
	 * @param string $changelog The contents of `CHANGELOG.md`, or the changelog section of a `readme.txt`.
	 *
	 * @return array<string, Changelog_Entry> Each version's entry, by version, in the order they are written.
	 */
	public function parse( string $changelog ): array {
		/** @var array<string, string[]> $lines_by_version */
		$lines_by_version = array();
		/** @var array<string, string> $date_by_version */
		$date_by_version = array();
		$version         = null;

		foreach ( preg_split( '/\R/', $changelog ) ?: array() as $line ) {
			$line = trim( $line );

			if ( '' === $line || $this->is_link_definition( $line ) ) {
				continue;
			}

			$heading = $this->get_heading( $line );

			if ( null === $heading ) {
				if ( null !== $version ) {
					$lines_by_version[ $version ][] = $this->normalize_bullet( $line );
				}
				continue;
			}

			if ( 1 === preg_match( self::VERSION_PATTERN, $heading['text'], $version_match ) ) {
				$version = $version_match[1];

				$lines_by_version[ $version ] ??= array();
				$date_by_version[ $version ]  ??= $this->get_date( $heading['text'] );
				continue;
			}

			// A heading above the versions' level, or at it, which is not a version, e.g. `## [Unreleased]`, ends the entry.
			if ( ! $heading['is_subheading'] ) {
				$version = null;
				continue;
			}

			// A heading inside a version, e.g. `### Added`.
			if ( null !== $version ) {
				$lines_by_version[ $version ][] = '**' . $heading['text'] . '**';
			}
		}

		$changelog_entries = array();

		foreach ( $lines_by_version as $entry_version => $lines ) {
			$changelog_entries[ (string) $entry_version ] = new Changelog_Entry(
				version: (string) $entry_version,
				lines: $lines,
				date: $date_by_version[ $entry_version ] ?? '',
			);
		}

		return $changelog_entries;
	}

	/**
	 * Parse the `== Changelog ==` section of a readme.
	 *
	 * @param string $readme The contents of `readme.txt`.
	 *
	 * @return array<string, Changelog_Entry> Each version's entry, by version, in the order they are written.
	 */
	public function parse_readme( string $readme ): array {
		$section_lines = array();
		$in_changelog  = false;

		foreach ( preg_split( '/\R/', $readme ) ?: array() as $line ) {
			// A readme's sections are `== Heading ==`, or `## Heading` when it is written as Markdown.
			if ( 1 === preg_match( '/^\s*(?:==\s*([^=].*?)\s*==|##\s+(.+?)\s*#*)\s*$/', $line, $section_heading ) ) {
				$section_title = '' !== $section_heading[1] ? $section_heading[1] : $section_heading[2];
				$in_changelog  = 0 === strcasecmp( 'changelog', trim( $section_title ) );
				continue;
			}

			if ( $in_changelog ) {
				$section_lines[] = $line;
			}
		}

		return $this->parse( implode( "\n", $section_lines ) );
	}

	/**
	 * If the line is a heading, its text and whether it is below the level versions are written at.
	 *
	 * @param string $line A trimmed line.
	 *
	 * @return ?array{text: string, is_subheading: bool}
	 */
	protected function get_heading( string $line ): ?array {
		// A readme.txt heading: `= 1.2.0 =`.
		if ( 1 === preg_match( '/^=\s*([^=].*?)\s*=$/', $line, $readme_heading ) ) {
			return array(
				'text'          => $readme_heading[1],
				'is_subheading' => false,
			);
		}

		// A Markdown heading: `## [1.2.0] - 2026-01-31`, `### Added`.
		if ( 1 === preg_match( '/^(#{1,6})\s+(.+?)\s*#*$/', $line, $markdown_heading ) ) {
			return array(
				'text'          => $markdown_heading[2],
				'is_subheading' => 2 < strlen( $markdown_heading[1] ),
			);
		}

		return null;
	}

	/**
	 * The date in a version's heading, e.g. `2026-01-31` in `[1.2.0] - 2026-01-31`.
	 *
	 * @param string $heading_text The heading without its `#` or `=`.
	 */
	protected function get_date( string $heading_text ): string {
		return 1 === preg_match( '/\d{4}-\d{2}-\d{2}/', $heading_text, $date_match ) ? $date_match[0] : '';
	}

	/**
	 * Write list items the same way, `* A change`, whichever of `-`, `+` or `*` the file uses.
	 *
	 * @param string $line A trimmed line.
	 */
	protected function normalize_bullet( string $line ): string {
		return 1 === preg_match( '/^[-+*]\s+(.*)$/', $line, $bullet_match ) ? '* ' . $bullet_match[1] : $line;
	}

	/**
	 * Is the line a Markdown link definition, e.g. `[1.2.0]: https://example.org/compare/1.1.0...1.2.0`?
	 *
	 * @param string $line A trimmed line.
	 */
	protected function is_link_definition( string $line ): bool {
		return 1 === preg_match( '/^\[[^\]]+\]:\s+\S+/', $line );
	}
}
