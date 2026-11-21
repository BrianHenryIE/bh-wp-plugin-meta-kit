<?php
/**
 * Adds a link to the plugin's settings on the "Plugin installed successfully" screen.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Appends the Settings link after the "Go to Plugins page" link shown once a plugin has been installed.
 */
class Plugin_Installer {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin settings.
	 * @param LoggerInterface          $logger   The plugin's PSR logger.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Add the settings page link to the existing links, when the plugin has one.
	 *
	 * These are only displayed on subsequent plugin installs, not the first.
	 *
	 * @hooked install_plugin_complete_actions
	 * @see Plugin_Meta_Kit::plugin_installer()
	 * @see \Plugin_Installer_Skin::after()
	 *
	 * @param string[] $install_actions Array of plugin action links.
	 * @param object   $_api            Object containing WordPress.org API plugin data. Empty
	 *                                  for non-API installs, such as when a plugin is installed
	 *                                  via upload.
	 * @param string   $plugin_file     Path to the plugin file relative to the plugins directory.
	 *
	 * @return string[]
	 */
	public function add_settings_link( $install_actions, $_api, $plugin_file ): array {
		if ( $plugin_file !== $this->settings->plugin_basename ) {
			return $install_actions;
		}

		if ( '' === $this->settings->settings_url ) {
			return $install_actions;
		}

		$install_actions[] = '•';

		$install_actions[] = '<a href="' . esc_url( $this->settings->settings_url ) . '">'
			. sprintf(
				/* translators: %s: the plugin's name. */
				esc_html__( 'Go to %s settings', 'bh-wp-plugin-meta-kit' ),
				esc_html( $this->settings->plugin_name ),
			)
			. '</a>';

		return $install_actions;
	}
}
