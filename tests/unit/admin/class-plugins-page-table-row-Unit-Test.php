<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_Table_Row
 */
class Plugins_Page_Table_Row_Unit_Test extends Unit_Testcase {
	/**
	 * @param string $settings_url     The plugin's settings screen.
	 * @param bool   $passthru_esc_url False when the test mocks `esc_url()` itself.
	 * @param string $github_url       The plugin's repository.
	 */
	protected function get_sut( string $settings_url, bool $passthru_esc_url = true, string $github_url = '' ): Plugins_Page_Table_Row {
		if ( $passthru_esc_url ) {
			WP_Mock::passthruFunction( 'esc_url' );
		}

		return new Plugins_Page_Table_Row(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: 'my-plugin',
				plugin_basename: 'my-plugin/my-plugin.php',
				plugin_name: 'My Plugin',
				settings_url: $settings_url,
				github_url: $github_url,
			),
		);
	}

	/**
	 * @covers ::action_links
	 */
	public function test_settings_link_uses_the_url_in_the_settings(): void {
		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin' );

		$result = $sut->action_links( array(), 'my-plugin/my-plugin.php', array(), 'all' );

		$this->assertSame(
			array( 'settings' => '<a href="https://example.org/wp-admin/admin.php?page=my-plugin">Settings</a>' ),
			$result,
		);
	}

	/**
	 * @covers ::action_links
	 */
	public function test_settings_link_is_before_the_existing_links(): void {
		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin' );

		$existing_links = array(
			'deactivate' => '<a href="plugins.php?action=deactivate">Deactivate</a>',
			0            => '<a href="https://example.org/docs">Docs</a>',
		);

		$result = $sut->action_links( $existing_links, 'my-plugin/my-plugin.php', array(), 'all' );

		$this->assertSame(
			array( 'settings', 'deactivate', 0 ),
			array_keys( $result ),
		);
		$this->assertSame(
			'<a href="plugins.php?action=deactivate">Deactivate</a>',
			$result['deactivate'],
		);
		$this->assertSame(
			'<a href="https://example.org/docs">Docs</a>',
			$result[0],
		);
	}

	/**
	 * @covers ::action_links
	 */
	public function test_no_settings_link_when_the_plugin_has_no_settings_url(): void {
		$existing_links = array( 'deactivate' => '<a href="plugins.php?action=deactivate">Deactivate</a>' );

		$result = $this->get_sut( '' )->action_links( $existing_links, 'my-plugin/my-plugin.php', array(), 'all' );

		$this->assertSame( $existing_links, $result );
	}

	/**
	 * @covers ::action_links
	 */
	public function test_settings_url_is_escaped(): void {
		WP_Mock::userFunction( 'esc_url' )
			->once()
			->with( 'https://example.org/wp-admin/admin.php?page=my-plugin&tab="general"' )
			->andReturn( 'https://example.org/wp-admin/admin.php?page=my-plugin&#038;tab=general' );

		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin&tab="general"', false );

		$result = $sut->action_links( array(), 'my-plugin/my-plugin.php', array(), 'all' );

		$this->assertSame(
			'<a href="https://example.org/wp-admin/admin.php?page=my-plugin&#038;tab=general">Settings</a>',
			$result['settings'],
		);
	}

	/**
	 * WordPress passes null for these on some screens.
	 *
	 * @covers ::action_links
	 */
	public function test_null_plugin_file_data_and_context_are_accepted(): void {
		$sut = $this->get_sut( 'https://example.org/wp-admin/admin.php?page=my-plugin' );

		$result = $sut->action_links( array(), null, null, null );

		$this->assertArrayHasKey( 'settings', $result );
	}

	/**
	 * @covers ::add_github_row_meta
	 */
	public function test_github_link_uses_the_url_in_the_settings(): void {
		$sut = $this->get_sut( '', true, 'https://github.com/example/my-plugin' );

		$existing_meta = array( 'Version 1.2.3', 'By <a href="https://example.org">Example</a>' );

		$result = $sut->add_github_row_meta( $existing_meta, 'my-plugin/my-plugin.php', array(), 'active' );

		$this->assertSame(
			array(
				'Version 1.2.3',
				'By <a href="https://example.org">Example</a>',
				'<a target="_blank" href="https://github.com/example/my-plugin">View on GitHub</a>',
			),
			$result,
		);
	}

	/**
	 * `plugin_row_meta` is called for every plugin's row.
	 *
	 * @covers ::add_github_row_meta
	 */
	public function test_github_link_is_not_added_to_other_plugins_rows(): void {
		$sut = $this->get_sut( '', true, 'https://github.com/example/my-plugin' );

		$existing_meta = array( 'Version 4.5.6' );

		$result = $sut->add_github_row_meta( $existing_meta, 'another-plugin/another-plugin.php', array(), 'active' );

		$this->assertSame( $existing_meta, $result );
	}

	/**
	 * @covers ::add_github_row_meta
	 */
	public function test_no_github_link_when_the_plugin_has_no_github_url(): void {
		$existing_meta = array( 'Version 1.2.3' );

		$result = $this->get_sut( '' )->add_github_row_meta( $existing_meta, 'my-plugin/my-plugin.php', array(), 'active' );

		$this->assertSame( $existing_meta, $result );
	}

	/**
	 * @covers ::add_github_row_meta
	 */
	public function test_github_url_is_escaped(): void {
		WP_Mock::userFunction( 'esc_url' )
			->once()
			->with( 'https://github.com/example/my-plugin"onclick="alert(1)' )
			->andReturn( 'https://github.com/example/my-pluginonclick=alert(1)' );

		$sut = $this->get_sut( '', false, 'https://github.com/example/my-plugin"onclick="alert(1)' );

		$result = $sut->add_github_row_meta( array(), 'my-plugin/my-plugin.php', array(), 'active' );

		$this->assertSame(
			array( '<a target="_blank" href="https://github.com/example/my-pluginonclick=alert(1)">View on GitHub</a>' ),
			$result,
		);
	}
}
