<?php
/**
 * The bare minimum to get the "View details" link to appear on the plugins page and work.
 * Without additional filters, there will be no content displayed, but additional content needs these
 * steps to be taken first.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;

class Plugins_Page_View_Details_Modal {

	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings
	) {
	}

	/**
	 * @hooked site_transient_update_plugins
	 *
	 * @param false|object $update_plugins
	 * @param string $transient Transient name.
	 *
	 * @return false|object
	 * @see get_site_transient()
	 *
	 */
	public function update_plugins_transient( $update_plugins, string $transient ) {

		// TODO: This seemed to happed once on a brand new site.
		// Presumably because the transient was not set yet.
		if(is_bool($update_plugins)){
			return $update_plugins;
		}

		// This is needed to get the `View details` link to appear on `plugins.php`.

		// By default, add to `no_update`. If another mechanism has an update available, let that move and override this.
		$update_plugins->no_update[ $this->settings->plugin_basename ] = (object) array(
			'slug' => $this->settings->plugin_slug,
		);

		return $update_plugins;
	}


	/**
	 * @hooked plugins_api
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string $action The type of information being requested from the Plugin Installation API.
	 * @param object $args Plugin API arguments.
	 */
	public function add_tabs( $res, $action, $args ) {

		if ( empty( $args ) || ! isset( $args->slug ) || $this->settings->plugin_slug !== $args->slug ) {
			return $res;
		}

		if ( false === $res ) {
			$res = new \stdClass();
		}

		$minimum = array(
			'slug'     => $this->settings->plugin_slug,
			'name'     => $this->settings->plugin_name,
			'sections' => array(),
		);

		foreach ( $minimum as $key => $value ) {
			if ( ! isset( $res->$key ) ) {
				$res->$key = $value;
			}
		}

		return $res;
	}
}
