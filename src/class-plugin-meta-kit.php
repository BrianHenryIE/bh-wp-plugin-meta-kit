<?php

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme;

class Plugin_Meta_Kit {

	protected static Plugin_Meta_Kit $instance;

	public static function initialize(): void {

		self::$instance = new Plugin_Meta_Kit(  self::make_settings() );
	}

	public static function get_instance( ?Plugin_Meta_Kit_Settings_Interface $settings = null ): ?Plugin_Meta_Kit {

		if( ! did_action( 'admin_init' ) ) {
			return null;
		}

		if(! isset(self::$instance)) {
			self::initialize( $settings );
		}

		return self::$instance;
	}

	protected static function make_settings(): Plugin_Meta_Kit_Settings_Interface {
		return new class() implements Plugin_Meta_Kit_Settings_Interface {

			protected string $plugin_basename;
			protected string $plugin_slug;
			protected array $root_plugin_file_headers;

			public function __construct() {
				$plugin_basename_file = plugin_basename( __FILE__ );
				$plugin_slug = explode( '/', $plugin_basename_file )[0];

//				$plugin = array_filter(
//					get_plugins($plugin_slug),
//					function( string $e ) use ( $plugin_slug ): bool {
//						return str_starts_with( $e, $plugin_slug );
//					},
//					ARRAY_FILTER_USE_KEY
//				);

				$plugin = get_plugins($plugin_slug);

				$this->plugin_slug = $plugin_slug;
				$this->plugin_basename = array_key_first( $plugin );
				$this->root_plugin_file_headers = $plugin[ $this->plugin_basename ];
			}

			public function get_plugin_slug(): string {
				return $this->plugin_slug;
			}

			public function get_plugin_basename(): string {
				return $this->plugin_basename;
			}

			// The only required plugin header is Name.
			public function get_plugin_name(): string {
				return $this->root_plugin_file_headers['Name'];
			}
		};
	}

	public function __construct(
		protected Plugin_Meta_Kit_Settings_Interface $settings
	) {
	}


	public function view_details_modal(): void {

		$plugins_page_view_details = new Plugins_Page_View_Details_Modal( $this->settings );
		add_filter( "site_transient_update_plugins", array($plugins_page_view_details,'update_plugins_transient') , 10, 2 );
		add_filter('plugins_api', array( $plugins_page_view_details, 'add_tabs' ), 10, 3 );


		$plugin_details_tabs = new Plugins_Page_View_Details_Modal_Readme( $this->settings );
		add_filter('plugins_api', array( $plugin_details_tabs, 'add_tabs' ), 10, 3 );
	}

	public function activation(): void {

		$log_activation_time_for_settings_notice = function() {

			$last_activated_option_name = function( string $plugin_slug ): string {
				return str_replace( '-','_', $plugin_slug ) . '_bhpmk_last_activated';
			};

			update_option(
				$last_activated_option_name( $this->settings->get_plugin_slug() ),
				new \DateTimeImmutable()
			);
		};

		register_activation_hook(
			$this->settings->get_plugin_basename(),
			$log_activation_time_for_settings_notice
		);

	}
}
