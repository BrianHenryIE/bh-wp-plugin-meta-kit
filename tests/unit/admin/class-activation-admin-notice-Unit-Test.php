<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Plugin_Activation;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use Mockery;
use Mockery\MockInterface;
use WP_Mock;
use WPTRT\AdminNotices\Notices;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Activation_Admin_Notice
 */
class Activation_Admin_Notice_Unit_Test extends Unit_Testcase {
	protected const SETTINGS_URL = 'https://example.org/wp-admin/admin.php?page=my-plugin&tab=general';

	protected const NOTICE_ID = 'my-plugin_activation';

	protected const NOTICE_OPTIONS = array(
		'type'          => 'info',
		'capability'    => 'manage_options',
		'scope'         => 'user',
		'option_prefix' => 'bh_wp_plugin_meta_kit_notice_dismissed',
	);

	/**
	 * Prints the notice and handles its dismissal.
	 *
	 * @var Notices&MockInterface
	 */
	protected Notices $notices;

	/**
	 * When the plugin was activated and its settings visited.
	 *
	 * @var Plugin_Activation&MockInterface
	 */
	protected Plugin_Activation $plugin_activation;

	protected function setUp(): void {
		parent::setUp();

		$this->notices           = Mockery::mock( Notices::class );
		$this->plugin_activation = Mockery::mock( Plugin_Activation::class );

		WP_Mock::passthruFunction( 'esc_html' );
		WP_Mock::passthruFunction( 'esc_url' );
		WP_Mock::passthruFunction( 'sanitize_key' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_unslash' );

		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing( fn ( string $url, int $component ) => parse_url( $url, $component ) );

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['pagenow'] );
		$_GET = array();

		parent::tearDown();
	}

	protected function get_sut( string $settings_url = self::SETTINGS_URL ): Activation_Admin_Notice {
		return new Activation_Admin_Notice(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: 'my-plugin',
				plugin_basename: 'my-plugin/my-plugin.php',
				plugin_name: 'My Plugin',
				settings_url: $settings_url,
			),
			$this->plugin_activation,
			$this->notices,
			$this->logger,
		);
	}

	/**
	 * The state of the request and of the plugin.
	 *
	 * @param int                   $seconds_since_activation     How long ago the plugin was activated.
	 * @param ?int                  $seconds_since_settings_visit How long ago its settings were visited, or null for never.
	 * @param bool                  $can_manage_options           Whether the user can configure the plugin.
	 * @param bool                  $is_ajax                      Whether it is an AJAX request, as dismissing a notice is.
	 * @param bool                  $is_dismissed                 Whether the user has dismissed the notice.
	 * @param string                $pagenow                      The wp-admin file requested.
	 * @param array<string, string> $query_arguments              The request's query arguments.
	 */
	protected function mock_request(
		int $seconds_since_activation = 60,
		?int $seconds_since_settings_visit = null,
		bool $can_manage_options = true,
		bool $is_ajax = false,
		bool $is_dismissed = false,
		string $pagenow = 'index.php',
		array $query_arguments = array(),
	): void {
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->andReturn( $can_manage_options );
		WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( $is_ajax );
		WP_Mock::userFunction( 'get_user_meta' )
			->with( 1, 'bh_wp_plugin_meta_kit_notice_dismissed_' . self::NOTICE_ID, true )
			->andReturn( $is_dismissed ? '1' : '' );

		$this->plugin_activation->shouldReceive( 'get_activation_time' )
			->andReturn( time() - $seconds_since_activation );
		$this->plugin_activation->shouldReceive( 'get_settings_visit_time' )
			->andReturn( null === $seconds_since_settings_visit ? 0 : time() - $seconds_since_settings_visit );

		$GLOBALS['pagenow'] = $pagenow;
		$_GET               = $query_arguments;
	}

	protected function expect_notice(): void {
		$this->notices->shouldReceive( 'add' )
			->once()
			->with(
				self::NOTICE_ID,
				'',
				'<strong>My Plugin</strong> options can be configured in its <a href="' . self::SETTINGS_URL . '">settings</a>.',
				self::NOTICE_OPTIONS,
			);
	}

	/**
	 * @covers ::add_setup_notice
	 * @covers ::should_show
	 * @covers ::get_message
	 * @covers ::get_notice_id
	 */
	public function test_notice_is_added_after_a_recent_activation(): void {
		$this->mock_request();
		$this->expect_notice();

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * Without a settings screen there is nothing to invite the user to.
	 *
	 * @covers ::add_setup_notice
	 */
	public function test_no_notice_when_the_plugin_has_no_settings_url(): void {
		WP_Mock::userFunction( 'current_user_can' )->never();
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut( '' )->add_setup_notice();
	}

	/**
	 * @covers ::add_setup_notice
	 */
	public function test_no_notice_for_users_who_cannot_configure_the_plugin(): void {
		$this->mock_request( can_manage_options: false );
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @covers ::should_show
	 */
	public function test_no_notice_when_activated_more_than_a_week_ago(): void {
		$this->mock_request( seconds_since_activation: 8 * 24 * 60 * 60 );
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @covers ::should_show
	 */
	public function test_notice_is_added_when_activated_just_under_a_week_ago(): void {
		$this->mock_request( seconds_since_activation: 7 * 24 * 60 * 60 - 60 );
		$this->expect_notice();

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * An activation which was never recorded reads as time 0.
	 *
	 * @covers ::should_show
	 */
	public function test_no_notice_when_no_activation_was_recorded(): void {
		$this->mock_request( seconds_since_activation: time() );
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @covers ::should_show
	 */
	public function test_no_notice_when_settings_were_visited_since_activation(): void {
		$this->mock_request(
			seconds_since_activation: 3600,
			seconds_since_settings_visit: 60,
		);
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * Deactivating and activating the plugin again starts the week over.
	 *
	 * @covers ::should_show
	 */
	public function test_notice_is_added_when_settings_were_only_visited_before_activation(): void {
		$this->mock_request(
			seconds_since_activation: 60,
			seconds_since_settings_visit: 3600,
		);
		$this->expect_notice();

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @covers ::should_show
	 */
	public function test_no_notice_when_the_user_has_dismissed_it(): void {
		$this->mock_request( is_dismissed: true );
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @covers ::should_show
	 * @covers ::is_settings_screen
	 */
	public function test_no_notice_on_the_settings_screen_and_the_visit_is_recorded(): void {
		$this->mock_request(
			pagenow: 'admin.php',
			query_arguments: array(
				'page' => 'my-plugin',
				'tab'  => 'general',
			),
		);
		$this->notices->shouldNotReceive( 'add' );
		$this->plugin_activation->shouldReceive( 'record_settings_visit' )->once();

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @return array<string, array{0: string, 1: array<string, string>}>
	 */
	public static function other_screens(): array {
		return array(
			'another page of the same file' => array(
				'admin.php',
				array(
					'page' => 'another-plugin',
					'tab'  => 'general',
				),
			),
			'another tab of the same page' => array(
				'admin.php',
				array(
					'page' => 'my-plugin',
					'tab'  => 'advanced',
				),
			),
			'the page without the tab'         => array( 'admin.php', array( 'page' => 'my-plugin' ) ),
			'the same arguments, another file' => array(
				'options-general.php',
				array(
					'page' => 'my-plugin',
					'tab'  => 'general',
				),
			),
		);
	}

	/**
	 * @covers ::is_settings_screen
	 *
	 * @dataProvider other_screens
	 *
	 * @param string                $pagenow         The wp-admin file requested.
	 * @param array<string, string> $query_arguments The request's query arguments.
	 */
	public function test_other_screens_are_not_the_settings_screen( string $pagenow, array $query_arguments ): void {
		$this->mock_request( pagenow: $pagenow, query_arguments: $query_arguments );
		$this->expect_notice();
		$this->plugin_activation->shouldNotReceive( 'record_settings_visit' );

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * A settings screen may be reached with more arguments than its URL has, e.g. `settings-updated=true`.
	 *
	 * @covers ::is_settings_screen
	 */
	public function test_extra_query_arguments_are_still_the_settings_screen(): void {
		$this->mock_request(
			pagenow: 'admin.php',
			query_arguments: array(
				'page'             => 'my-plugin',
				'tab'              => 'general',
				'settings-updated' => 'true',
			),
		);
		$this->notices->shouldNotReceive( 'add' );
		$this->plugin_activation->shouldReceive( 'record_settings_visit' )->once();

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * Dismissing is an AJAX request which names the notice, so the notice must exist to handle it.
	 *
	 * @covers ::add_setup_notice
	 */
	public function test_notice_is_added_on_ajax_requests_so_it_can_be_dismissed(): void {
		$this->mock_request(
			seconds_since_activation: 30 * 24 * 60 * 60,
			is_ajax: true,
		);
		$this->expect_notice();

		$this->get_sut()->add_setup_notice();
	}

	/**
	 * @covers ::reset_dismissed
	 * @covers ::get_dismissed_meta_key
	 */
	public function test_reset_dismissed_deletes_every_users_dismissal_and_logs_it(): void {
		WP_Mock::userFunction( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'bh_wp_plugin_meta_kit_notice_dismissed_' . self::NOTICE_ID, '', true )
			->andReturn( true );

		$this->get_sut()->reset_dismissed();

		$this->assertTrue( $this->logger->hasInfoThatContains( 'Reset dismissed activation notice of {plugin_slug}' ) );
	}
}
