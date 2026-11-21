<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal;
use BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;

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

		Plugin_Meta_Kit::initialize( $this->get_settings() );

		WP_Mock::assertHooksAdded();
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
