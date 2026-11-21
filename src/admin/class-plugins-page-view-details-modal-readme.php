<?php
/**
 * Reads the root plugin file and readme.txt for the "View Details" modal.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use Parsedown;
use stdClass;
use WordPressdotorg\Plugin_Directory\Readme\Parser as ReadmeParser;

/**
 * Fills the plugin details modal with the plugin's headers and the contents of its readme.
 */
class Plugins_Page_View_Details_Modal_Readme {
	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The meta kit settings.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
	) {
	}

	/**
	 * Add the plugin's headers and its readme's fields and sections to the plugin information.
	 *
	 * @hooked plugins_api
	 * @see Plugin_Meta_Kit::view_details_modal()
	 *
	 * @param false|object|array<mixed> $result The result object or array. Default false.
	 * @param string                    $action The type of information being requested from the Plugin Installation API.
	 * @param object                    $args   Plugin API arguments.
	 *
	 * @return false|object|array<mixed>
	 */
	public function add_tabs( $result, $action, $args ) {
		$requested_slug = get_object_vars( $args )['slug'] ?? null;

		if ( $requested_slug !== $this->settings->plugin_slug ) {
			return $result;
		}

		if ( ! is_object( $result ) ) {
			$result = is_array( $result ) ? (object) $result : new stdClass();
		}

		$existing_fields = get_object_vars( $result );

		$minimum = array(
			'slug'     => $this->settings->plugin_slug,
			'name'     => $this->settings->plugin_name,
			'sections' => array(),
		);

		$fields = array();

		foreach ( $minimum as $key => $value ) {
			if ( ! isset( $existing_fields[ $key ] ) ) {
				$fields[ $key ] = $value;
			}
		}

		$plugin_headers = get_plugin_data( constant( 'WP_PLUGIN_DIR' ) . '/' . $this->settings->plugin_basename );
		// TODO it is reading the plugin file but looks like the fields need to be mapped.
		$fields = array_merge( $fields, $plugin_headers );

		$readme_path = $this->get_readme_path();
		if ( null === $readme_path ) {
			return $this->set_fields( $result, $fields );
		}

		$root_plugin_file_headers = get_plugins()[ $this->settings->plugin_basename ] ?? array();

		$fields = array_merge( $fields, $root_plugin_file_headers, $this->get_readme( $readme_path ) );

		// Lie and say the plugin has been tested on patch versions of WordPress.
		// Otherwise a banner is displayed: "Warning: This plugin has not been tested with your current version of WordPress."
		// plugin-install.php:817
		// TODO: open a trac ticket. The behaviour on WordPress.org repo pages is to only compare minor, not patch.
		// TODO: move this to its own class and only run it while the plugin modal is displayed. Otherwise it will
		// interfere with plugin updates.
		// TODO: Conversely, filter the bloginfo result.
		$tested            = $fields['tested'] ?? $existing_fields['tested'] ?? null;
		$wordpress_version = get_bloginfo( 'version' );
		if ( is_string( $tested )
			&& preg_match( '#^(\d+\.\d+).*#', $wordpress_version, $wp_minor_version )
			&& preg_match( '#^(\d+\.\d+).*#', $tested, $plugin_minor_version )
			&& $wp_minor_version[1] === $plugin_minor_version[1]
		) {
			$fields['tested'] = $wordpress_version;
		}

		$sections = $fields['sections'] ?? $existing_fields['sections'] ?? array();

		if ( is_array( $sections ) ) {
			$parsedown = new Parsedown();

			foreach ( $sections as $name => $section ) {
				if ( is_string( $section ) ) {
					$sections[ $name ] = $parsedown->text( $section );
				}
			}

			$fields['sections'] = $sections;
		}

		return $this->set_fields( $result, $fields );
	}

	/**
	 * Set each field as a property of the plugin information.
	 *
	 * @param object       $result The plugin information being built.
	 * @param array<mixed> $fields The properties to set, by name.
	 */
	protected function set_fields( object $result, array $fields ): object {
		foreach ( $fields as $key => $value ) {
			$result->$key = $value;
		}

		return $result;
	}

	/**
	 * Find the plugin's readme, whose name plugins capitalise differently: `readme.txt`, `README.txt`, `Readme.txt`.
	 *
	 * The directory is listed, rather than each spelling tried, because filesystems differ on case-sensitivity.
	 *
	 * @return ?string The readme's path, or null when the plugin has none.
	 */
	protected function get_readme_path(): ?string {
		$plugin_directory = constant( 'WP_PLUGIN_DIR' ) . '/' . $this->settings->plugin_slug;

		if ( ! is_dir( $plugin_directory ) ) {
			return null;
		}

		foreach ( scandir( $plugin_directory ) ?: array() as $filename ) {
			$file_path = $plugin_directory . '/' . $filename;

			if ( 0 === strcasecmp( 'readme.txt', $filename ) && is_file( $file_path ) && is_readable( $file_path ) ) {
				return $file_path;
			}
		}

		return null;
	}

	/**
	 * Parse the readme.
	 *
	 * @param string $readme_path The readme's path.
	 *
	 * @return array<mixed> The parser's public properties, by name: `requires`, `tested`, `sections`, etc.
	 */
	protected function get_readme( string $readme_path ): array {
		return get_object_vars( new ReadmeParser( $readme_path ) );
	}
}
