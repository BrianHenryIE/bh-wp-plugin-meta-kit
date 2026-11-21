<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @covers \BrianHenryIE\Plugin_Meta_Kit\make_settings
 * @covers \BrianHenryIE\Plugin_Meta_Kit\get_settings_url
 */
class Bootstrap2_WPUnit_Test extends WPTestCase {
	protected const TEST_PLUGIN_SLUG = 'meta-kit-test-plugin';

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

	/**
	 * Write a plugin into the plugins directory, with the headers given.
	 *
	 * @param string $headers The lines of the plugin's header comment after `Plugin Name`.
	 */
	protected function install_test_plugin( string $headers = '' ): void {
		$plugin_directory = WP_PLUGIN_DIR . '/' . self::TEST_PLUGIN_SLUG;

		if ( ! is_dir( $plugin_directory ) ) {
			mkdir( $plugin_directory );
		}

		file_put_contents(
			$plugin_directory . '/' . self::TEST_PLUGIN_SLUG . '.php',
			"<?php\n/**\n * Plugin Name: Meta Kit Test Plugin\n{$headers} */\n",
		);

		// `get_plugins()` caches the plugins it found.
		wp_cache_delete( 'plugins', 'plugins' );
	}

	protected function tearDown(): void {
		$plugin_directory = WP_PLUGIN_DIR . '/' . self::TEST_PLUGIN_SLUG;

		if ( is_dir( $plugin_directory ) ) {
			unlink( $plugin_directory . '/' . self::TEST_PLUGIN_SLUG . '.php' );
			rmdir( $plugin_directory );
		}

		wp_cache_delete( 'plugins', 'plugins' );

		parent::tearDown();
	}

	protected function make_test_plugin_settings(): Plugin_Meta_Kit_Settings {
		$result = make_settings( WP_PLUGIN_DIR . '/' . self::TEST_PLUGIN_SLUG . '/vendor/brianhenryie/bh-wp-plugin-meta-kit/bootstrap2.php' );

		$this->assertInstanceOf( Plugin_Meta_Kit_Settings::class, $result );

		return $result;
	}

	/**
	 * `Settings Link` is not one of WordPress's plugin headers, so `get_plugins()` does not return it.
	 */
	public function test_settings_link_header_relative_to_wp_admin(): void {
		$this->install_test_plugin( " * Settings Link: admin.php?page=meta-kit-test-plugin\n" );

		$result = $this->make_test_plugin_settings();

		$this->assertSame(
			admin_url( 'admin.php?page=meta-kit-test-plugin' ),
			$result->settings_url,
		);
	}

	public function test_settings_link_header_with_a_leading_slash(): void {
		$this->install_test_plugin( " * Settings Link: /options-general.php?page=meta-kit-test-plugin\n" );

		$result = $this->make_test_plugin_settings();

		$this->assertSame(
			admin_url( 'options-general.php?page=meta-kit-test-plugin' ),
			$result->settings_url,
		);
	}

	public function test_settings_link_header_which_is_a_full_url(): void {
		$this->install_test_plugin( " * Settings Link: https://example.org/account/settings\n" );

		$result = $this->make_test_plugin_settings();

		$this->assertSame(
			'https://example.org/account/settings',
			$result->settings_url,
		);
	}

	public function test_settings_link_header_among_other_headers(): void {
		$this->install_test_plugin( " * Version: 1.2.3\n * Settings Link:   admin.php?page=meta-kit-test-plugin  \n * Author: Someone\n" );

		$result = $this->make_test_plugin_settings();

		$this->assertSame( 'Meta Kit Test Plugin', $result->plugin_name );
		$this->assertSame(
			admin_url( 'admin.php?page=meta-kit-test-plugin' ),
			$result->settings_url,
		);
	}

	public function test_plugin_without_a_settings_link_header_has_no_settings_url(): void {
		$this->install_test_plugin();

		$this->assertSame( '', $this->make_test_plugin_settings()->settings_url );
	}
}
