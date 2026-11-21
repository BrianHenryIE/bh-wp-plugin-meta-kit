<?php
/**
 * The library's entry point.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Activation_Admin_Notice;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Dependencies_Notice;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugin_Installer;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_Table_Row;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Tab_Changelog;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WPTRT\AdminNotices\Notices;

/**
 * Holds the single instance and adds the hooks for each feature.
 */
class Plugin_Meta_Kit {
	/** The single instance, set by {@see self::initialize()}. */
	protected static Plugin_Meta_Kit $instance;

	/** Prints the admin notices and handles their dismissal; set by {@see self::get_notices()}. */
	protected ?Notices $notices = null;

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
		self::$instance->activation_notice();
		self::$instance->plugins_page_table_row();
		self::$instance->plugin_installer();
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
	 * Add the hooks which add the Settings and GitHub links to the plugin's row on plugins.php.
	 */
	public function plugins_page_table_row(): void {
		$plugins_page_table_row = new Plugins_Page_Table_Row( $this->settings );
		add_filter(
			'plugin_action_links_' . $this->settings->plugin_basename,
			array( $plugins_page_table_row, 'action_links' ),
			10,
			4,
		);
		add_filter(
			'plugin_row_meta',
			array( $plugins_page_table_row, 'add_github_row_meta' ),
			10,
			4,
		);
	}

	/**
	 * Add the hook which adds the Settings link to the screen shown after the plugin is installed.
	 */
	public function plugin_installer(): void {
		$plugin_installer = new Plugin_Installer( $this->settings, $this->logger );
		add_filter(
			'install_plugin_complete_actions',
			array( $plugin_installer, 'add_settings_link' ),
			10,
			3,
		);
	}

	/**
	 * Warn users who can deactivate the plugin when the site lacks something it needs.
	 */
	public function dependencies_notice(): void {
		$dependencies_notice = new Dependencies_Notice( $this->settings, $this->get_notices(), $this->logger );
		$dependencies_notice->add_notices();
	}

	/**
	 * For a week after the plugin is activated, invite administrators to its settings screen.
	 *
	 * The activation must have been recorded: the zero-config setup does that, otherwise {@see self::activation()}.
	 */
	public function activation_notice(): void {
		$activation_admin_notice = new Activation_Admin_Notice(
			$this->settings,
			new Plugin_Activation( $this->settings->plugin_slug, $this->logger ),
			$this->get_notices(),
			$this->logger,
		);
		$activation_admin_notice->add_setup_notice();
	}

	/**
	 * The notices, with the hook which prints them added the first time.
	 */
	protected function get_notices(): Notices {
		if ( null === $this->notices ) {
			$this->notices = new Notices();
			$this->notices->boot();
		}

		return $this->notices;
	}

	/**
	 * Show every notice again to the users who dismissed it.
	 *
	 * Works on any request, not only wp-admin page loads, e.g. REST and WP-CLI.
	 */
	public function reset_dismissed_notices(): void {
		$dependencies_notice = new Dependencies_Notice( $this->settings, new Notices(), $this->logger );
		$dependencies_notice->reset_dismissed();

		$activation_admin_notice = new Activation_Admin_Notice(
			$this->settings,
			new Plugin_Activation( $this->settings->plugin_slug, $this->logger ),
			new Notices(),
			$this->logger,
		);
		$activation_admin_notice->reset_dismissed();
	}

	/**
	 * Record when the plugin is activated, for the temporary activation admin notice.
	 *
	 * Call it as the plugin's file is loaded, as `register_activation_hook()` must be: `admin_init` has already
	 * run by the time WordPress loads a plugin to activate it. The zero-config setup records activation itself.
	 */
	public function activation(): void {
		$plugin_activation = new Plugin_Activation( $this->settings->plugin_slug, $this->logger );

		register_activation_hook(
			$this->settings->plugin_basename,
			array( $plugin_activation, 'record_activation' ),
		);
	}
}
