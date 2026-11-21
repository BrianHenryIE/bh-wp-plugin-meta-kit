<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use Mockery;
use stdClass;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Plugin_Activation
 */
class Plugin_Activation_Unit_Test extends Unit_Testcase {
	protected function get_sut(): Plugin_Activation {
		return new Plugin_Activation( 'my-plugin', $this->logger );
	}

	/**
	 * @covers ::get_activation_time_option_name
	 * @covers ::get_settings_visit_time_option_name
	 */
	public function test_option_names_use_underscores(): void {
		$sut = $this->get_sut();

		$this->assertSame(
			'my_plugin_bhpmk_last_activated',
			$sut->get_activation_time_option_name(),
		);
		$this->assertSame(
			'my_plugin_bhpmk_settings_visited',
			$sut->get_settings_visit_time_option_name(),
		);
	}

	/**
	 * @covers ::record_activation
	 * @covers ::save_time
	 */
	public function test_record_activation_saves_the_time_and_logs_it(): void {
		$before = time();

		WP_Mock::userFunction( 'update_option' )
			->once()
			->with(
				'my_plugin_bhpmk_last_activated',
				Mockery::on( fn ( $time ): bool => is_int( $time ) && $time >= $before && $time <= time() ),
				false,
			)
			->andReturn( true );

		$this->get_sut()->record_activation();

		$this->assertTrue( $this->logger->hasInfoThatContains( 'Saved {option_name} for {plugin_slug} as {time}.' ) );
	}

	/**
	 * @covers ::record_settings_visit
	 * @covers ::save_time
	 */
	public function test_record_settings_visit_saves_the_time_and_logs_it(): void {
		$before = time();

		WP_Mock::userFunction( 'update_option' )
			->once()
			->with(
				'my_plugin_bhpmk_settings_visited',
				Mockery::on( fn ( $time ): bool => is_int( $time ) && $time >= $before && $time <= time() ),
				false,
			)
			->andReturn( true );

		$this->get_sut()->record_settings_visit();

		$this->assertTrue( $this->logger->hasInfoRecords() );
	}

	/**
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public static function saved_times(): array {
		return array(
			'an integer'              => array( 1767225600, 1767225600 ),
			'as the database returns' => array( '1767225600', 1767225600 ),
			'never saved'             => array( 0, 0 ),
			'not a time'              => array( 'yesterday', 0 ),
			'an object'               => array( new stdClass(), 0 ),
		);
	}

	/**
	 * @covers ::get_activation_time
	 * @covers ::get_time
	 *
	 * @dataProvider saved_times
	 *
	 * @param mixed $option_value What the option holds.
	 * @param int   $expected     The time it is read as.
	 */
	public function test_get_activation_time( mixed $option_value, int $expected ): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'my_plugin_bhpmk_last_activated', 0 )
			->andReturn( $option_value );

		$this->assertSame( $expected, $this->get_sut()->get_activation_time() );
	}

	/**
	 * @covers ::get_settings_visit_time
	 */
	public function test_get_settings_visit_time(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'my_plugin_bhpmk_settings_visited', 0 )
			->andReturn( '1767225600' );

		$this->assertSame( 1767225600, $this->get_sut()->get_settings_visit_time() );
	}
}
