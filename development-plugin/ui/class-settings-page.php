<?php
/**
 * The development plugin's settings screen: Settings → Meta Kit Development.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Development_Plugin\UI;

use BrianHenryIE\Plugin_Meta_Kit\Development_Plugin\Library_Setup;

/**
 * Switches the example dependency's notice on and off, and shows dismissed notices again.
 */
class Settings_Page {
	public const PAGE_SLUG = 'development-plugin';

	public const RESET_ACTION = 'development_plugin_reset_dismissed_notices';

	/**
	 * Constructor.
	 *
	 * @param Library_Setup $library_setup Resets the library's dismissed notices.
	 */
	public function __construct(
		protected Library_Setup $library_setup,
	) {
	}

	/**
	 * Add the hooks which add the screen and handle its reset button.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action(
			'admin_post_' . self::RESET_ACTION,
			array( $this, 'reset_dismissed_notices' ),
		);
	}

	/**
	 * Add the screen under Settings.
	 *
	 * @hooked admin_menu
	 * @see self::register_hooks()
	 */
	public function add_page(): void {
		add_options_page(
			'Meta Kit Development',
			'Meta Kit Development',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'print_page' ),
		);
	}

	/**
	 * Print the screen.
	 *
	 * @see self::add_page()
	 */
	public function print_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_dependency_missing = (bool) get_option( Library_Setup::MISSING_DEPENDENCY_OPTION, false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses whether to print a confirmation; the reset itself checks a nonce.
		$was_reset = isset( $_GET['dismissed-notices-reset'] );

		echo '<div class="wrap">';
		echo '<h1>Meta Kit Development</h1>';

		if ( $was_reset ) {
			echo '<div class="notice notice-success" id="development-plugin-dismissed-notices-reset"><p>Dismissed notices will be shown again.</p></div>';
		}

		echo '<h2>Example dependency</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'development_plugin' );
		echo '<p><label>';
		echo '<input type="checkbox" name="' . esc_attr( Library_Setup::MISSING_DEPENDENCY_OPTION ) . '" value="1" ' . checked( $is_dependency_missing, true, false ) . '> ';
		echo 'Report the example dependency (the GMP PHP extension) as missing, so its admin notice is shown.';
		echo '</label></p>';
		submit_button( 'Save' );
		echo '</form>';

		echo '<h2>Dismissed notices</h2>';
		echo '<p>A dismissed notice stays hidden for the user who dismissed it. This shows them all again, to everyone.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::RESET_ACTION ) . '">';
		wp_nonce_field( self::RESET_ACTION );
		submit_button(
			'Reset dismissed notices',
			'secondary',
			'reset-dismissed-notices',
		);
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Handle the reset button, then return to the screen.
	 *
	 * @hooked admin_post_development_plugin_reset_dismissed_notices
	 * @see self::register_hooks()
	 */
	public function reset_dismissed_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You are not allowed to reset dismissed notices.', 403 );
		}

		check_admin_referer( self::RESET_ACTION );

		$this->library_setup->reset_dismissed_notices();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => self::PAGE_SLUG,
					'dismissed-notices-reset' => '1',
				),
				admin_url( 'options-general.php' ),
			),
		);
		exit;
	}
}
