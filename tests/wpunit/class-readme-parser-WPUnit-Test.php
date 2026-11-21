<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * The parser calls WordPress functions, e.g. `wp_kses()`, so it is tested here, not in the unit suite.
 *
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Readme_Parser
 */
class Readme_Parser_WPUnit_Test extends WPTestCase {
	protected function get_development_plugin_readme(): string {
		$readme = file_get_contents( dirname( __DIR__, 2 ) . '/development-plugin/readme.txt' );

		$this->assertIsString( $readme );

		return $readme;
	}

	/**
	 * @covers ::parse
	 */
	public function test_headers_are_parsed(): void {
		$result = ( new Readme_Parser() )->parse( $this->get_development_plugin_readme() );

		$this->assertSame(
			'BH Plugin Meta Kit Development/Test/Demo plugin',
			$result->name,
		);
		$this->assertSame( '6.2', $result->requires );
		$this->assertSame( '6.5', $result->tested );
		$this->assertSame( '8.2', $result->requires_php );
		$this->assertSame( '1.0.0', $result->stable_tag );
		$this->assertSame(
			'A plugin to showcase the BH Plugin Meta Kit library and add test helpers.',
			$result->short_description,
		);
	}

	/**
	 * @covers ::parse
	 */
	public function test_sections_are_rendered_as_html(): void {
		$result = ( new Readme_Parser() )->parse( $this->get_development_plugin_readme() );

		$this->assertSame(
			array( 'description', 'faq', 'changelog' ),
			array_keys( $result->sections ),
		);
		$this->assertStringContainsString(
			'<strong>BH Plugin Meta Kit</strong>',
			$result->sections['description'],
		);
		$this->assertStringContainsString(
			'<li>Adds a',
			$result->sections['description'],
		);
		$this->assertStringContainsString(
			'<h3>Should I install this on a live site?</h3>',
			$result->sections['faq'],
		);
	}

	/**
	 * `= 1.0.0 =` is readme.txt's heading syntax, not Markdown's.
	 *
	 * @covers ::parse
	 */
	public function test_changelog_version_headings_are_rendered(): void {
		$result = ( new Readme_Parser() )->parse( $this->get_development_plugin_readme() );

		$this->assertStringContainsString(
			'<h4>1.0.0</h4>',
			$result->sections['changelog'],
		);
		$this->assertStringNotContainsString(
			'= 1.0.0 =',
			$result->sections['changelog'],
		);
	}

	/**
	 * WordPress.org's parser drops sections its directory does not display.
	 *
	 * @covers ::allow_all_sections
	 */
	public function test_sections_wordpress_org_does_not_know_are_kept(): void {
		$readme = "=== My Plugin ===\nStable tag: 1.0.0\n\nShort.\n\n== Description ==\n\nDescribed.\n\n== Developer Hooks ==\n\nFiltered.\n";

		$result = ( new Readme_Parser() )->parse( $readme );

		$this->assertArrayHasKey( 'developer_hooks', $result->sections );
		$this->assertStringContainsString(
			'Filtered.',
			$result->sections['developer_hooks'],
		);
	}

	/**
	 * @covers ::allow_all_sections
	 */
	public function test_readme_with_windows_line_endings_keeps_its_sections(): void {
		$readme = "=== My Plugin ===\r\nStable tag: 1.0.0\r\n\r\nShort.\r\n\r\n== Description ==\r\n\r\nDescribed.\r\n\r\n== Developer Hooks ==\r\n\r\nFiltered.\r\n";

		$result = ( new Readme_Parser() )->parse( $readme );

		$this->assertArrayHasKey( 'description', $result->sections );
		$this->assertArrayHasKey( 'developer_hooks', $result->sections );
	}

	/**
	 * Contributors are WordPress.org usernames; WordPress.org's parser drops those who are not users of its own site.
	 *
	 * @covers ::sanitize_contributors
	 */
	public function test_contributors_who_are_not_users_of_this_site_are_kept(): void {
		$this->assertFalse( get_user_by( 'login', 'brianhenryie' ) );

		$readme = "=== My Plugin ===\nContributors: brianhenryie, @someone-else\nStable tag: 1.0.0\n\nShort.\n";

		$result = ( new Readme_Parser() )->parse( $readme );

		$this->assertSame(
			array( 'brianhenryie', 'someone-else' ),
			$result->contributors,
		);
	}

	/**
	 * @covers ::parse
	 */
	public function test_upgrade_notices_are_by_version(): void {
		$readme = "=== My Plugin ===\nStable tag: 2.0\n\nShort.\n\n== Upgrade Notice ==\n\n= 2.0 =\nBreaking changes.\n";

		$result = ( new Readme_Parser() )->parse( $readme );

		$this->assertArrayHasKey( '2.0', $result->upgrade_notices );
		$this->assertStringContainsString(
			'Breaking changes.',
			$result->upgrade_notices['2.0'],
		);
	}
}
