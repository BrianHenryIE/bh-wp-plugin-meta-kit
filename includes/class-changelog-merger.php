<?php
/**
 * Merge changelogs which describe the same versions, e.g. `CHANGELOG.md` and the changelog in `readme.txt`.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Model\Changelog_Entry;

/**
 * One file is often updated and the other forgotten, so each version gets the lines of both, without repeats.
 */
class Changelog_Merger {
	/**
	 * Merge changelogs, version by version.
	 *
	 * A version's lines are those of the first changelog which has it, followed by the lines of the others which
	 * are not already there.
	 *
	 * @param array<string, Changelog_Entry> ...$changelogs Each changelog's entries by version; the first is preferred.
	 *
	 * @return array<string, Changelog_Entry> The merged entries by version, newest version first.
	 */
	public function merge( array ...$changelogs ): array {
		/** @var array<string, Changelog_Entry> $merged */
		$merged = array();

		foreach ( $changelogs as $changelog ) {
			foreach ( $changelog as $entry ) {
				$merged[ $entry->version ] = isset( $merged[ $entry->version ] )
					? $this->merge_entries( $merged[ $entry->version ], $entry )
					: $entry;
			}
		}

		uksort(
			$merged,
			fn ( $version_a, $version_b ): int => version_compare( (string) $version_b, (string) $version_a ),
		);

		return $merged;
	}

	/**
	 * Add to the first entry the lines of the second which it does not already have.
	 *
	 * @param Changelog_Entry $preferred  The entry whose lines come first.
	 * @param Changelog_Entry $additional The entry whose lines are added when new.
	 */
	protected function merge_entries( Changelog_Entry $preferred, Changelog_Entry $additional ): Changelog_Entry {
		$lines     = $preferred->lines;
		$seen_keys = array_map( $this->get_comparison_key( ... ), $lines );

		foreach ( $additional->lines as $line ) {
			$comparison_key = $this->get_comparison_key( $line );

			if ( in_array( $comparison_key, $seen_keys, true ) ) {
				continue;
			}

			$lines[]     = $line;
			$seen_keys[] = $comparison_key;
		}

		return new Changelog_Entry(
			version: $preferred->version,
			lines: $lines,
			date: '' !== $preferred->date ? $preferred->date : $additional->date,
		);
	}

	/**
	 * Reduce a line to what it says, so the same change written slightly differently in each file is seen as the same:
	 * the list marker, Markdown emphasis, capitalisation, spacing and a closing full stop are ignored.
	 *
	 * @param string $line A line of an entry.
	 */
	protected function get_comparison_key( string $line ): string {
		$comparison_key = preg_replace( '/^[-+*]\s+/', '', trim( $line ) ) ?? $line;
		$comparison_key = str_replace( array( '*', '_', '`' ), '', $comparison_key );
		$comparison_key = preg_replace( '/\s+/', ' ', $comparison_key ) ?? $comparison_key;

		return strtolower( rtrim( trim( $comparison_key ), '.' ) );
	}
}
