<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Changelog_Parser
 */
class Changelog_Parser_Unit_Test extends Unit_Testcase {
	/**
	 * @covers ::parse
	 */
	public function test_keep_a_changelog_format(): void {
		$changelog = <<<'MARKDOWN'
			# Changelog

			All notable changes to this project will be documented in this file.

			## [Unreleased]

			- Not released yet

			## [1.1.0] - 2026-03-01

			### Added

			- A settings link
			- A changelog tab

			### Fixed

			- A typo

			## [1.0.0] - 2026-01-31

			- First release

			[1.1.0]: https://example.org/compare/1.0.0...1.1.0
			[1.0.0]: https://example.org/releases/1.0.0
			MARKDOWN;

		$result = ( new Changelog_Parser() )->parse( $changelog );

		$this->assertSame( array( '1.1.0', '1.0.0' ), array_keys( $result ) );

		$this->assertSame( '1.1.0', $result['1.1.0']->version );
		$this->assertSame( '2026-03-01', $result['1.1.0']->date );
		$this->assertSame(
			array( '**Added**', '* A settings link', '* A changelog tab', '**Fixed**', '* A typo' ),
			$result['1.1.0']->lines,
		);

		$this->assertSame( '2026-01-31', $result['1.0.0']->date );
		$this->assertSame( array( '* First release' ), $result['1.0.0']->lines );
	}

	/**
	 * @covers ::parse
	 */
	public function test_readme_txt_format(): void {
		$changelog = "= 1.1.0 =\n* A settings link\n* A typo\n\n= 1.0.0 =\n* First release\n";

		$result = ( new Changelog_Parser() )->parse( $changelog );

		$this->assertSame( array( '1.1.0', '1.0.0' ), array_keys( $result ) );
		$this->assertSame(
			array( '* A settings link', '* A typo' ),
			$result['1.1.0']->lines,
		);
		$this->assertSame( '', $result['1.1.0']->date );
		$this->assertSame( array( '* First release' ), $result['1.0.0']->lines );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function version_headings(): array {
		return array(
			'markdown, bracketed with date' => array( '## [1.2.3] - 2026-01-31', '1.2.3' ),
			'markdown, plain'               => array( '## 1.2.3', '1.2.3' ),
			'markdown, v prefix'            => array( '### v1.2.3', '1.2.3' ),
			'markdown, pre-release'         => array( '## 1.2.3-beta.1', '1.2.3-beta.1' ),
			'markdown, date in brackets'    => array( '## 1.2.3 (2026-01-31)', '1.2.3' ),
			'readme'                        => array( '= 1.2.3 =', '1.2.3' ),
			'readme, with date'             => array( '= 1.2.3 - 2026-01-31 =', '1.2.3' ),
			'readme, two part version'      => array( '= 2.0 =', '2.0' ),
			'windows line endings'          => array( "= 1.2.3 =\r", '1.2.3' ),
		);
	}

	/**
	 * @covers ::parse
	 * @covers ::get_heading
	 *
	 * @dataProvider version_headings
	 *
	 * @param string $heading          The line as written in the file.
	 * @param string $expected_version The version it names.
	 */
	public function test_version_headings_are_recognised( string $heading, string $expected_version ): void {
		$result = ( new Changelog_Parser() )->parse( $heading . "\n* A change\n" );

		$this->assertSame( array( $expected_version ), array_keys( $result ) );
		$this->assertSame(
			array( '* A change' ),
			$result[ $expected_version ]->lines,
		);
	}

	/**
	 * @covers ::get_date
	 */
	public function test_date_is_read_from_a_readme_heading(): void {
		$result = ( new Changelog_Parser() )->parse( "= 1.2.3 - 2026-01-31 =\n* A change\n" );

		$this->assertSame( '2026-01-31', $result['1.2.3']->date );
	}

	/**
	 * @covers ::normalize_bullet
	 */
	public function test_list_markers_are_written_the_same_way(): void {
		$result = ( new Changelog_Parser() )->parse( "## 1.0.0\n- Dash\n+ Plus\n* Star\nPlain text\n" );

		$this->assertSame(
			array( '* Dash', '* Plus', '* Star', 'Plain text' ),
			$result['1.0.0']->lines,
		);
	}

	/**
	 * @covers ::parse
	 */
	public function test_changelog_without_versions_has_no_entries(): void {
		$result = ( new Changelog_Parser() )->parse( "# Changelog\n\nNothing yet.\n" );

		$this->assertSame( array(), $result );
	}

	/**
	 * @covers ::parse_readme
	 */
	public function test_parse_readme_reads_only_the_changelog_section(): void {
		$readme = <<<'README'
			=== My Plugin ===
			Stable tag: 1.1.0

			Short description.

			== Description ==

			* Not a change

			== Changelog ==

			= 1.1.0 =
			* A settings link

			= 1.0.0 =
			* First release

			== Upgrade Notice ==

			= 1.1.0 =
			Upgrade for the settings link.
			README;

		$result = ( new Changelog_Parser() )->parse_readme( $readme );

		$this->assertSame( array( '1.1.0', '1.0.0' ), array_keys( $result ) );
		$this->assertSame( array( '* A settings link' ), $result['1.1.0']->lines );
		$this->assertSame( array( '* First release' ), $result['1.0.0']->lines );
	}

	/**
	 * @covers ::parse_readme
	 */
	public function test_parse_readme_written_as_markdown(): void {
		$readme = "# My Plugin\n\n## Description\n\nText.\n\n## Changelog\n\n### 1.0.0\n\n* First release\n\n## Upgrade Notice\n\n### 1.0.0\n\nUpgrade.\n";

		$result = ( new Changelog_Parser() )->parse_readme( $readme );

		$this->assertSame( array( '1.0.0' ), array_keys( $result ) );
		$this->assertSame( array( '* First release' ), $result['1.0.0']->lines );
	}

	/**
	 * @covers ::parse_readme
	 */
	public function test_parse_readme_without_a_changelog_has_no_entries(): void {
		$result = ( new Changelog_Parser() )->parse_readme( "=== My Plugin ===\n\n== Description ==\n\n= 1.0.0 =\n* Not a change\n" );

		$this->assertSame( array(), $result );
	}
}
