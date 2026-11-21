<?php
/**
 * The library's entry point.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme;
use DateTimeImmutable;

/**
 * Holds the single instance and adds the hooks for each feature.
 */
class Plugin_Meta_Kit {
	/** The single instance, set by {@see self::initialize()}. */
	protected static Plugin_Meta_Kit $instance;

	/**
	 * Create the single instance and add its hooks.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin the library is working for.
	 */
	public static function initialize( Plugin_Meta_Kit_Settings $settings ): void {
		self::$instance = new Plugin_Meta_Kit( $settings );
		self::$instance->view_details_modal();
	}

	/**
	 * The instance, once `admin_init` has run.
	 *
	 * @param ?Plugin_Meta_Kit_Settings $settings Used to create the instance when the zero-config setup has not.
	 */
	public static function get_instance( ?Plugin_Meta_Kit_Settings $settings = null ): ?Plugin_Meta_Kit {
		if ( ! did_action( 'admin_init' ) ) {
			return null;
		}

		if ( ! isset( self::$instance ) && null !== $settings ) {
			self::initialize( $settings );
		}

		return self::$instance ?? null;
	}

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin the library is working for.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
	) {
	}

	/**
	 * Add the hooks which make the "View details" link appear on plugins.php and fill the modal it opens.
	 */
	public function view_details_modal(): void {
		$plugins_page_view_details = new Plugins_Page_View_Details_Modal( $this->settings );
		add_filter(
			'site_transient_update_plugins',
			array( $plugins_page_view_details, 'update_plugins_transient' ),
			10,
			2,
		);
		add_filter(
			'plugins_api',
			array( $plugins_page_view_details, 'add_tabs' ),
			10,
			3,
		);

		$plugin_details_tabs = new Plugins_Page_View_Details_Modal_Readme( $this->settings, new Readme_Parser() );
		add_filter( 'plugins_api', array( $plugin_details_tabs, 'add_tabs' ), 10, 3 );
	}

	/**
	 * Record when the plugin was last activated, for the temporary activation admin notice.
	 */
	public function activation(): void {
		$log_activation_time_for_settings_notice = function (): void {
			$last_activated_option_name = function ( string $plugin_slug ): string {
				return str_replace( '-', '_', $plugin_slug ) . '_bhpmk_last_activated';
			};

			update_option(
				$last_activated_option_name( $this->settings->plugin_slug ),
				new DateTimeImmutable(),
			);
		};

		register_activation_hook(
			$this->settings->plugin_basename,
			$log_activation_time_for_settings_notice,
		);
	}
}
