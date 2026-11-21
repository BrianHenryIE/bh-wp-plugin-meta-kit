<?php

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Model\Dependency;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use BrianHenryIE\Plugin_Meta_Kit\Unit_Testcase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use WP_Mock;
use WPTRT\AdminNotices\Notices;

/**
 * @coversDefaultClass \BrianHenryIE\Plugin_Meta_Kit\Admin\Dependencies_Notice
 */
class Dependencies_Notice_Unit_Test extends Unit_Testcase {
	protected const GMP_NOTICE_ID = 'my-plugin_dependency_gmp-php-extension';

	protected const NOTICE_OPTIONS = array(
		'type'          => 'warning',
		'capability'    => 'activate_plugins',
		'scope'         => 'user',
		'option_prefix' => 'bh_wp_plugin_meta_kit_notice_dismissed',
	);

	/**
	 * Prints the notices and handles their dismissal.
	 *
	 * @var Notices&MockInterface
	 */
	protected Notices $notices;

	protected function setUp(): void {
		parent::setUp();

		$this->notices = Mockery::mock( Notices::class );

		WP_Mock::passthruFunction( 'esc_html' );
		WP_Mock::passthruFunction( 'esc_url' );
		WP_Mock::passthruFunction( 'wp_kses' );
		WP_Mock::passthruFunction( 'sanitize_key' );

		WP_Mock::userFunction( 'sanitize_title' )
			->andReturnUsing( fn ( string $title ): string => strtolower( str_replace( ' ', '-', $title ) ) );

		WP_Mock::userFunction( 'admin_url' )
			->with( 'plugins.php' )
			->andReturn( 'https://example.org/wp-admin/plugins.php' );

		WP_Mock::userFunction( 'add_query_arg' )
			->andReturnUsing( fn ( array $query_args, string $url ): string => $url . '?' . http_build_query( $query_args ) );

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
	}

	/**
	 * @param Dependency[] $dependencies What the plugin needs.
	 */
	protected function get_sut( array $dependencies ): Dependencies_Notice {
		return new Dependencies_Notice(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: 'my-plugin',
				plugin_basename: 'my-plugin/my-plugin.php',
				plugin_name: 'My Plugin',
				dependencies: $dependencies,
			),
			$this->notices,
			$this->logger,
		);
	}

	/**
	 * The example the class was written for: a PHP extension the server might not have.
	 *
	 * @param bool $is_satisfied Whether the server has the extension.
	 */
	protected function get_gmp_dependency( bool $is_satisfied ): Dependency {
		return new Dependency(
			name: 'GMP PHP extension',
			is_satisfied: fn (): bool => $is_satisfied,
			description: 'It is needed for the calculations to derive Bitcoin payment addresses. Please <strong>contact your hosting provider for support</strong>.',
			url: 'https://www.php.net/manual/en/book.gmp.php',
		);
	}

	/**
	 * The state of the request: who is asking, how, and what they have dismissed.
	 *
	 * @param bool     $can_activate_plugins Whether the user can deactivate the plugin.
	 * @param bool     $is_ajax              Whether it is an AJAX request, as dismissing a notice is.
	 * @param string[] $dismissed_notice_ids The notices the user has dismissed.
	 * @param bool     $passthru_nonce_url   False when the test mocks `wp_nonce_url()` itself.
	 */
	protected function mock_request( bool $can_activate_plugins = true, bool $is_ajax = false, array $dismissed_notice_ids = array(), bool $passthru_nonce_url = true ): void {
		if ( $passthru_nonce_url ) {
			WP_Mock::passthruFunction( 'wp_nonce_url' );
		}

		WP_Mock::userFunction( 'current_user_can' )->with( 'activate_plugins' )->andReturn( $can_activate_plugins );
		WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( $is_ajax );
		WP_Mock::userFunction( 'get_user_meta' )
			->andReturnUsing(
				fn ( int $user_id, string $meta_key ): string => in_array(
					str_replace( 'bh_wp_plugin_meta_kit_notice_dismissed_', '', $meta_key ),
					$dismissed_notice_ids,
					true,
				) ? '1' : '',
			);
	}

	/**
	 * @covers ::add_notices
	 */
	public function test_no_notice_when_the_plugin_has_no_dependencies(): void {
		WP_Mock::userFunction( 'current_user_can' )->never();
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut( array() )->add_notices();
	}

	/**
	 * @covers ::add_notices
	 * @covers ::is_satisfied
	 */
	public function test_no_notice_when_the_dependency_is_satisfied(): void {
		$this->mock_request();
		$this->notices->shouldNotReceive( 'add' );

		$this->get_sut( array( $this->get_gmp_dependency( true ) ) )->add_notices();
	}

	/**
	 * Only users who can deactivate the plugin are told, and the check is not run for anyone else.
	 *
	 * @covers ::add_notices
	 */
	public function test_no_notice_for_users_who_cannot_deactivate_plugins(): void {
		$this->mock_request( can_activate_plugins: false );
		$this->notices->shouldNotReceive( 'add' );

		$was_checked = false;
		$dependency  = new Dependency(
			name: 'Something',
			is_satisfied: function () use ( &$was_checked ): bool {
				$was_checked = true;
				return false;
			},
		);

		$this->get_sut( array( $dependency ) )->add_notices();

		$this->assertFalse( $was_checked );
	}

	/**
	 * @covers ::add_notices
	 * @covers ::get_message
	 * @covers ::get_notice_id
	 * @covers ::get_deactivate_url
	 */
	public function test_missing_dependency_notice(): void {
		$this->mock_request();

		$this->notices->shouldReceive( 'add' )
			->once()
			->with(
				self::GMP_NOTICE_ID,
				'',
				'<strong>My Plugin</strong>: '
				. '<a href="https://www.php.net/manual/en/book.gmp.php">GMP PHP extension</a>'
				. ' is required but is not available on this site.'
				. ' It is needed for the calculations to derive Bitcoin payment addresses. Please <strong>contact your hosting provider for support</strong>.'
				. ' <a href="https://example.org/wp-admin/plugins.php?action=deactivate&plugin=my-plugin%2Fmy-plugin.php">Deactivate My Plugin</a>.',
				self::NOTICE_OPTIONS,
			);

		$this->get_sut( array( $this->get_gmp_dependency( false ) ) )->add_notices();
	}

	/**
	 * @covers ::get_deactivate_url
	 */
	public function test_deactivate_link_has_the_nonce_plugins_php_checks(): void {
		$this->mock_request( passthru_nonce_url: false );

		WP_Mock::userFunction( 'wp_nonce_url' )
			->once()
			->with(
				'https://example.org/wp-admin/plugins.php?action=deactivate&plugin=my-plugin%2Fmy-plugin.php',
				'deactivate-plugin_my-plugin/my-plugin.php',
			)
			->andReturn( 'https://example.org/wp-admin/plugins.php?action=deactivate&_wpnonce=abc123' );

		$this->notices->shouldReceive( 'add' )
			->once()
			->with(
				self::GMP_NOTICE_ID,
				'',
				Mockery::pattern( '#<a href="https://example\.org/wp-admin/plugins\.php\?action=deactivate&_wpnonce=abc123">Deactivate My Plugin</a>\.$#' ),
				self::NOTICE_OPTIONS,
			);

		$this->get_sut( array( $this->get_gmp_dependency( false ) ) )->add_notices();
	}

	/**
	 * @covers ::get_message
	 */
	public function test_dependency_without_a_url_or_description(): void {
		$this->mock_request();

		$this->notices->shouldReceive( 'add' )
			->once()
			->with(
				'my-plugin_dependency_woocommerce',
				'',
				Mockery::pattern( '#^<strong>My Plugin</strong>: WooCommerce is required but is not available on this site\. <a href=#' ),
				self::NOTICE_OPTIONS,
			);

		$dependency = new Dependency(
			name: 'WooCommerce',
			is_satisfied: fn (): bool => false,
		);

		$this->get_sut( array( $dependency ) )->add_notices();
	}

	/**
	 * @covers ::add_notices
	 */
	public function test_a_notice_is_added_for_each_missing_dependency_only(): void {
		$this->mock_request();

		$this->notices->shouldReceive( 'add' )
			->once()
			->with( 'my-plugin_dependency_first-missing', '', Mockery::type( 'string' ), self::NOTICE_OPTIONS );
		$this->notices->shouldReceive( 'add' )
			->once()
			->with( 'my-plugin_dependency_second-missing', '', Mockery::type( 'string' ), self::NOTICE_OPTIONS );

		$dependencies = array(
			new Dependency( name: 'First missing', is_satisfied: fn (): bool => false ),
			new Dependency( name: 'Satisfied', is_satisfied: fn (): bool => true ),
			new Dependency( name: 'Second missing', is_satisfied: fn (): bool => false ),
		);

		$this->get_sut( $dependencies )->add_notices();
	}

	/**
	 * A dismissed notice is not added, and its dependency is not checked.
	 *
	 * @covers ::add_notices
	 * @covers ::is_dismissed
	 * @covers ::get_dismissed_meta_key
	 */
	public function test_no_notice_when_the_user_has_dismissed_it(): void {
		$this->mock_request( dismissed_notice_ids: array( 'my-plugin_dependency_dismissed' ) );

		$this->notices->shouldReceive( 'add' )
			->once()
			->with( 'my-plugin_dependency_not-dismissed', '', Mockery::type( 'string' ), self::NOTICE_OPTIONS );

		$was_checked  = false;
		$dependencies = array(
			new Dependency(
				name: 'Dismissed',
				is_satisfied: function () use ( &$was_checked ): bool {
					$was_checked = true;
					return false;
				},
			),
			new Dependency( name: 'Not dismissed', is_satisfied: fn (): bool => false ),
		);

		$this->get_sut( $dependencies )->add_notices();

		$this->assertFalse( $was_checked );
	}

	/**
	 * Dismissing is an AJAX request which names the notice, so the notice must exist to handle it.
	 *
	 * @covers ::add_notices
	 */
	public function test_every_notice_is_added_on_ajax_requests_so_it_can_be_dismissed(): void {
		$this->mock_request( is_ajax: true );

		$this->notices->shouldReceive( 'add' )
			->once()
			->with( self::GMP_NOTICE_ID, '', Mockery::type( 'string' ), self::NOTICE_OPTIONS );

		$this->get_sut( array( $this->get_gmp_dependency( true ) ) )->add_notices();
	}

	/**
	 * The check is the plugin's own code; an error in it must not break every wp-admin page.
	 *
	 * @covers ::is_satisfied
	 */
	public function test_a_check_which_throws_adds_no_notice_is_logged_and_does_not_stop_the_others(): void {
		$this->mock_request();

		$this->notices->shouldReceive( 'add' )
			->once()
			->with( 'my-plugin_dependency_missing', '', Mockery::type( 'string' ), self::NOTICE_OPTIONS );

		$dependencies = array(
			new Dependency(
				name: 'Throws',
				is_satisfied: function (): bool {
					throw new RuntimeException( 'The check itself failed.' );
				},
			),
			new Dependency( name: 'Missing', is_satisfied: fn (): bool => false ),
		);

		$this->get_sut( $dependencies )->add_notices();

		$this->assertTrue( $this->logger->hasErrorThatContains( 'The check for dependency {dependency_name}' ) );
	}

	/**
	 * @covers ::reset_dismissed
	 * @covers ::get_dismissed_meta_key
	 */
	public function test_reset_dismissed_deletes_every_users_dismissal_and_logs_it(): void {
		WP_Mock::userFunction( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'bh_wp_plugin_meta_kit_notice_dismissed_' . self::GMP_NOTICE_ID, '', true )
			->andReturn( true );
		WP_Mock::userFunction( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'bh_wp_plugin_meta_kit_notice_dismissed_my-plugin_dependency_woocommerce', '', true )
			->andReturn( true );

		$dependencies = array(
			$this->get_gmp_dependency( false ),
			new Dependency( name: 'WooCommerce', is_satisfied: fn (): bool => false ),
		);

		$this->get_sut( $dependencies )->reset_dismissed();

		$this->assertTrue( $this->logger->hasInfoThatContains( 'Reset dismissed dependency notice {dependency_name}' ) );
	}
}
