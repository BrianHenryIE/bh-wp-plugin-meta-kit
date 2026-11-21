<?php
/**
 * Corrects plugin paths and URLs for the wp-env directory mappings.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

namespace BrianHenryIE\Plugin_Meta_Kit\Development_Plugin;

/**
 * The library is mapped into the container at `wp-content/uploads/development-plugin`, outside the plugins
 * directory, so WordPress's path and URL helpers need telling where it is.
 */
class Mappings {
	/**
	 * Add the hooks which correct the paths and URLs.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'wp_plugin_paths' ) );
		add_filter( 'plugins_url', array( $this, 'plugins_url_fix' ), 10, 3 );
	}

	/**
	 * Tell WordPress the development plugin's real path, so `plugin_basename()` works for files under it.
	 *
	 * @hooked init
	 * @see self::register_hooks()
	 */
	public function wp_plugin_paths(): void {
		/**
		 * Fix for mapped directories. I.e. vendor is not under `wp-content/plugins/development-plugin`.
		 *
		 * @see plugin_basename()
		 */
		global $wp_plugin_paths;
		$plugin_path = '/var/www/html/wp-content/uploads/development-plugin/';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Adding an entry to the global is how a mapped plugin directory is registered.
		$wp_plugin_paths[ WP_PLUGIN_DIR . '/development-plugin/' ] = $plugin_path;
	}

	/**
	 * Partial fix for symlinks.
	 *
	 * @hooked plugins_url
	 * @see self::register_hooks()
	 *
	 * @param string $url     The complete URL to the plugins directory, including the path and plugin.
	 * @param string $_path   The path which was appended to the plugins URL.
	 * @param string $_plugin The plugin file the URL is relative to.
	 *
	 * @return string
	 */
	public function plugins_url_fix( $url, $_path, $_plugin ) {
		$url = str_replace( 'wp-content/plugins/var/www/html/', '', $url );
		$url = str_replace( 'plugins/development-plugin/vendor', 'uploads/development-plugin/vendor', $url );
		$url = str_replace( 'plugins/development-plugin/includes', 'uploads/development-plugin/includes', $url );
		return $url;
	}
}
