<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_Table_Row;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Tab_Changelog;
use BrianHenryIE\Plugin_Meta_Kit\Model\Dependency;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;
use WPTRT\AdminNotices\Notices;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit
 */
class Plugin_Meta_Kit_Unit_Test extends Unit_Testcase {
	protected function get_settings(): Plugin_Meta_Kit_Settings {
		return new Plugin_Meta_Kit_Settings(
			plugin_slug: 'my-plugin',
			plugin_basename: 'my-plugin/my-plugin.php',
			plugin_name: 'My Plugin',
		);
	}

	/**
	 * @covers ::initialize
	 * @covers ::view_details_modal
	 */
	public function test_initialize_adds_view_details_modal_hooks(): void {
		WP_Mock::expectFilterAdded(
			'site_transient_update_plugins',
			array( new AnyInstance( Plugins_Page_View_Details_Modal::class ), 'update_plugins_transient' ),
			10,
			2,
		);
		WP_Mock::expectFilterAdded(
			'plugins_api',
			array( new AnyInstance( Plugins_Page_View_Details_Modal::class ), 'add_tabs' ),
			10,
			3,
		);
		WP_Mock::expectFilterAdded(
			'plugins_api',
			array( new AnyInstance( Plugins_Page_View_Details_Modal_Readme::class ), 'add_tabs' ),
			10,
			3,
		);

		WP_Mock::expectFilterAdded(
			'plugins_api',
			array( new AnyInstance( Plugins_Page_View_Details_Modal_Tab_Changelog::class ), 'add_changelog_tab' ),
			11,
			3,
		);

		Plugin_Meta_Kit::initialize( $this->get_settings() );

		WP_Mock::assertHooksAdded();
	}

	/**
	 * @covers ::initialize
	 * @covers ::dependencies_notice
	 */
	public function test_initialize_adds_the_hook_which_prints_dependency_notices(): void {
		WP_Mock::expectActionAdded(
			'admin_notices',
			array( new AnyInstance( Notices::class ), 'the_notices' ),
		);

		Plugin_Meta_Kit::initialize( $this->get_settings() );

		WP_Mock::assertHooksAdded();
	}

	/**
	 * @covers ::initialize
	 * @covers ::plugins_page_table_row
	 */
	public function test_initialize_adds_the_settings_link_hook_for_this_plugins_row(): void {
		WP_Mock::expectFilterAdded(
			'plugin_action_links_my-plugin/my-plugin.php',
			array( new AnyInstance( Plugins_Page_Table_Row::class ), 'action_links' ),
			10,
			4,
		);

		Plugin_Meta_Kit::initialize( $this->get_settings() );

		WP_Mock::assertHooksAdded();
	}

	/**
	 * It is not only called on wp-admin page loads, where `initialize()` has set the notices up.
	 *
	 * @covers ::reset_dismissed_notices
	 */
	public function test_reset_dismissed_notices_without_initialize(): void {
		WP_Mock::passthruFunction( 'sanitize_key' );
		WP_Mock::passthruFunction( 'sanitize_title' );

		WP_Mock::userFunction( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'bh_wp_plugin_meta_kit_notice_dismissed_my-plugin_dependency_woocommerce', '', true )
			->andReturn( true );

		$settings = new Plugin_Meta_Kit_Settings(
			plugin_slug: 'my-plugin',
			plugin_basename: 'my-plugin/my-plugin.php',
			plugin_name: 'My Plugin',
			dependencies: array( new Dependency( name: 'woocommerce', is_satisfied: fn (): bool => false ) ),
		);

		( new Plugin_Meta_Kit( $settings, $this->logger ) )->reset_dismissed_notices();

		$this->assertTrue( $this->logger->hasInfoRecords() );
	}

	/**
	 * @covers ::get_instance
	 */
	public function test_get_instance_is_null_before_admin_init(): void {
		WP_Mock::userFunction( 'did_action' )
			->with( 'admin_init' )
			->andReturn( 0 );

		$this->assertNull( Plugin_Meta_Kit::get_instance( $this->get_settings() ) );
	}

	/**
	 * @covers ::get_instance
	 */
	public function test_get_instance_returns_the_initialized_instance(): void {
		WP_Mock::userFunction( 'did_action' )
			->with( 'admin_init' )
			->andReturn( 1 );

		Plugin_Meta_Kit::initialize( $this->get_settings() );

		$this->assertInstanceOf(
			Plugin_Meta_Kit::class,
			Plugin_Meta_Kit::get_instance(),
		);
	}
}
