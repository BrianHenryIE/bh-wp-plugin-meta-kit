<?php
/**
 * Additions to the plugin's row on plugins.php.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;

/**
 * This class adds a `Settings` link and a `GitHub` link on the plugins.php page.
 */
class Plugins_Page_Table_Row {
	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin settings.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
	) {
	}

	/**
	 * Add a link to the plugin's settings screen, before the existing links, when the plugin has one.
	 *
	 * @hooked plugin_action_links_{basename}
	 * @see Plugin_Meta_Kit::plugins_page_table_row() plugin_action_links_{$plugin_basename}
	 *
	 * @param array<int|string, string>   $links_array The existing plugin links (usually "Deactivate"). May or may not be indexed with a string.
	 * @param ?string                     $plugin_file The plugin basename.
	 * @param ?array<string, string|bool> $plugin_data The parsed plugin header data.
	 * @param ?string                     $context     'all'|'active'|'inactive'...
	 *
	 * @return array<int|string, string> The links to display below the plugin name on plugins.php.
	 */
	public function action_links( array $links_array, ?string $plugin_file, ?array $plugin_data, ?string $context ): array {
		if ( '' === $this->settings->settings_url ) {
			return $links_array;
		}

		$settings_link = '<a href="' . esc_url( $this->settings->settings_url ) . '">' . esc_html__( 'Settings', 'bh-wp-plugin-meta-kit' ) . '</a>';

		// `+` rather than `array_unshift()`, which would renumber the links other plugins added without a key.
		return array( 'settings' => $settings_link ) + $links_array;
	}

	/**
	 * Add a link to the plugin's GitHub repository after its version and author, when the plugin has one.
	 *
	 * @hooked plugin_row_meta
	 * @see Plugin_Meta_Kit::plugins_page_table_row()
	 * @see https://rudrastyh.com/wordpress/plugin_action_links-plugin_row_meta.html
	 *
	 * @param array<int|string, string>  $plugin_meta      The meta information/links displayed by the plugin description.
	 * @param string                     $plugin_file_name The plugin filename to match when filtering.
	 * @param array<string, string|bool> $plugin_data      Associative array including PluginURI, slug, Author, Version.
	 * @param string                     $status           The plugin status, e.g. 'Inactive'.
	 *
	 * @return array<int|string, string> The filtered $plugin_meta.
	 */
	public function add_github_row_meta( array $plugin_meta, string $plugin_file_name, array $plugin_data, string $status ): array {
		if ( '' === $this->settings->github_url ) {
			return $plugin_meta;
		}

		// The filter runs for every plugin's row.
		if ( $this->settings->plugin_basename !== $plugin_file_name ) {
			return $plugin_meta;
		}

		$plugin_meta[] = '<a target="_blank" href="' . esc_url( $this->settings->github_url ) . '">' . esc_html__( 'View on GitHub', 'bh-wp-plugin-meta-kit' ) . '</a>';

		return $plugin_meta;
	}
}
