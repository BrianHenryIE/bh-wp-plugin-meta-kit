<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Model\Changelog_Entry;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Changelog_Merger
 */
class Changelog_Merger_Unit_Test extends Unit_Testcase {
	/**
	 * @param array<string, string[]> $lines_by_version Each version's lines.
	 *
	 * @return array<string, Changelog_Entry>
	 */
	protected function get_changelog( array $lines_by_version ): array {
		$changelog = array();

		foreach ( $lines_by_version as $version => $lines ) {
			$changelog[ $version ] = new Changelog_Entry( version: $version, lines: $lines );
		}

		return $changelog;
	}

	/**
	 * @covers ::merge
	 * @covers ::merge_entries
	 */
	public function test_lines_in_both_are_not_repeated(): void {
		$changelog_md = $this->get_changelog( array( '1.0.0' => array( '* In both', '* Only in CHANGELOG.md' ) ) );
		$readme       = $this->get_changelog( array( '1.0.0' => array( '* In both', '* Only in the readme' ) ) );

		$result = ( new Changelog_Merger() )->merge( $changelog_md, $readme );

		$this->assertSame(
			array( '* In both', '* Only in CHANGELOG.md', '* Only in the readme' ),
			$result['1.0.0']->lines,
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function lines_which_say_the_same(): array {
		return array(
			'closing full stop' => array( '* Fixed the typo.', '* Fixed the typo' ),
			'capitalisation'    => array( '* Fixed the Typo', '* fixed the typo' ),
			'spacing'           => array( '* Fixed  the typo', '* Fixed the typo ' ),
			'list marker'       => array( '- Fixed the typo', '* Fixed the typo' ),
			'emphasis'          => array( '* Fixed the `typo` in **Settings**', '* Fixed the typo in Settings' ),
		);
	}

	/**
	 * @covers ::get_comparison_key
	 *
	 * @dataProvider lines_which_say_the_same
	 *
	 * @param string $changelog_md_line The line as `CHANGELOG.md` has it.
	 * @param string $readme_line       The line as the readme has it.
	 */
	public function test_lines_written_slightly_differently_are_the_same( string $changelog_md_line, string $readme_line ): void {
		$changelog_md = $this->get_changelog( array( '1.0.0' => array( $changelog_md_line ) ) );
		$readme       = $this->get_changelog( array( '1.0.0' => array( $readme_line ) ) );

		$result = ( new Changelog_Merger() )->merge( $changelog_md, $readme );

		$this->assertSame( array( $changelog_md_line ), $result['1.0.0']->lines );
	}

	/**
	 * The case this is for: one file was updated for a release and the other forgotten.
	 *
	 * @covers ::merge
	 */
	public function test_versions_in_only_one_changelog_are_kept(): void {
		$changelog_md = $this->get_changelog(
			array(
				'1.2.0' => array( '* Only CHANGELOG.md was updated' ),
				'1.0.0' => array( '* First release' ),
			),
		);
		$readme       = $this->get_changelog(
			array(
				'1.1.0' => array( '* Only the readme was updated' ),
				'1.0.0' => array( '* First release' ),
			),
		);

		$result = ( new Changelog_Merger() )->merge( $changelog_md, $readme );

		$this->assertSame(
			array( '1.2.0', '1.1.0', '1.0.0' ),
			array_keys( $result ),
		);
		$this->assertSame(
			array( '* Only CHANGELOG.md was updated' ),
			$result['1.2.0']->lines,
		);
		$this->assertSame(
			array( '* Only the readme was updated' ),
			$result['1.1.0']->lines,
		);
		$this->assertSame( array( '* First release' ), $result['1.0.0']->lines );
	}

	/**
	 * @covers ::merge
	 */
	public function test_versions_are_newest_first_by_version_not_as_text(): void {
		$changelog_md = $this->get_changelog(
			array(
				'1.2.0'  => array( '* a' ),
				'1.10.0' => array( '* b' ),
				'1.9.0'  => array( '* c' ),
			),
		);

		$result = ( new Changelog_Merger() )->merge( $changelog_md );

		$this->assertSame(
			array( '1.10.0', '1.9.0', '1.2.0' ),
			array_keys( $result ),
		);
	}

	/**
	 * @covers ::merge_entries
	 */
	public function test_date_is_taken_from_whichever_has_it(): void {
		$changelog_md = array( '1.0.0' => new Changelog_Entry( version: '1.0.0', lines: array( '* a' ) ) );
		$readme       = array( '1.0.0' => new Changelog_Entry( version: '1.0.0', lines: array( '* a' ), date: '2026-01-31' ) );

		$result = ( new Changelog_Merger() )->merge( $changelog_md, $readme );

		$this->assertSame( '2026-01-31', $result['1.0.0']->date );
	}

	/**
	 * @covers ::merge
	 */
	public function test_nothing_to_merge(): void {
		$this->assertSame(
			array(),
			( new Changelog_Merger() )->merge( array(), array() ),
		);
	}
}
