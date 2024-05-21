<?php
/**
 * Adds a tab to the "View Details" modal for plugins, displaying the changelog.
 *
 * Reads the changelog from the CHANGELOG.md file.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings_Interface;

class Plugins_Page_View_Details_Modal_Tab_Changelog {

	public function __construct(
		protected Plugin_Meta_Kit_Settings_Interface $settings,
	) {
	}

	/**
	 * @hooked plugins_api
	 *
	 * $api->sections as $section_name => $content
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args Plugin API arguments.
	 */
	public function add_changelog_tab( $res, $action, $args ) {

		if ( empty( $args ) || ! isset( $args->slug ) || $this->settings->get_plugin_slug() !== $args->slug ) {
			return $res;
		}

		if ( ! is_readable( constant( WP_PLUGIN_DIR ) . '/' . $this->settings->get_plugin_slug() . '/CHANGELOG.md' ) ) {
			return $res;
		}

		if ( false === $res ) {
			$res       = new \stdClass();
			$res->slug = 'test-plugin';
		}

		if ( ! isset( $res->sections ) ) {
			$res->sections = array();
		}

		$res->sections['changelog'] = $this->get_changelog_tab_html();

		return $res;
	}

	protected function get_changelog_tab_html(): string {
		return 'TODO: parse markdown and display changelog here.';
	}
}
