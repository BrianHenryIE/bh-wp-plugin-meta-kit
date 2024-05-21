<?php
/**
 * Reads the root plugin file and readme.txt for the "View Details" modal.
 */

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings_Interface;
use Parsedown as MarkdownParser;
use WordPressdotorg\Plugin_Directory\Readme\Parser as ReadmeParser;

class Plugins_Page_View_Details_Modal_Readme {

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings_Interface $settings The meta kit settings.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings_Interface $settings
	) {
	}

	/**
	 * @hooked plugins_api
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args Plugin API arguments.
	 */
	public function add_tabs( $res, $action, $args ) {

		if ( empty( $args ) || ! isset( $args->slug ) || $this->settings->get_plugin_slug() !== $args->slug ) {
			return $res;
		}

		if ( false === $res ) {
			$res = new \stdClass();
		}

		$minimum = array(
			'slug'     => $this->settings->get_plugin_slug(),
			'name'     => $this->settings->get_plugin_name(),
			'sections' => array(),
		);

		foreach ( $minimum as $key => $value ) {
			if ( ! isset( $res->$key ) ) {
				$res->$key = $value;
			}
		}

		$plugin_headers = get_plugin_data( constant('WP_PLUGIN_DIR') . '/' . $this->settings->get_plugin_basename() );
		// TODO it is reading the plugin file but looks like the fields need to be mapped
		foreach( $plugin_headers as $key => $value ) {
			$res->$key = $value;
		}

		$readme_path = constant( 'WP_PLUGIN_DIR' ) . '/' . $this->settings->get_plugin_slug() . '/readme.txt';
		if ( ! is_readable( $readme_path ) ) {
			return $res;
		}

		$root_plugin_file_headers = get_plugins()[$this->settings->get_plugin_basename()];

		foreach( $root_plugin_file_headers as $key => $value ) {
			$res->$key = $value;
		}

		$readme = $this->get_readme( $readme_path );

		foreach( $readme as $key => $value ) {
			$res->$key = $value;
		}



		// Lie and say the plugin has been tested on patch versions of WordPress.
		// Otherwise a banner is displayed: "Warning: This plugin has not been tested with your current version of WordPress."
		// plugin-install.php:817
		// TODO: open a trac ticket. The behaviour on WordPress.org repo pages is to only compare minor, not patch.
		// TODO: move this to its own class and only run it while the plugin modal is displayed. Otherwise it will
		// interfere with plugin updates.
		// TODO: Conversely, filter the bloginfo result.
		if( isset( $res->tested )
		    && preg_match( '#^(\d+\.\d+).*#', get_bloginfo( 'version' ), $wp_minor_version )
		    && preg_match( '#^(\d+\.\d+).*#',  $res->tested, $plugin_minor_version )
		    && $wp_minor_version[1] === $plugin_minor_version[1]
		) {
			$res->tested = get_bloginfo( 'version' );
		}


		$parsedown = new MarkdownParser();

		foreach( $res->sections as $name => $section ) {
			$res->sections[$name] = $parsedown->text( $section );
		}

		return $res;
	}

	protected function get_readme( $readme_path ) {

		$readme = new ReadmeParser( $readme_path );
		return $readme;
	}
}