<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @covers \BrianHenryIE\Plugin_Meta_Kit\make_settings
 * @covers \BrianHenryIE\Plugin_Meta_Kit\get_settings_url
 * @covers \BrianHenryIE\Plugin_Meta_Kit\get_github_url
 * @covers \BrianHenryIE\Plugin_Meta_Kit\get_plugin_file_header
 * @covers \BrianHenryIE\Plugin_Meta_Kit\record_activation_of_plugin
 * @covers \BrianHenryIE\Plugin_Meta_Kit\is_relevant_request
 * @covers \BrianHenryIE\Plugin_Meta_Kit\initialize_plugin_meta_kit
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

		remove_all_filters( 'wp_doing_ajax' );
		unset( $_REQUEST['action'] );
		$GLOBALS['pagenow'] = 'index.php';

		parent::tearDown();
	}

	/**
	 * Set up the request as WordPress would see it.
	 *
	 * @param string  $pagenow     The wp-admin file requested.
	 * @param ?string $ajax_action The AJAX action, when it is an `admin-ajax.php` request.
	 */
	protected function mock_request( string $pagenow, ?string $ajax_action = null ): void {
		$GLOBALS['pagenow'] = $pagenow;

		if ( null !== $ajax_action ) {
			add_filter( 'wp_doing_ajax', '__return_true' );
			$_REQUEST['action'] = $ajax_action;
		}
	}

	/**
	 * @return array<string, array{0: string, 1: ?string, 2: bool}>
	 */
	public static function requests(): array {
		return array(
			'the dashboard'                        => array( 'index.php', null, true ),
			'plugins.php'                          => array( 'plugins.php', null, true ),
			'the plugin details modal'             => array( 'plugin-install.php', null, true ),
			'dismissing a notice'                  => array( 'admin-ajax.php', 'wptrt_dismiss_notice', true ),
			'the Heartbeat'                        => array( 'admin-ajax.php', 'heartbeat', false ),
			'another plugin\'s AJAX action'        => array( 'admin-ajax.php', 'another_plugin_action', false ),
			'an AJAX request with an empty action' => array( 'admin-ajax.php', '', false ),
			'a form posted to admin-post.php'      => array( 'admin-post.php', null, false ),
		);
	}

	/**
	 * `admin_init` runs for every one of these.
	 *
	 * @dataProvider requests
	 *
	 * @param string  $pagenow     The wp-admin file requested.
	 * @param ?string $ajax_action The AJAX action, when it is an `admin-ajax.php` request.
	 * @param bool    $expected    Whether the library has a part in the request.
	 */
	public function test_is_relevant_request( string $pagenow, ?string $ajax_action, bool $expected ): void {
		$this->mock_request( $pagenow, $ajax_action );

		$this->assertSame( $expected, is_relevant_request() );
	}

	/**
	 * The plugins directory is not read for a request the library has no part in.
	 */
	public function test_library_is_not_initialized_for_an_irrelevant_request(): void {
		$this->mock_request( 'admin-ajax.php', 'heartbeat' );

		$this->assertFalse( has_filter( 'plugins_api' ) );

		initialize_plugin_meta_kit();

		// Initializing adds the library's `plugins_api` filters, among others.
		$this->assertFalse( has_filter( 'plugins_api' ) );
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

	public function test_github_link_header_which_is_a_full_url(): void {
		$this->install_test_plugin( " * GitHub Link: https://github.com/example/meta-kit-test-plugin\n" );

		$this->assertSame(
			'https://github.com/example/meta-kit-test-plugin',
			$this->make_test_plugin_settings()->github_url,
		);
	}

	public function test_github_link_header_which_is_owner_and_repository(): void {
		$this->install_test_plugin( " * GitHub Link: example/meta-kit-test-plugin\n" );

		$this->assertSame(
			'https://github.com/example/meta-kit-test-plugin',
			$this->make_test_plugin_settings()->github_url,
		);
	}

	public function test_github_link_and_settings_link_headers_together(): void {
		$this->install_test_plugin( " * Settings Link: admin.php?page=meta-kit-test-plugin\n * GitHub Link:   https://github.com/example/meta-kit-test-plugin\n" );

		$result = $this->make_test_plugin_settings();

		$this->assertSame(
			admin_url( 'admin.php?page=meta-kit-test-plugin' ),
			$result->settings_url,
		);
		$this->assertSame(
			'https://github.com/example/meta-kit-test-plugin',
			$result->github_url,
		);
	}

	public function test_plugin_without_a_github_link_header_has_no_github_url(): void {
		$this->install_test_plugin();

		$this->assertSame( '', $this->make_test_plugin_settings()->github_url );
	}

	public function test_activation_of_the_plugin_the_library_is_in_is_recorded(): void {
		$this->install_test_plugin();
		delete_option( 'meta_kit_test_plugin_bhpmk_last_activated' );

		$before = time();

		record_activation_of_plugin(
			self::TEST_PLUGIN_SLUG . '/' . self::TEST_PLUGIN_SLUG . '.php',
			WP_PLUGIN_DIR . '/' . self::TEST_PLUGIN_SLUG . '/vendor/brianhenryie/bh-wp-plugin-meta-kit/bootstrap2.php',
		);

		$recorded = get_option( 'meta_kit_test_plugin_bhpmk_last_activated' );

		$this->assertIsNumeric( $recorded );
		$this->assertGreaterThanOrEqual( $before, (int) $recorded );
		$this->assertLessThanOrEqual( time(), (int) $recorded );

		delete_option( 'meta_kit_test_plugin_bhpmk_last_activated' );
	}

	/**
	 * `activated_plugin` runs whichever plugin was activated.
	 */
	public function test_activation_of_another_plugin_is_not_recorded(): void {
		$this->install_test_plugin();
		delete_option( 'meta_kit_test_plugin_bhpmk_last_activated' );

		record_activation_of_plugin(
			'akismet/akismet.php',
			WP_PLUGIN_DIR . '/' . self::TEST_PLUGIN_SLUG . '/vendor/brianhenryie/bh-wp-plugin-meta-kit/bootstrap2.php',
		);

		$this->assertFalse( get_option( 'meta_kit_test_plugin_bhpmk_last_activated' ) );
	}

	/**
	 * A plugin whose directory name begins with this plugin's is another plugin.
	 */
	public function test_activation_of_a_plugin_with_a_similar_name_is_not_recorded(): void {
		$this->install_test_plugin();
		delete_option( 'meta_kit_test_plugin_bhpmk_last_activated' );

		record_activation_of_plugin(
			self::TEST_PLUGIN_SLUG . '-pro/' . self::TEST_PLUGIN_SLUG . '-pro.php',
			WP_PLUGIN_DIR . '/' . self::TEST_PLUGIN_SLUG . '/vendor/brianhenryie/bh-wp-plugin-meta-kit/bootstrap2.php',
		);

		$this->assertFalse( get_option( 'meta_kit_test_plugin_bhpmk_last_activated' ) );
	}
}
