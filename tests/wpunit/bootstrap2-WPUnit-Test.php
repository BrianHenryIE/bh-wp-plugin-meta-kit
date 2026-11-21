<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @covers \BrianHenryIE\Plugin_Meta_Kit\make_settings
 */
class Bootstrap2_WPUnit_Test extends WPTestCase {
	protected function setUp(): void {
		parent::setUp();

		// Composer's autoloader includes the file before WordPress is loaded, when it returns early.
		if ( ! function_exists( __NAMESPACE__ . '\\make_settings' ) ) {
			require codecept_root_dir( 'bootstrap2.php' );
		}
	}

	public function test_make_settings_for_library_bundled_in_a_plugin(): void {
		$library_file_path = WP_PLUGIN_DIR . '/akismet/vendor/brianhenryie/bh-wp-plugin-meta-kit/bootstrap2.php';

		$result = make_settings( $library_file_path );

		$this->assertInstanceOf( Plugin_Meta_Kit_Settings::class, $result );
		$this->assertSame( 'akismet', $result->plugin_slug );
		$this->assertSame( 'akismet/akismet.php', $result->plugin_basename );
		$this->assertStringContainsString( 'Akismet', $result->plugin_name );
	}

	public function test_make_settings_is_null_outside_an_installed_plugin(): void {
		$library_file_path = WP_PLUGIN_DIR . '/not-installed/vendor/brianhenryie/bh-wp-plugin-meta-kit/bootstrap2.php';

		$this->assertNull( make_settings( $library_file_path ) );
	}
}
