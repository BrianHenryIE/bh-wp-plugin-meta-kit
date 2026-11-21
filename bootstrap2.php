<?php
/**
 * Zero-config setup: work out which plugin the library is bundled in and start the library for it.
 *
 * Delete this file to configure the library yourself.
 *
 * @see bootstrap.php
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare(strict_types=1);

namespace BrianHenryIE\Plugin_Meta_Kit;

use Psr\Log\NullLogger;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( __NAMESPACE__ . '\\make_settings' ) ) {
	/**
	 * Infer the settings from where the library is installed, i.e. `wp-content/plugins/{slug}/vendor/...`.
	 *
	 * @param string $library_file_path A file inside the library, used to find the plugin directory it is under.
	 *
	 * @return ?Plugin_Meta_Kit_Settings Null when the file is not under an installed plugin's directory.
	 */
	function make_settings( string $library_file_path = __FILE__ ): ?Plugin_Meta_Kit_Settings {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once constant( 'ABSPATH' ) . 'wp-admin/includes/plugin.php';
		}

		$plugin_slug = explode( '/', plugin_basename( $library_file_path ) )[0];

		foreach ( get_plugins() as $plugin_basename => $plugin_file_headers ) {
			if ( ! str_starts_with( (string) $plugin_basename, $plugin_slug . '/' ) ) {
				continue;
			}

			// The only required plugin header is Name.
			$plugin_name = $plugin_file_headers['Name'] ?? null;

			return new Plugin_Meta_Kit_Settings(
				plugin_slug: $plugin_slug,
				plugin_basename: (string) $plugin_basename,
				plugin_name: is_string( $plugin_name ) ? $plugin_name : $plugin_slug,
				settings_url: get_settings_url( (string) $plugin_basename ),
				github_url: get_github_url( (string) $plugin_basename ),
			);
		}

		return null;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_plugin_file_header' ) ) {
	/**
	 * Read a header from the plugin's main file which is not one of WordPress's own, so `get_plugins()` does not
	 * return it.
	 *
	 * @param string $plugin_basename The plugin's main file relative to the plugins directory.
	 * @param string $header          The header's name, e.g. `Settings Link`.
	 *
	 * @return string The header's value, or empty when the file does not have it.
	 */
	function get_plugin_file_header( string $plugin_basename, string $header ): string {
		$headers = get_file_data(
			constant( 'WP_PLUGIN_DIR' ) . '/' . $plugin_basename,
			array( 'header' => $header ),
		);

		return trim( $headers['header'] );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_settings_url' ) ) {
	/**
	 * Read the plugin's settings screen from a `Settings Link` header in its main file.
	 *
	 * ```
	 * * Plugin Name:   My Plugin
	 * * Settings Link: options-general.php?page=my-plugin
	 * ```
	 *
	 * @param string $plugin_basename The plugin's main file relative to the plugins directory.
	 *
	 * @return string The full URL: the header as it is when it is one, otherwise relative to wp-admin. Empty when the plugin has no such header.
	 */
	function get_settings_url( string $plugin_basename ): string {
		$settings_link = get_plugin_file_header( $plugin_basename, 'Settings Link' );

		if ( '' === $settings_link ) {
			return '';
		}

		if ( 1 === preg_match( '#^https?://#i', $settings_link ) ) {
			return $settings_link;
		}

		return admin_url( ltrim( $settings_link, '/' ) );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_github_url' ) ) {
	/**
	 * Read the plugin's GitHub repository from a `GitHub Link` header in its main file.
	 *
	 * ```
	 * * Plugin Name: My Plugin
	 * * GitHub Link: https://github.com/example/my-plugin
	 * ```
	 *
	 * @param string $plugin_basename The plugin's main file relative to the plugins directory.
	 *
	 * @return string The full URL: the header as it is when it is one, otherwise as `owner/repository` on github.com. Empty when the plugin has no such header.
	 */
	function get_github_url( string $plugin_basename ): string {
		$github_link = get_plugin_file_header( $plugin_basename, 'GitHub Link' );

		if ( '' === $github_link ) {
			return '';
		}

		if ( 1 === preg_match( '#^https?://#i', $github_link ) ) {
			return $github_link;
		}

		return 'https://github.com/' . ltrim( $github_link, '/' );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\record_activation_of_plugin' ) ) {
	/**
	 * Record the time, when the plugin which was activated is the one the library is bundled in.
	 *
	 * @param string $activated_plugin_basename The plugin which was activated, relative to the plugins directory.
	 * @param string $library_file_path         A file inside the library, used to find the plugin directory it is under.
	 */
	function record_activation_of_plugin( string $activated_plugin_basename, string $library_file_path = __FILE__ ): void {
		$plugin_slug = explode( '/', plugin_basename( $library_file_path ) )[0];

		if ( ! str_starts_with( $activated_plugin_basename, $plugin_slug . '/' ) ) {
			return;
		}

		( new Plugin_Activation( $plugin_slug, new NullLogger() ) )->record_activation();
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\record_plugin_activation' ) ) {
	/**
	 * Record when the plugin the library is bundled in is activated, for the temporary activation admin notice.
	 *
	 * `activated_plugin` is used, not `register_activation_hook()`, which needs the plugin's main file: finding
	 * that means listing the plugins directory, which is too slow to do on every request.
	 *
	 * @hooked activated_plugin
	 * @see bootstrap2.php
	 *
	 * @param string $activated_plugin_basename The plugin which was activated, relative to the plugins directory.
	 */
	function record_plugin_activation( $activated_plugin_basename ): void {
		record_activation_of_plugin( (string) $activated_plugin_basename );
	}

	add_action( 'activated_plugin', __NAMESPACE__ . '\\record_plugin_activation' );
}

if ( ! function_exists( __NAMESPACE__ . '\\is_relevant_request' ) ) {
	/**
	 * Is this a request the library has a part in? Checked during `admin_init`, before any work is done.
	 *
	 * `admin_init` is what keeps the library out of front-end, REST, cron and WP-CLI requests, so there is no check
	 * for those here, nor one where this file is loaded: `activated_plugin`, above, has to be hooked on every kind
	 * of request, since a plugin can be activated from WP-CLI and the REST API.
	 *
	 * But `admin_init` also runs for every `admin-ajax.php` request, e.g. the Heartbeat every 15 to 60 seconds in
	 * each open tab, and for `admin-post.php`. The only one of those the library handles is the request which
	 * dismisses a notice.
	 */
	function is_relevant_request(): bool {
		if ( wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only compared with one action's name, to tell which request this is; the action itself checks a nonce.
			$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

			return 'wptrt_dismiss_notice' === $action;
		}

		global $pagenow;

		return 'admin-post.php' !== $pagenow;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\initialize_plugin_meta_kit' ) ) {
	/**
	 * Start the library for the plugin it is bundled in.
	 *
	 * To configure it yourself:
	 * `remove_action( 'admin_init', 'BrianHenryIE\\Plugin_Meta_Kit\\initialize_plugin_meta_kit' );`
	 *
	 * @hooked admin_init
	 * @see bootstrap2.php
	 */
	function initialize_plugin_meta_kit(): void {
		if ( ! is_relevant_request() ) {
			return;
		}

		$settings = make_settings();

		// The library is not under a plugin's directory, e.g. it is symlinked, so there is no row on plugins.php to add to.
		if ( null === $settings ) {
			return;
		}

		Plugin_Meta_Kit::initialize( $settings );
	}

	add_action( 'admin_init', __NAMESPACE__ . '\\initialize_plugin_meta_kit' );
}
