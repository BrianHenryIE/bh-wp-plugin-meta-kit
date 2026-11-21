<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Model\Readme;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Readme_Parser;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use Mockery;
use ReflectionMethod;
use stdClass;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme
 */
class Plugins_Page_View_Details_Modal_Readme_Unit_Test extends Unit_Testcase {
	/** A temporary directory standing in for `wp-content/plugins`. */
	protected string $plugins_directory;

	protected const README_CONTENTS = "=== My Plugin ===\nRequires at least: 6.2\n";

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

	protected function get_settings(): Plugin_Meta_Kit_Settings {
		return new Plugin_Meta_Kit_Settings(
			plugin_slug: 'my-plugin',
			plugin_basename: 'my-plugin/my-plugin.php',
			plugin_name: 'My Plugin',
		);
	}

	/**
	 * The system under test, with the readme parser (which needs WordPress) mocked.
	 *
	 * @param ?Readme $readme What the parser returns, or null when it must not be called.
	 */
	protected function get_sut( ?Readme $readme = null ): Plugins_Page_View_Details_Modal_Readme {
		$readme_parser = Mockery::mock( Readme_Parser::class );

		if ( null === $readme ) {
			$readme_parser->shouldNotReceive( 'parse' );
		} else {
			$readme_parser->shouldReceive( 'parse' )
				->once()
				->with( self::README_CONTENTS )
				->andReturn( $readme );
		}

		return new Plugins_Page_View_Details_Modal_Readme( $this->get_settings(), $readme_parser );
	}

	/**
	 * Write the plugin's readme.
	 *
	 * @param string $readme_filename The name the plugin gave its readme.
	 */
	protected function put_readme( string $readme_filename = 'readme.txt' ): void {
		file_put_contents(
			$this->plugins_directory . '/my-plugin/' . $readme_filename,
			self::README_CONTENTS,
		);
	}

	/**
	 * Call the filter for this plugin and return the plugin information as its properties.
	 *
	 * @param Plugins_Page_View_Details_Modal_Readme $sut    The system under test.
	 * @param false|stdClass                         $result The value an earlier filter returned.
	 *
	 * @return array<mixed> The plugin information's properties, by name.
	 */
	protected function add_tabs( Plugins_Page_View_Details_Modal_Readme $sut, false|stdClass $result = false ): array {
		$filtered = $sut->add_tabs( $result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		$this->assertInstanceOf( stdClass::class, $filtered );

		return get_object_vars( $filtered );
	}

	/**
	 * @param array<string, string> $plugin_headers The headers of the plugin's main file.
	 */
	protected function mock_plugin_headers( array $plugin_headers = array(
		'Name'    => 'My Plugin',
		'Version' => '1.2.3',
	) ): void {
		WP_Mock::userFunction( 'get_plugin_data' )
			->with( $this->plugins_directory . '/my-plugin/my-plugin.php' )
			->andReturn( $plugin_headers );

		WP_Mock::userFunction( 'get_plugins' )
			->andReturn( array( 'my-plugin/my-plugin.php' => $plugin_headers ) );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_other_plugins_are_left_alone(): void {
		WP_Mock::userFunction( 'get_plugin_data' )->never();

		$result = $this->get_sut()->add_tabs( false, 'plugin_information', (object) array( 'slug' => 'another-plugin' ) );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_requests_without_a_slug_are_left_alone(): void {
		WP_Mock::userFunction( 'get_plugin_data' )->never();

		$result = $this->get_sut()->add_tabs( false, 'hot_tags', new stdClass() );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_without_a_readme_the_minimum_and_plugin_headers_are_returned(): void {
		$this->mock_plugin_headers();

		$sut = $this->get_sut();

		$result = $this->add_tabs( $sut );

		$this->assertSame( 'my-plugin', $result['slug'] );
		$this->assertSame( 'My Plugin', $result['name'] );
		$this->assertSame( array(), $result['sections'] );
		$this->assertSame( '1.2.3', $result['Version'] );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_an_earlier_filters_result_is_added_to(): void {
		$this->mock_plugin_headers();

		$earlier_result           = new stdClass();
		$earlier_result->name     = 'Name From Another Filter';
		$earlier_result->homepage = 'https://example.org';

		$sut      = $this->get_sut();
		$filtered = $sut->add_tabs( $earlier_result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );
		$result   = $this->add_tabs( $sut, $earlier_result );

		$this->assertSame( $earlier_result, $filtered );
		$this->assertSame( 'Name From Another Filter', $result['name'] );
		$this->assertSame( 'https://example.org', $result['homepage'] );
		$this->assertSame( 'my-plugin', $result['slug'] );
	}

	/**
	 * @covers ::add_tabs
	 * @covers ::get_readme_fields
	 */
	public function test_readme_fields_are_added(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		$this->put_readme();

		$sut = $this->get_sut(
			new Readme(
				name: 'My Plugin Readme Name',
				requires: '6.2',
				tested: '6.4',
				requires_php: '8.2',
				donate_link: 'https://example.org/donate',
				short_description: 'Short.',
				sections: array( 'description' => '<p>What it does.</p>' ),
				upgrade_notices: array( '1.2.3' => '<p>Upgrade.</p>' ),
			),
		);

		$result = $this->add_tabs( $sut );

		$this->assertSame( 'My Plugin Readme Name', $result['name'] );
		$this->assertSame( '6.2', $result['requires'] );
		$this->assertSame( '6.4', $result['tested'] );
		$this->assertSame( '8.2', $result['requires_php'] );
		$this->assertSame( 'https://example.org/donate', $result['donate_link'] );
		$this->assertSame( 'Short.', $result['short_description'] );
		$this->assertSame(
			array( 'description' => '<p>What it does.</p>' ),
			$result['sections'],
		);
		$this->assertSame(
			array( '1.2.3' => '<p>Upgrade.</p>' ),
			$result['upgrade_notice'],
		);
	}

	/**
	 * @covers ::add_tabs
	 * @covers ::get_readme_fields
	 */
	public function test_empty_readme_fields_do_not_replace_an_earlier_filters(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		$this->put_readme();

		$earlier_result               = new stdClass();
		$earlier_result->requires     = '5.9';
		$earlier_result->requires_php = '7.4';

		$result = $this->add_tabs( $this->get_sut( new Readme( requires_php: '8.2' ) ), $earlier_result );

		$this->assertSame( '5.9', $result['requires'] );
		$this->assertSame( '8.2', $result['requires_php'] );
		$this->assertArrayNotHasKey( 'donate_link', $result );
		$this->assertArrayNotHasKey( 'contributors', $result );
	}

	/**
	 * Core's modal reads `display_name`, `profile` and `avatar` of each contributor.
	 *
	 * @covers ::get_contributors
	 */
	public function test_contributors_are_in_the_shape_core_prints(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		$this->put_readme();

		WP_Mock::userFunction( 'get_avatar_url' )
			->with( '', array( 'force_default' => true ) )
			->andReturn( 'https://secure.gravatar.com/avatar/?d=mm' );

		$result = $this->add_tabs( $this->get_sut( new Readme( contributors: array( 'brianhenryie' ) ) ) );

		$this->assertSame(
			array(
				'brianhenryie' => array(
					'display_name' => 'brianhenryie',
					'profile'      => 'https://profiles.wordpress.org/brianhenryie/',
					'avatar'       => 'https://secure.gravatar.com/avatar/?d=mm',
				),
			),
			$result['contributors'],
		);
	}

	/**
	 * @covers ::get_readme_fields
	 */
	public function test_an_empty_readme_is_not_parsed(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		touch( $this->plugins_directory . '/my-plugin/readme.txt' );

		$result = $this->add_tabs( $this->get_sut() );

		$this->assertArrayNotHasKey( 'requires', $result );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function readme_filenames(): array {
		return array(
			'lowercase'           => array( 'readme.txt' ),
			'uppercase name'      => array( 'README.txt' ),
			'capitalised name'    => array( 'Readme.txt' ),
			'uppercase extension' => array( 'README.TXT' ),
		);
	}

	/**
	 * Linux filesystems are case-sensitive, and plugins do not agree on the capitalisation.
	 *
	 * @covers ::add_tabs
	 * @covers ::get_readme_path
	 *
	 * @dataProvider readme_filenames
	 *
	 * @param string $readme_filename The name the plugin gave its readme.
	 */
	public function test_readme_is_found_whatever_its_capitalisation( string $readme_filename ): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		$this->put_readme( $readme_filename );

		$sut = $this->get_sut( new Readme( requires: '6.2' ) );

		// The path is checked too: on a case-insensitive filesystem the file is read whichever spelling is tried.
		$get_readme_path = new ReflectionMethod( $sut, 'get_readme_path' );
		$this->assertSame(
			$this->plugins_directory . '/my-plugin/' . $readme_filename,
			$get_readme_path->invoke( $sut ),
		);

		$result = $this->add_tabs( $sut );

		$this->assertSame( '6.2', $result['requires'] );
	}

	/**
	 * @covers ::add_tabs
	 * @covers ::get_readme_path
	 */
	public function test_other_text_files_are_not_mistaken_for_the_readme(): void {
		$this->mock_plugin_headers();
		touch( $this->plugins_directory . '/my-plugin/changelog.txt' );
		touch( $this->plugins_directory . '/my-plugin/readme.txt.bak' );

		$result = $this->add_tabs( $this->get_sut() );

		$this->assertArrayNotHasKey( 'requires', $result );
	}

	/**
	 * The modal warns "This plugin has not been tested with your current version of WordPress" when the readme
	 * names the minor version and the site is on a patch release of it.
	 *
	 * @covers ::add_tabs
	 */
	public function test_tested_up_to_the_same_minor_version_is_reported_as_the_running_version(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		$this->put_readme();

		$result = $this->add_tabs( $this->get_sut( new Readme( tested: '6.5' ) ) );

		$this->assertSame( '6.5.3', $result['tested'] );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_tested_up_to_an_older_minor_version_is_not_changed(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		$this->put_readme();

		$result = $this->add_tabs( $this->get_sut( new Readme( tested: '6.4' ) ) );

		$this->assertSame( '6.4', $result['tested'] );
	}
}
