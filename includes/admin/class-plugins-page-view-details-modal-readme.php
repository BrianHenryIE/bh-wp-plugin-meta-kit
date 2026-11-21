<?php
/**
 * Reads the root plugin file and readme.txt for the "View Details" modal.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Model\Readme;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_File_Finder;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Readme_Parser;
use stdClass;

/**
 * Fills the plugin details modal with the plugin's headers and the contents of its readme.
 */
class Plugins_Page_View_Details_Modal_Readme {
	use Plugin_Information_Fields;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings           The meta kit settings.
	 * @param Readme_Parser            $readme_parser      Parses the plugin's readme.
	 * @param Plugin_File_Finder       $plugin_file_finder Finds the readme in the plugin's directory.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
		protected Readme_Parser $readme_parser,
		protected Plugin_File_Finder $plugin_file_finder,
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

		$plugin_header_fields = $this->get_plugin_header_fields();

		$readme_path = $this->get_readme_path();
		if ( null === $readme_path ) {
			return $this->set_fields( $result, array_merge( $fields, $plugin_header_fields ) );
		}

		// WordPress reads the plugin's requirements from its main file, so its headers win over the readme.
		$fields = array_merge( $fields, $this->get_readme_fields( $readme_path ), $plugin_header_fields );

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

		return $this->set_fields( $result, $fields );
	}

	/**
	 * The headers of the plugin's main file, as the plugin information fields core's modal prints.
	 *
	 * Empty values are left out, so they do not replace what an earlier filter or the readme set.
	 *
	 * @see install_plugin_information()
	 *
	 * @return array<string, string>
	 */
	protected function get_plugin_header_fields(): array {
		// With markup, `Author` is a link to `Author URI`, which is how the modal prints it.
		$plugin_headers = get_plugin_data( constant( 'WP_PLUGIN_DIR' ) . '/' . $this->settings->plugin_basename );

		$field_headers = array(
			'version'      => 'Version',
			'author'       => 'Author',
			'homepage'     => 'PluginURI',
			'requires'     => 'RequiresWP',
			'requires_php' => 'RequiresPHP',
		);

		$fields = array();

		foreach ( $field_headers as $field => $header ) {
			if ( '' !== $plugin_headers[ $header ] ) {
				$fields[ $field ] = $plugin_headers[ $header ];
			}
		}

		return $fields;
	}

	/**
	 * Find the plugin's readme, whose name plugins capitalise differently: `readme.txt`, `README.txt`, `Readme.txt`.
	 *
	 * @return ?string The readme's path, or null when the plugin has none.
	 */
	protected function get_readme_path(): ?string {
		return $this->plugin_file_finder->find( 'readme.txt' );
	}

	/**
	 * Parse the readme into the plugin information fields core's modal prints.
	 *
	 * Empty values are left out, so they do not replace what an earlier filter set.
	 *
	 * @param string $readme_path The readme's path.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_readme_fields( string $readme_path ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file; WP_Filesystem is for writing.
		$readme_contents = file_get_contents( $readme_path );

		if ( false === $readme_contents || '' === $readme_contents ) {
			return array();
		}

		$readme = $this->readme_parser->parse( $readme_contents );

		$fields = array(
			'name'              => $readme->name,
			'requires'          => $readme->requires,
			'tested'            => $readme->tested,
			'requires_php'      => $readme->requires_php,
			'donate_link'       => $readme->donate_link,
			'short_description' => $readme->short_description,
			'sections'          => $readme->sections,
			'upgrade_notice'    => $readme->upgrade_notices,
			'contributors'      => $this->get_contributors( $readme ),
		);

		return array_filter( $fields, fn ( $value ): bool => '' !== $value && array() !== $value );
	}

	/**
	 * The readme's contributors, in the shape core's modal prints: WordPress.org username to profile details.
	 *
	 * @see install_plugin_information()
	 *
	 * @param Readme $readme The parsed readme.
	 *
	 * @return array<string, array{display_name: string, profile: string, avatar: string}>
	 */
	protected function get_contributors( Readme $readme ): array {
		$contributors = array();

		if ( array() === $readme->contributors ) {
			return $contributors;
		}

		// The readme does not say who the contributors are beyond their usernames, so each gets the site's default avatar.
		$default_avatar_url = get_avatar_url( '', array( 'force_default' => true ) );

		foreach ( $readme->contributors as $username ) {
			$contributors[ $username ] = array(
				'display_name' => $username,
				'profile'      => 'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/',
				'avatar'       => is_string( $default_avatar_url ) ? $default_avatar_url : '',
			);
		}

		return $contributors;
	}
}
