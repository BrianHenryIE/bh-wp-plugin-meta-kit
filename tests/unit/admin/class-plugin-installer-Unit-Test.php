<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use stdClass;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugin_Installer
 */
class Plugin_Installer_Unit_Test extends Unit_Testcase {
	protected const EXISTING_ACTIONS = array(
		'activate_plugin' => '<a href="plugins.php?action=activate">Activate Plugin</a>',
		'plugins_page'    => '<a href="plugins.php">Go to Plugins page</a>',
	);

	/**
	 * @param string $settings_url     The plugin's settings screen.
	 * @param bool   $passthru_esc_url False when the test mocks `esc_url()` itself.
	 */
	protected function get_sut( string $settings_url, bool $passthru_esc_url = true ): Plugin_Installer {
		if ( $passthru_esc_url ) {
			WP_Mock::passthruFunction( 'esc_url' );
		}
		WP_Mock::passthruFunction( 'esc_html' );

		return new Plugin_Installer(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: 'my-plugin',
				plugin_basename: 'my-plugin/my-plugin.php',
				plugin_name: 'My Plugin',
				settings_url: $settings_url,
			),
			$this->logger,
		);
	}

	/**
	 * @covers ::add_settings_link
	 */
	public function test_settings_link_uses_the_url_in_the_settings(): void {
		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin' );

		$result = $sut->add_settings_link( self::EXISTING_ACTIONS, new stdClass(), 'my-plugin/my-plugin.php' );

		$this->assertSame(
			array(
				'activate_plugin' => '<a href="plugins.php?action=activate">Activate Plugin</a>',
				'plugins_page'    => '<a href="plugins.php">Go to Plugins page</a>',
				0                 => '•',
				1                 => '<a href="https://example.org/wp-admin/admin.php?page=my-plugin">Go to My Plugin settings</a>',
			),
			$result,
		);
	}

	/**
	 * The filter runs whichever plugin was installed.
	 *
	 * @covers ::add_settings_link
	 */
	public function test_no_settings_link_when_another_plugin_was_installed(): void {
		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin' );

		$result = $sut->add_settings_link( self::EXISTING_ACTIONS, new stdClass(), 'another-plugin/another-plugin.php' );

		$this->assertSame( self::EXISTING_ACTIONS, $result );
	}

	/**
	 * @covers ::add_settings_link
	 */
	public function test_no_settings_link_when_the_plugin_has_no_settings_url(): void {
		$result = $this->get_sut( '' )->add_settings_link( self::EXISTING_ACTIONS, new stdClass(), 'my-plugin/my-plugin.php' );

		$this->assertSame( self::EXISTING_ACTIONS, $result );
	}

	/**
	 * @covers ::add_settings_link
	 */
	public function test_settings_url_is_escaped(): void {
		WP_Mock::userFunction( 'esc_url' )
			->once()
			->with( 'https://example.org/wp-admin/admin.php?page=my-plugin&tab="general"' )
			->andReturn( 'https://example.org/wp-admin/admin.php?page=my-plugin&#038;tab=general' );

		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin&tab="general"', false );

		$result = $sut->add_settings_link( array(), new stdClass(), 'my-plugin/my-plugin.php' );

		$this->assertSame(
			array(
				'•',
				'<a href="https://example.org/wp-admin/admin.php?page=my-plugin&#038;tab=general">Go to My Plugin settings</a>',
			),
			$result,
		);
	}
}
