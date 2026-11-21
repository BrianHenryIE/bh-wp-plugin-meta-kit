<?php
/**
 * Setting fields on the plugin information which the `plugins_api` filters build.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

/**
 * The plugin information is whatever class an earlier filter made it, so its properties are set by name.
 */
trait Plugin_Information_Fields {
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
}
