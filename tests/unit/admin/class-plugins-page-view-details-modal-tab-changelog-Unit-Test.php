<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Changelog_Merger;
use BrianHenryIE\Plugin_Meta_Kit\Changelog_Parser;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_File_Finder;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use stdClass;

/**
 * The parser, merger and file finder are not mocked: they need nothing from WordPress, and what matters here is
 * what the Changelog tab ends up showing.
 *
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Tab_Changelog
 */
class Plugins_Page_View_Details_Modal_Tab_Changelog_Unit_Test extends Unit_Testcase {
	protected const README = <<<'README'
		=== My Plugin ===
		Stable tag: 1.1.0

		Short description.

		== Description ==

		Described.

		== Changelog ==

		= 1.1.0 =
		* Added a settings link
		* Only the readme mentions this

		= 1.0.0 =
		* First release
		README;

	protected const CHANGELOG_MD = <<<'MARKDOWN'
		# Changelog

		## [1.2.0] - 2026-03-01

		- Only CHANGELOG.md has this version

		## [1.1.0] - 2026-02-01

		- Added a settings link.
		- Only CHANGELOG.md mentions this

		## [1.0.0] - 2026-01-31

		- First release
		MARKDOWN;

	/** A temporary directory standing in for `wp-content/plugins`. */
	protected string $plugins_directory;

	protected function setUp(): void {
		parent::setUp();

		$this->plugins_directory = sys_get_temp_dir() . '/' . uniqid( 'bh-wp-plugin-meta-kit-' );
		mkdir( $this->plugins_directory . '/my-plugin', 0777, true );

		$plugins_directory = $this->plugins_directory;
		\Patchwork\redefine(
			'constant',
			fn ( string $name ) => 'WP_PLUGIN_DIR' === $name ? $plugins_directory : \Patchwork\relay( array( $name ) ),
		);
	}

	protected function tearDown(): void {
		foreach ( glob( $this->plugins_directory . '/my-plugin/*' ) ?: array() as $file_path ) {
			unlink( $file_path );
		}
		rmdir( $this->plugins_directory . '/my-plugin' );
		rmdir( $this->plugins_directory );

		parent::tearDown();
	}

	protected function get_sut(): Plugins_Page_View_Details_Modal_Tab_Changelog {
		$settings = new Plugin_Meta_Kit_Settings(
			plugin_slug: 'my-plugin',
			plugin_basename: 'my-plugin/my-plugin.php',
			plugin_name: 'My Plugin',
		);

		return new Plugins_Page_View_Details_Modal_Tab_Changelog(
			$settings,
			new Plugin_File_Finder( $settings ),
			new Changelog_Parser(),
			new Changelog_Merger(),
		);
	}

	/**
	 * Write a file in the plugin's directory.
	 *
	 * @param string $filename The file's name.
	 * @param string $contents Its contents.
	 */
	protected function put_file( string $filename, string $contents ): void {
		file_put_contents(
			$this->plugins_directory . '/my-plugin/' . $filename,
			$contents,
		);
	}

	/**
	 * Call the filter for this plugin and return the `changelog` section, or null when it was not set.
	 *
	 * @param false|stdClass $result The value an earlier filter returned.
	 */
	protected function get_changelog_section( false|stdClass $result = false ): ?string {
		$filtered = $this->get_sut()->add_changelog_tab( $result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		if ( ! $filtered instanceof stdClass ) {
			return null;
		}

		$sections = get_object_vars( $filtered )['sections'] ?? array();
		$this->assertIsArray( $sections );

		$changelog = $sections['changelog'] ?? null;
		$this->assertTrue( null === $changelog || is_string( $changelog ) );

		return $changelog;
	}

	/**
	 * @covers ::add_changelog_tab
	 */
	public function test_other_plugins_are_left_alone(): void {
		$this->put_file( 'CHANGELOG.md', self::CHANGELOG_MD );

		$result = $this->get_sut()->add_changelog_tab( false, 'plugin_information', (object) array( 'slug' => 'another-plugin' ) );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::add_changelog_tab
	 */
	public function test_requests_without_a_slug_are_left_alone(): void {
		$this->put_file( 'CHANGELOG.md', self::CHANGELOG_MD );

		$result = $this->get_sut()->add_changelog_tab( false, 'hot_tags', new stdClass() );

		$this->assertFalse( $result );
	}

	/**
	 * Without a `CHANGELOG.md` the readme's changelog, which the readme class has already set, is what is shown.
	 *
	 * @covers ::add_changelog_tab
	 * @covers ::get_changelog_entries
	 */
	public function test_without_a_changelog_md_the_result_is_not_changed(): void {
		$this->put_file( 'readme.txt', self::README );

		$earlier_result           = new stdClass();
		$earlier_result->sections = array( 'changelog' => '<h4>1.1.0</h4>' );

		$result = $this->get_sut()->add_changelog_tab( $earlier_result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		$this->assertSame( $earlier_result, $result );
		$this->assertSame(
			array( 'changelog' => '<h4>1.1.0</h4>' ),
			$earlier_result->sections,
		);
	}

	/**
	 * @covers ::get_changelog_entries
	 * @covers ::get_file_contents
	 */
	public function test_an_empty_changelog_md_does_not_change_the_result(): void {
		$this->put_file( 'CHANGELOG.md', "\n" );

		$this->assertFalse( $this->get_sut()->add_changelog_tab( false, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) ) );
	}

	/**
	 * @covers ::get_changelog_entries
	 */
	public function test_a_changelog_md_without_versions_does_not_change_the_result(): void {
		$this->put_file( 'CHANGELOG.md', "# Changelog\n\nNothing yet.\n" );

		$this->assertFalse( $this->get_sut()->add_changelog_tab( false, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) ) );
	}

	/**
	 * @covers ::add_changelog_tab
	 * @covers ::get_changelog_html
	 */
	public function test_changelog_md_alone_is_rendered(): void {
		$this->put_file(
			'CHANGELOG.md',
			"## [1.0.0] - 2026-01-31\n\n### Added\n\n- First release\n- A second thing\n",
		);

		$changelog = $this->get_changelog_section();

		$this->assertSame(
			"<h4>1.0.0 – 2026-01-31</h4>\n\n<p><strong>Added</strong></p>\n\n<ul>\n<li>First release</li>\n<li>A second thing</li>\n</ul>",
			$changelog,
		);
	}

	/**
	 * @covers ::add_changelog_tab
	 * @covers ::get_changelog_entries
	 */
	public function test_changelog_md_and_readme_are_merged_for_each_version(): void {
		$this->put_file( 'CHANGELOG.md', self::CHANGELOG_MD );
		$this->put_file( 'readme.txt', self::README );

		$changelog = $this->get_changelog_section();

		$this->assertSame(
			implode(
				"\n\n",
				array(
					'<h4>1.2.0 – 2026-03-01</h4>',
					"<ul>\n<li>Only CHANGELOG.md has this version</li>\n</ul>",
					'<h4>1.1.0 – 2026-02-01</h4>',
					"<ul>\n<li>Added a settings link.</li>\n<li>Only CHANGELOG.md mentions this</li>\n<li>Only the readme mentions this</li>\n</ul>",
					'<h4>1.0.0 – 2026-01-31</h4>',
					"<ul>\n<li>First release</li>\n</ul>",
				),
			),
			$changelog,
		);
	}

	/**
	 * @covers ::get_changelog_entries
	 */
	public function test_a_line_in_both_files_is_shown_once(): void {
		$this->put_file( 'CHANGELOG.md', self::CHANGELOG_MD );
		$this->put_file( 'readme.txt', self::README );

		$changelog = (string) $this->get_changelog_section();

		$this->assertSame( 1, substr_count( $changelog, 'Added a settings link' ) );
		$this->assertSame( 1, substr_count( $changelog, 'First release' ) );
	}

	/**
	 * @covers ::get_changelog_entries
	 */
	public function test_a_version_only_in_the_readme_is_shown(): void {
		$this->put_file( 'CHANGELOG.md', "## 1.0.0\n\n- First release\n" );
		$this->put_file( 'readme.txt', self::README );

		$changelog = (string) $this->get_changelog_section();

		$this->assertStringContainsString( '<h4>1.1.0</h4>', $changelog );
		$this->assertStringContainsString(
			'<li>Only the readme mentions this</li>',
			$changelog,
		);
		$this->assertLessThan(
			strpos( $changelog, '<h4>1.0.0</h4>' ),
			strpos( $changelog, '<h4>1.1.0</h4>' ),
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function filenames(): array {
		return array(
			'uppercase'   => array( 'CHANGELOG.md', 'README.txt' ),
			'lowercase'   => array( 'changelog.md', 'readme.txt' ),
			'capitalised' => array( 'Changelog.md', 'Readme.txt' ),
			'mixed'       => array( 'CHANGElog.md', 'README.TXT' ),
		);
	}

	/**
	 * @covers ::get_file_contents
	 *
	 * @dataProvider filenames
	 *
	 * @param string $changelog_filename The name the plugin gave its changelog.
	 * @param string $readme_filename    The name the plugin gave its readme.
	 */
	public function test_files_are_found_whatever_their_capitalisation( string $changelog_filename, string $readme_filename ): void {
		$this->put_file( $changelog_filename, self::CHANGELOG_MD );
		$this->put_file( $readme_filename, self::README );

		$changelog = (string) $this->get_changelog_section();

		$this->assertStringContainsString(
			'Only CHANGELOG.md mentions this',
			$changelog,
		);
		$this->assertStringContainsString(
			'Only the readme mentions this',
			$changelog,
		);
	}

	/**
	 * @covers ::add_changelog_tab
	 */
	public function test_other_sections_and_fields_are_kept(): void {
		$this->put_file( 'CHANGELOG.md', self::CHANGELOG_MD );

		$earlier_result           = new stdClass();
		$earlier_result->name     = 'My Plugin';
		$earlier_result->sections = array(
			'description' => '<p>Described.</p>',
			'changelog'   => '<p>The readme changelog, to be replaced.</p>',
		);

		$result = $this->get_sut()->add_changelog_tab( $earlier_result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		$this->assertSame( $earlier_result, $result );
		$this->assertSame( 'My Plugin', $earlier_result->name );
		$sections = $earlier_result->sections;
		$this->assertIsArray( $sections );
		$this->assertSame( '<p>Described.</p>', $sections['description'] );

		$changelog = $sections['changelog'];
		$this->assertIsString( $changelog );
		$this->assertStringContainsString( '<h4>1.2.0', $changelog );
		$this->assertStringNotContainsString( 'to be replaced', $changelog );
	}

	/**
	 * `Unreleased` is not in any version a site could have installed.
	 *
	 * @covers ::get_changelog_entries
	 */
	public function test_unreleased_changes_are_not_shown(): void {
		$this->put_file(
			'CHANGELOG.md',
			"## [Unreleased]\n\n- Not out yet\n\n## [1.0.0]\n\n- First release\n",
		);

		$changelog = (string) $this->get_changelog_section();

		$this->assertStringNotContainsString( 'Not out yet', $changelog );
		$this->assertStringContainsString( 'First release', $changelog );
	}
}
