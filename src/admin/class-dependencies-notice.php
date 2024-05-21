<?php
/**
 * Print an admin notice if the required GMP PHP extension is not present.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings_Interface;

/**
 * Hooks into wp-admin's admin_notice action and prints a warning if the plugin's dependencies are not present.
 *
 * @see wp-admin/admin-header.php
 */
class Dependencies_Notice {

	/**
	 * Constructor
	 *
	 * @param Plugin_Meta_Kit_Settings_Interface $settings The plugin settings.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings_Interface $settings
	) {
	}

	/**
	 * Print the admin notice, if the dependency is missing and it is an admin logged in.
	 * The notice links to the PHP.net GMP page, and contains a deactivate link for the plugin.
	 *
	 * @hooked admin_notices
	 */
	public function print_dependencies_notice(): void {

		if ( $this->api->is_server_has_dependencies() ) {
			return;
		}

		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p>';
		echo '<b>Bitcoin Gateway</b>.';
		echo ' ';

		$gmp_link = '<a target="_blank" href="https://www.php.net/manual/en/book.gmp.php">GMP (GNU Multiple Precision Arithmetic Library)</a>';

		printf(
			/* translators: %s is replaced with a link to the PHP.net page for the missing GMP extension. */
			esc_html( __( 'Required PHP extension %s is not installed on this server. This is required for the calculations to derive Bitcoin payment addresses.', 'bh-wp-bitcoin-gateway' ) ),
			wp_kses(
				$gmp_link,
				array(
					'a' => array(
						'target' => array(),
						'href'   => array(),
					),
				)
			)
		);
		echo ' ';

		echo wp_kses( __( 'Please <b>contact your hosting provider for support</b>.', 'bh-wp-bitcoin-gateway' ), array( 'b' => array() ) );
		echo ' ';

		$deactivate_link = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'deactivate',
					'plugin' => $this->settings->get_plugin_basename(),
				),
				admin_url(
					'plugins.php'
				)
			),
			"deactivate-plugin_{$this->settings->get_plugin_basename()}"
		);

		echo ' <a href="' . esc_url( $deactivate_link ) . '">';
		echo esc_html( __( 'Deactivate Bitcoin Gateway plugin', 'bh-wp-bitcoin-gateway' ) );
		echo '</a>.';

		echo '</p>';
		echo '</div>';
	}
}
