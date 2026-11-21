<?php
/**
 * REST route for the Playwright tests to arrange and clean up dismissed notices.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Development_Plugin\Rest;

use BrianHenryIE\Plugin_Meta_Kit\Development_Plugin\Library_Setup;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `DELETE /wp-json/development-plugin/v1/dismissed-notices` shows every dismissed notice again.
 */
class Dismissed_Notices_Controller {
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
	 * Add the hook which registers the route.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 *
	 * @hooked rest_api_init
	 * @see self::register_hooks()
	 */
	public function register_routes(): void {
		register_rest_route(
			'development-plugin/v1',
			'/dismissed-notices',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_dismissed_notices' ),
				'permission_callback' => fn (): bool => current_user_can( 'manage_options' ),
			),
		);
	}

	/**
	 * Show every dismissed notice again, to every user.
	 *
	 * @see self::register_routes()
	 */
	public function delete_dismissed_notices(): WP_REST_Response {
		$this->library_setup->reset_dismissed_notices();

		return new WP_REST_Response( array( 'reset' => true ) );
	}
}
