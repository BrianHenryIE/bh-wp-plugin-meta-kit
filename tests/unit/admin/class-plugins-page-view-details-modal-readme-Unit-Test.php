<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use Closure;
use stdClass;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme
 */
class Plugins_Page_View_Details_Modal_Readme_Unit_Test extends Unit_Testcase {
	/** A temporary directory standing in for `wp-content/plugins`. */
	protected string $plugins_directory;

	/** The file the readme parser was asked to read, if it was. */
	protected ?string $parsed_readme_path = null;

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
	 * The system under test, with the readme parser (which needs WordPress) replaced by its parsed result.
	 *
	 * @param array<string, mixed> $parsed_readme The public properties the parser would have after parsing.
	 */
	protected function get_sut( array $parsed_readme = array() ): Plugins_Page_View_Details_Modal_Readme {
		$record_parsed_readme_path = function ( string $readme_path ): void {
			$this->parsed_readme_path = $readme_path;
		};

		return new class( $this->get_settings(), $parsed_readme, $record_parsed_readme_path ) extends Plugins_Page_View_Details_Modal_Readme {
			/**
			 * @param Plugin_Meta_Kit_Settings $settings                  The plugin.
			 * @param array<string, mixed>     $parsed_readme             The public properties the parser would have after parsing.
			 * @param Closure(string): void    $record_parsed_readme_path Told which file the parser was asked to read.
			 */
			public function __construct(
				Plugin_Meta_Kit_Settings $settings,
				protected array $parsed_readme,
				protected Closure $record_parsed_readme_path,
			) {
				parent::__construct( $settings );
			}

			protected function get_readme( string $readme_path ): array {
				( $this->record_parsed_readme_path )( $readme_path );
				return $this->parsed_readme;
			}
		};
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
		$this->assertNull( $this->parsed_readme_path );
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
	 */
	public function test_readme_fields_are_added(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		touch( $this->plugins_directory . '/my-plugin/readme.txt' );

		$sut = $this->get_sut(
			array(
				'requires'     => '6.2',
				'requires_php' => '8.2',
				'tested'       => '6.4',
				'sections'     => array( 'description' => 'What it does.' ),
			),
		);

		$result = $this->add_tabs( $sut );

		$this->assertSame(
			$this->plugins_directory . '/my-plugin/readme.txt',
			$this->parsed_readme_path,
		);
		$this->assertSame( '6.2', $result['requires'] );
		$this->assertSame( '8.2', $result['requires_php'] );
		$this->assertSame(
			array( 'description' => '<p>What it does.</p>' ),
			$result['sections'],
		);
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_readme_sections_markdown_is_converted_to_html(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		touch( $this->plugins_directory . '/my-plugin/readme.txt' );

		$sut = $this->get_sut(
			array(
				'sections' => array(
					'description' => 'It is **bold**.',
					'changelog'   => "* First\n* Second",
				),
			),
		);

		$result = $this->add_tabs( $sut );

		$this->assertSame(
			array(
				'description' => '<p>It is <strong>bold</strong>.</p>',
				'changelog'   => "<ul>\n<li>First</li>\n<li>Second</li>\n</ul>",
			),
			$result['sections'],
		);
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
		touch( $this->plugins_directory . '/my-plugin/' . $readme_filename );

		$sut = $this->get_sut( array( 'requires' => '6.2' ) );

		$result = $this->add_tabs( $sut );

		$this->assertSame(
			$this->plugins_directory . '/my-plugin/' . $readme_filename,
			$this->parsed_readme_path,
		);
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

		$sut = $this->get_sut( array( 'requires' => '6.2' ) );

		$result = $this->add_tabs( $sut );

		$this->assertNull( $this->parsed_readme_path );
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
		touch( $this->plugins_directory . '/my-plugin/readme.txt' );

		$result = $this->add_tabs( $this->get_sut( array( 'tested' => '6.5' ) ) );

		$this->assertSame( '6.5.3', $result['tested'] );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_tested_up_to_an_older_minor_version_is_not_changed(): void {
		$this->mock_plugin_headers();
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( '6.5.3' );
		touch( $this->plugins_directory . '/my-plugin/readme.txt' );

		$result = $this->add_tabs( $this->get_sut( array( 'tested' => '6.4' ) ) );

		$this->assertSame( '6.4', $result['tested'] );
	}
}
