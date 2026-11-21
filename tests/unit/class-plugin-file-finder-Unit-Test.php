<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Plugin_File_Finder
 */
class Plugin_File_Finder_Unit_Test extends Unit_Testcase {
	/** A temporary directory standing in for `wp-content/plugins`. */
	protected string $plugins_directory;

	protected function setUp(): void {
		parent::setUp();

		$this->plugins_directory = sys_get_temp_dir() . '/' . uniqid( 'bh-wp-plugin-meta-kit-' );
		mkdir( $this->plugins_directory . '/my-plugin/readme.txt', 0777, true );

		$plugins_directory = $this->plugins_directory;
		\Patchwork\redefine(
			'constant',
			fn ( string $name ) => 'WP_PLUGIN_DIR' === $name ? $plugins_directory : \Patchwork\relay( array( $name ) ),
		);
	}

	protected function tearDown(): void {
		foreach ( glob( $this->plugins_directory . '/my-plugin/*' ) ?: array() as $path ) {
			is_dir( $path ) ? rmdir( $path ) : unlink( $path );
		}
		rmdir( $this->plugins_directory . '/my-plugin' );
		rmdir( $this->plugins_directory );

		parent::tearDown();
	}

	protected function get_sut( string $plugin_slug = 'my-plugin' ): Plugin_File_Finder {
		return new Plugin_File_Finder(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: $plugin_slug,
				plugin_basename: $plugin_slug . '/' . $plugin_slug . '.php',
				plugin_name: 'My Plugin',
			),
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function filenames(): array {
		return array(
			'same capitalisation'       => array( 'CHANGELOG.md', 'CHANGELOG.md' ),
			'file is lowercase'         => array( 'changelog.md', 'CHANGELOG.md' ),
			'file is capitalised'       => array( 'Changelog.md', 'CHANGELOG.md' ),
			'file extension uppercase'  => array( 'CHANGELOG.MD', 'CHANGELOG.md' ),
			'looking for the lowercase' => array( 'CHANGELOG.md', 'changelog.md' ),
		);
	}

	/**
	 * @covers ::find
	 *
	 * @dataProvider filenames
	 *
	 * @param string $filename_on_disk   The name the plugin gave the file.
	 * @param string $filename_asked_for The name it is looked for by.
	 */
	public function test_file_is_found_whatever_its_capitalisation( string $filename_on_disk, string $filename_asked_for ): void {
		touch( $this->plugins_directory . '/my-plugin/' . $filename_on_disk );

		$result = $this->get_sut()->find( $filename_asked_for );

		$this->assertSame(
			$this->plugins_directory . '/my-plugin/' . $filename_on_disk,
			$result,
		);
	}

	/**
	 * @covers ::find
	 */
	public function test_missing_file_is_null(): void {
		touch( $this->plugins_directory . '/my-plugin/CHANGELOG.md.bak' );

		$this->assertNull( $this->get_sut()->find( 'CHANGELOG.md' ) );
	}

	/**
	 * `setUp()` makes a directory named `readme.txt`.
	 *
	 * @covers ::find
	 */
	public function test_directory_with_the_files_name_is_not_found(): void {
		$this->assertNull( $this->get_sut()->find( 'readme.txt' ) );
	}

	/**
	 * @covers ::find
	 */
	public function test_missing_plugin_directory_is_null(): void {
		$this->assertNull( $this->get_sut( 'not-installed' )->find( 'readme.txt' ) );
	}
}
