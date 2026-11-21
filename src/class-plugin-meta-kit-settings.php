<?php
/**
 * What the library needs to know about the plugin it is bundled in.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Model\Dependency;

/**
 * Identifies the plugin whose row on plugins.php is being added to.
 */
readonly class Plugin_Meta_Kit_Settings {
	/**
	 * Constructor.
	 *
	 * @param string       $plugin_slug     The plugin's directory name, e.g. `my-plugin`.
	 * @param string       $plugin_basename The plugin's main file relative to the plugins directory, e.g. `my-plugin/my-plugin.php`.
	 * @param string       $plugin_name     The `Plugin Name` header of the plugin's main file.
	 * @param Dependency[] $dependencies    What the plugin needs from the site; an admin notice is shown for each which is missing.
	 * @param string       $settings_url    The full URL of the plugin's settings screen, linked from its row on plugins.php, or empty when it has none.
	 */
	public function __construct(
		public string $plugin_slug,
		public string $plugin_basename,
		public string $plugin_name,
		public array $dependencies = array(),
		public string $settings_url = '',
	) {
	}
}
