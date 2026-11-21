<?php
/**
 * Display an admin notice inviting the user to configure the plugin. Stops displaying after a week.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Activation;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use Psr\Log\LoggerInterface;
use WPTRT\AdminNotices\Notices;

/**
 * Shown when:
 * * the plugin has a settings screen
 * * it was activated in the past week
 * * its settings screen has not been visited since
 * * current_user_can('manage_options')
 * * the user has not dismissed it
 *
 * @see Plugin_Meta_Kit_Settings::$settings_url
 * @see https://github.com/wptrt/admin-notices
 */
class Activation_Admin_Notice {
	/** How long after activation the notice is shown, in seconds. */
	protected const SHOW_FOR = 7 * 24 * 60 * 60;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings          The plugin settings.
	 * @param Plugin_Activation        $plugin_activation When the plugin was activated and its settings visited.
	 * @param Notices                  $notices           Prints the notice and handles its dismissal.
	 * @param LoggerInterface          $logger            Records when dismissals are reset.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
		protected Plugin_Activation $plugin_activation,
		protected Notices $notices,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Checks if we are recently activated and unconfigured, then displays an admin notice to users.
	 *
	 * Called during `admin_init`, not hooked: the notice must exist before `admin_notices`, and on AJAX requests,
	 * which is how it is dismissed.
	 *
	 * @see Plugin_Meta_Kit::activation_notice()
	 */
	public function add_setup_notice(): void {
		// Without a settings screen there is nothing to invite the user to.
		if ( '' === $this->settings->settings_url ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The dismiss request names the notice it is dismissing, so it must exist to handle it.
		if ( ! wp_doing_ajax() && ! $this->should_show() ) {
			return;
		}

		$this->notices->add(
			$this->get_notice_id(),
			'',
			$this->get_message(),
			array(
				'type'          => 'info',
				'capability'    => 'manage_options',
				'scope'         => 'user',
				'option_prefix' => Dependencies_Notice::DISMISSED_META_KEY_PREFIX,
			),
		);
	}

	/**
	 * Show the notice again to every user who dismissed it.
	 */
	public function reset_dismissed(): void {
		$meta_key = $this->get_dismissed_meta_key();

		delete_metadata( 'user', 0, $meta_key, '', true );

		$this->logger->info(
			'Reset dismissed activation notice of {plugin_slug} for all users, deleting user meta {user_meta_key}.',
			array(
				'plugin_slug'   => $this->settings->plugin_slug,
				'user_meta_key' => $meta_key,
			),
		);
	}

	/**
	 * Whether the notice applies on this page load.
	 */
	protected function should_show(): bool {
		$last_activated = $this->plugin_activation->get_activation_time();

		// If last activation was longer than a week ago, stop annoying users with the admin notice.
		if ( $last_activated < time() - self::SHOW_FOR ) {
			return false;
		}

		// Don't show it on the settings page itself, and remember the visit.
		if ( $this->is_settings_screen() ) {
			$this->plugin_activation->record_settings_visit();

			return false;
		}

		// If the settings page has been viewed since the plugin was activated, do not show it.
		if ( $this->plugin_activation->get_settings_visit_time() > $last_activated ) {
			return false;
		}

		// A dismissed notice is not added at all: the dismiss script expects to find every notice which was added.
		return ! get_user_meta( get_current_user_id(), $this->get_dismissed_meta_key(), true );
	}

	/**
	 * Is the current request for the plugin's settings screen, i.e. the same wp-admin file and the same query
	 * arguments as {@see Plugin_Meta_Kit_Settings::$settings_url}?
	 */
	protected function is_settings_screen(): bool {
		global $pagenow;

		$settings_url_path = wp_parse_url( $this->settings->settings_url, PHP_URL_PATH );

		if ( ! is_string( $settings_url_path ) || basename( $settings_url_path ) !== $pagenow ) {
			return false;
		}

		$settings_url_query = wp_parse_url( $this->settings->settings_url, PHP_URL_QUERY );

		parse_str(
			is_string( $settings_url_query ) ? $settings_url_query : '',
			$settings_url_arguments,
		);

		foreach ( $settings_url_arguments as $name => $value ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only compared with the settings URL, to tell which screen this is.
			$request_value = isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : null;

			if ( $request_value !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The notice's id: unique to the plugin, and only the characters `sanitize_key()` keeps, since it is used in a
	 * meta key, an HTML id and the dismiss request.
	 */
	protected function get_notice_id(): string {
		return sanitize_key( $this->settings->plugin_slug . '_activation' );
	}

	/**
	 * The user meta key which records that a user dismissed the notice.
	 */
	protected function get_dismissed_meta_key(): string {
		return Dependencies_Notice::DISMISSED_META_KEY_PREFIX . '_' . $this->get_notice_id();
	}

	/**
	 * The notice's text.
	 */
	protected function get_message(): string {
		return sprintf(
			/* translators: 1: the plugin's name, 2: opening link tag to the plugin's settings screen, 3: closing link tag. */
			esc_html__( '%1$s options can be configured in its %2$ssettings%3$s.', 'bh-wp-plugin-meta-kit' ),
			'<strong>' . esc_html( $this->settings->plugin_name ) . '</strong>',
			'<a href="' . esc_url( $this->settings->settings_url ) . '">',
			'</a>',
		);
	}
}
