<?php
/**
 * The bare minimum to get the "View details" link to appear on the plugins page and work.
 * Without additional filters, there will be no content displayed, but additional content needs these
 * steps to be taken first.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use stdClass;

/**
 * Tells WordPress the plugin has plugin information, which is what makes it print the "View details" link, and
 * answers the request for that information with the plugin's slug and name.
 */
class Plugins_Page_View_Details_Modal {
	use Plugin_Information_Fields;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
	) {
	}

	/**
	 * Add the plugin to the plugins which were checked for updates and have none.
	 *
	 * @hooked site_transient_update_plugins
	 * @see Plugin_Meta_Kit::view_details_modal()
	 * @see get_site_transient()
	 *
	 * @param mixed  $update_plugins The transient: an object once WordPress has checked for updates, false before.
	 * @param string $transient      Transient name.
	 *
	 * @return mixed The transient, with the plugin added when it is an object.
	 */
	public function update_plugins_transient( $update_plugins, string $transient ) {
		// TODO: This seemed to happed once on a brand new site.
		// Presumably because the transient was not set yet.
		if ( ! $update_plugins instanceof stdClass ) {
			return $update_plugins;
		}

		// This is needed to get the `View details` link to appear on `plugins.php`.

		$no_update = $update_plugins->no_update ?? array();
		$no_update = is_array( $no_update ) ? $no_update : array();

		// By default, add to `no_update`. If another mechanism has an update available, let that move and override this.
		$no_update[ $this->settings->plugin_basename ] = (object) array(
			'slug' => $this->settings->plugin_slug,
		);

		$update_plugins->no_update = $no_update;

		return $update_plugins;
	}

	/**
	 * Answer the request for the plugin's information with its slug and name, where an earlier filter has not.
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

		$minimum = array(
			'slug'     => $this->settings->plugin_slug,
			'name'     => $this->settings->plugin_name,
			'sections' => array(),
		);

		// A field an earlier filter set to null is filled in too, as `isset()` would have it.
		$existing_fields = array_filter( get_object_vars( $result ), fn ( $value ): bool => null !== $value );

		return $this->set_fields( $result, array_diff_key( $minimum, $existing_fields ) );
	}
}
