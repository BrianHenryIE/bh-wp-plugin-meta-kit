<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use lucatume\WPBrowser\TestCase\WPTestCase;
use ReflectionMethod;

/**
 * The readme parser calls WordPress functions, so parsing a real readme is tested here, not in the unit suite.
 *
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Plugins_Page_View_Details_Modal_Readme
 */
class Plugins_Page_View_Details_Modal_Readme_WPUnit_Test extends WPTestCase {
	/**
	 * @covers ::get_readme
	 */
	public function test_development_plugin_readme_is_parsed(): void {
		$settings = new Plugin_Meta_Kit_Settings(
			plugin_slug: 'development-plugin',
			plugin_basename: 'development-plugin/development-plugin.php',
			plugin_name: 'BH Plugin Meta Kit Development/Test/Demo plugin',
		);

		$sut = new Plugins_Page_View_Details_Modal_Readme( $settings );

		$get_readme = new ReflectionMethod( $sut, 'get_readme' );

		$result = $get_readme->invoke( $sut, dirname( __DIR__, 3 ) . '/development-plugin/readme.txt' );

		$this->assertIsArray( $result );
		$this->assertSame( '6.2', $result['requires'] );
		$this->assertSame( '6.5', $result['tested'] );
		$this->assertSame( '8.2', $result['requires_php'] );
		$this->assertSame( '1.0.0', $result['stable_tag'] );

		$sections = $result['sections'];
		$this->assertIsArray( $sections );
		$this->assertIsString( $sections['description'] );
		$this->assertStringContainsString(
			'BH Plugin Meta Kit',
			$sections['description'],
		);
		$this->assertIsString( $sections['faq'] );
		$this->assertStringContainsString(
			'Should I install this on a live site?',
			$sections['faq'],
		);
		$this->assertIsString( $sections['changelog'] );
		$this->assertStringContainsString(
			'First version of the development plugin.',
			$sections['changelog'],
		);
	}
}
