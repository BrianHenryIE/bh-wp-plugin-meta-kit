<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use stdClass;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal
 */
class Plugins_Page_View_Details_Modal_Unit_Test extends Unit_Testcase {
	protected function get_sut(): Plugins_Page_View_Details_Modal {
		return new Plugins_Page_View_Details_Modal(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: 'my-plugin',
				plugin_basename: 'my-plugin/my-plugin.php',
				plugin_name: 'My Plugin',
			),
		);
	}

	/**
	 * WordPress prints "View details" for the plugins in the transient which have a slug.
	 *
	 * @covers ::update_plugins_transient
	 */
	public function test_plugin_is_added_to_the_plugins_without_an_update(): void {
		$another_plugin       = new stdClass();
		$another_plugin->slug = 'another-plugin';

		$transient               = new stdClass();
		$transient->last_checked = 1767225600;
		$transient->no_update    = array( 'another-plugin/another-plugin.php' => $another_plugin );

		$result = $this->get_sut()->update_plugins_transient( $transient, 'update_plugins' );

		$this->assertSame( $transient, $result );
		$this->assertSame( 1767225600, $transient->last_checked );

		$no_update = $transient->no_update;
		$this->assertIsArray( $no_update );
		$this->assertSame(
			array( 'another-plugin/another-plugin.php', 'my-plugin/my-plugin.php' ),
			array_keys( $no_update ),
		);
		$this->assertSame(
			$another_plugin,
			$no_update['another-plugin/another-plugin.php'],
		);
		$this->assertEquals(
			(object) array( 'slug' => 'my-plugin' ),
			$no_update['my-plugin/my-plugin.php'],
		);
	}

	/**
	 * @covers ::update_plugins_transient
	 */
	public function test_plugin_is_added_when_the_transient_lists_no_plugins_yet(): void {
		$transient = new stdClass();

		$this->get_sut()->update_plugins_transient( $transient, 'update_plugins' );

		$this->assertEquals(
			array( 'my-plugin/my-plugin.php' => (object) array( 'slug' => 'my-plugin' ) ),
			$transient->no_update,
		);
	}

	/**
	 * The transient is false until WordPress has checked for updates.
	 *
	 * @covers ::update_plugins_transient
	 */
	public function test_a_transient_which_is_not_set_is_left_alone(): void {
		$this->assertFalse( $this->get_sut()->update_plugins_transient( false, 'update_plugins' ) );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_plugin_information_has_the_slug_name_and_no_sections(): void {
		$result = $this->get_sut()->add_tabs( false, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		$this->assertEquals(
			(object) array(
				'slug'     => 'my-plugin',
				'name'     => 'My Plugin',
				'sections' => array(),
			),
			$result,
		);
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_other_plugins_are_left_alone(): void {
		$result = $this->get_sut()->add_tabs( false, 'plugin_information', (object) array( 'slug' => 'another-plugin' ) );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_requests_without_a_slug_are_left_alone(): void {
		$this->assertFalse( $this->get_sut()->add_tabs( false, 'hot_tags', new stdClass() ) );
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_what_an_earlier_filter_set_is_kept(): void {
		$earlier_result           = new stdClass();
		$earlier_result->name     = 'Name From Another Filter';
		$earlier_result->sections = array( 'description' => '<p>Described.</p>' );
		$earlier_result->rating   = 100;

		$result = $this->get_sut()->add_tabs( $earlier_result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		$this->assertSame( $earlier_result, $result );
		$this->assertEquals(
			(object) array(
				'name'     => 'Name From Another Filter',
				'sections' => array( 'description' => '<p>Described.</p>' ),
				'rating'   => 100,
				'slug'     => 'my-plugin',
			),
			$earlier_result,
		);
	}

	/**
	 * @covers ::add_tabs
	 */
	public function test_a_field_an_earlier_filter_left_null_is_filled_in(): void {
		$earlier_result       = new stdClass();
		$earlier_result->name = null;

		$this->get_sut()->add_tabs( $earlier_result, 'plugin_information', (object) array( 'slug' => 'my-plugin' ) );

		$this->assertSame( 'My Plugin', $earlier_result->name );
	}
}
