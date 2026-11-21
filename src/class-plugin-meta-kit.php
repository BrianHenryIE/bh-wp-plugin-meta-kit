<?php
/**
 * The library's entry point.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Dependencies_Notice;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Tab_Changelog;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WPTRT\AdminNotices\Notices;

/**
 * Holds the single instance and adds the hooks for each feature.
 */
class Plugin_Meta_Kit {
	/** The single instance, set by {@see self::initialize()}. */
	protected static Plugin_Meta_Kit $instance;

	/** Set by {@see self::dependencies_notice()}. */
	protected ?Dependencies_Notice $dependencies_notice = null;

	/**
	 * Create the single instance and add its hooks.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin the library is working for.
	 * @param ?LoggerInterface         $logger   The plugin's logger, if it has one.
	 */
	public static function initialize( Plugin_Meta_Kit_Settings $settings, ?LoggerInterface $logger = null ): void {
		self::$instance = new Plugin_Meta_Kit( $settings, $logger ?? new NullLogger() );
		self::$instance->view_details_modal();
		self::$instance->dependencies_notice();
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
	 * @param LoggerInterface          $logger   Records the changes the library makes.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
		protected LoggerInterface $logger = new NullLogger(),
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

		$plugin_file_finder = new Plugin_File_Finder( $this->settings );

		$plugin_details_tabs = new Plugins_Page_View_Details_Modal_Readme(
			$this->settings,
			new Readme_Parser(),
			$plugin_file_finder,
		);
		add_filter( 'plugins_api', array( $plugin_details_tabs, 'add_tabs' ), 10, 3 );

		// After the readme, whose changelog it merges with `CHANGELOG.md`.
		$changelog_tab = new Plugins_Page_View_Details_Modal_Tab_Changelog(
			$this->settings,
			$plugin_file_finder,
			new Changelog_Parser(),
			new Changelog_Merger(),
		);
		add_filter(
			'plugins_api',
			array( $changelog_tab, 'add_changelog_tab' ),
			11,
			3,
		);
	}

	/**
	 * Warn users who can deactivate the plugin when the site lacks something it needs.
	 */
	public function dependencies_notice(): void {
		$notices = new Notices();
		$notices->boot();

		$this->dependencies_notice = new Dependencies_Notice( $this->settings, $notices, $this->logger );
		$this->dependencies_notice->add_notices();
	}

	/**
	 * Show every notice again to the users who dismissed it.
	 *
	 * Works on any request, not only those where {@see self::dependencies_notice()} has run, e.g. REST and WP-CLI.
	 */
	public function reset_dismissed_notices(): void {
		$dependencies_notice = $this->dependencies_notice ?? new Dependencies_Notice( $this->settings, new Notices(), $this->logger );

		$dependencies_notice->reset_dismissed();
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
