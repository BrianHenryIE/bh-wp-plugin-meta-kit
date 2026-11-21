<?php

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\ColorLogger\ColorLogger;
use Codeception\Test\Unit;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use WP_Mock;

class Unit_Testcase extends Unit {
	/**
	 * PSR logger with convenience functions for assertions. Prints logs in the console as they are generated.
	 *
	 * @var LoggerInterface&TestLogger&ColorLogger
	 */
	protected ColorLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction(
			'wp_json_encode',
			array(
				'return' => json_encode( ... ),
			),
		);

		$this->logger = new ColorLogger();
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();

		if ( function_exists( '\Patchwork\restoreAll' ) ) {
			\Patchwork\restoreAll();
		}
	}
}
