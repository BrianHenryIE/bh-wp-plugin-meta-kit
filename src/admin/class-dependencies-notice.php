<?php
/**
 * Show an admin notice for each thing the plugin needs which the site does not have.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Model\Dependency;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use Psr\Log\LoggerInterface;
use Throwable;
use WPTRT\AdminNotices\Notices;

/**
 * Warns users who can do something about it, i.e. deactivate the plugin, that a dependency is missing.
 *
 * Each user can dismiss a notice, which is remembered in their user meta.
 *
 * @see Plugin_Meta_Kit_Settings::$dependencies
 * @see https://github.com/wptrt/admin-notices
 */
class Dependencies_Notice {
	/** The start of the user meta key which records that a user dismissed a notice; the notice's id follows it. */
	public const DISMISSED_META_KEY_PREFIX = 'bh_wp_plugin_meta_kit_notice_dismissed';

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin and its dependencies.
	 * @param Notices                  $notices  Prints the notices and handles their dismissal.
	 * @param LoggerInterface          $logger   Records when dismissals are reset.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
		protected Notices $notices,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Add a notice for each missing dependency: what is missing, why it is needed, and a link to deactivate the plugin.
	 *
	 * Called during `admin_init`, not hooked: the notices must exist before `admin_notices`, and on AJAX requests,
	 * which is how a notice is dismissed.
	 *
	 * @see Plugin_Meta_Kit::dependencies_notice()
	 */
	public function add_notices(): void {
		if ( array() === $this->settings->dependencies ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// The dismiss request names the notice it is dismissing, so each must exist to handle it, missing or not.
		$is_dismiss_request = wp_doing_ajax();

		foreach ( $this->settings->dependencies as $dependency ) {
			if ( ! $is_dismiss_request && ( $this->is_dismissed( $dependency ) || $this->is_satisfied( $dependency ) ) ) {
				continue;
			}

			$this->notices->add(
				$this->get_notice_id( $dependency ),
				'',
				$this->get_message( $dependency ),
				array(
					'type'          => 'warning',
					'capability'    => 'activate_plugins',
					'scope'         => 'user',
					'option_prefix' => self::DISMISSED_META_KEY_PREFIX,
				),
			);
		}
	}

	/**
	 * Show the notices again to every user who dismissed them.
	 */
	public function reset_dismissed(): void {
		foreach ( $this->settings->dependencies as $dependency ) {
			$meta_key = $this->get_dismissed_meta_key( $dependency );

			delete_metadata( 'user', 0, $meta_key, '', true );

			$this->logger->info(
				'Reset dismissed dependency notice {dependency_name} of {plugin_slug} for all users, deleting user meta {user_meta_key}.',
				array(
					'dependency_name' => $dependency->name,
					'plugin_slug'     => $this->settings->plugin_slug,
					'user_meta_key'   => $meta_key,
				),
			);
		}
	}

	/**
	 * The notice's id: unique to the plugin and the dependency, and only the characters `sanitize_key()` keeps,
	 * since it is used in a meta key, an HTML id and the dismiss request.
	 *
	 * @param Dependency $dependency The dependency the notice is about.
	 */
	protected function get_notice_id( Dependency $dependency ): string {
		return sanitize_key( $this->settings->plugin_slug . '_dependency_' . sanitize_title( $dependency->name ) );
	}

	/**
	 * The user meta key which records that a user dismissed the dependency's notice.
	 *
	 * @param Dependency $dependency The dependency the notice is about.
	 */
	protected function get_dismissed_meta_key( Dependency $dependency ): string {
		return self::DISMISSED_META_KEY_PREFIX . '_' . $this->get_notice_id( $dependency );
	}

	/**
	 * Has the current user dismissed the dependency's notice?
	 *
	 * A dismissed notice is not added at all: the dismiss script expects to find every notice which was added.
	 *
	 * @param Dependency $dependency The dependency the notice is about.
	 */
	protected function is_dismissed( Dependency $dependency ): bool {
		return (bool) get_user_meta( get_current_user_id(), $this->get_dismissed_meta_key( $dependency ), true );
	}

	/**
	 * Run the dependency's check.
	 *
	 * A check which throws says nothing about the dependency, so no notice is shown for it: the check is the
	 * plugin's own code, and must not take down wp-admin.
	 *
	 * @param Dependency $dependency The dependency to check.
	 */
	protected function is_satisfied( Dependency $dependency ): bool {
		try {
			return ( $dependency->is_satisfied )();
		} catch ( Throwable $throwable ) {
			$this->logger->error(
				'The check for dependency {dependency_name} of {plugin_slug} threw {exception_class}: {exception_message}',
				array(
					'dependency_name'   => $dependency->name,
					'plugin_slug'       => $this->settings->plugin_slug,
					'exception_class'   => $throwable::class,
					'exception_message' => $throwable->getMessage(),
					'exception'         => $throwable,
				),
			);

			return true;
		}
	}

	/**
	 * The notice's text.
	 *
	 * @param Dependency $dependency The missing dependency.
	 */
	protected function get_message( Dependency $dependency ): string {
		$allowed_html = array(
			'a' => array(
				'href' => array(),
				'rel'  => array(),
			),
			'strong' => array(),
			'em'     => array(),
		);

		$dependency_name = esc_html( $dependency->name );

		if ( '' !== $dependency->url ) {
			$dependency_name = '<a href="' . esc_url( $dependency->url ) . '">' . $dependency_name . '</a>';
		}

		$message = '<strong>' . esc_html( $this->settings->plugin_name ) . '</strong>: ';

		$message .= sprintf(
			/* translators: %s: the name of what is missing, e.g. "GMP PHP extension", linked to where to read about it. */
			esc_html__( '%s is required but is not available on this site.', 'bh-wp-plugin-meta-kit' ),
			$dependency_name,
		);

		if ( '' !== $dependency->description ) {
			$message .= ' ' . wp_kses( $dependency->description, $allowed_html );
		}

		$message .= ' <a href="' . esc_url( $this->get_deactivate_url() ) . '">';
		$message .= sprintf(
			/* translators: %s: the plugin's name. */
			esc_html__( 'Deactivate %s', 'bh-wp-plugin-meta-kit' ),
			esc_html( $this->settings->plugin_name ),
		);
		$message .= '</a>.';

		return $message;
	}

	/**
	 * The URL which deactivates the plugin, as the Deactivate link on plugins.php does.
	 */
	protected function get_deactivate_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'deactivate',
					'plugin' => $this->settings->plugin_basename,
				),
				admin_url( 'plugins.php' ),
			),
			'deactivate-plugin_' . $this->settings->plugin_basename,
		);
	}
}
