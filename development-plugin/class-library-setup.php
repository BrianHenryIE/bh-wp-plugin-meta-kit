<?php
/**
 * Configures the library for the development plugin, in place of the library's zero-config setup.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Development_Plugin;

use BrianHenryIE\Plugin_Meta_Kit\Model\Dependency;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;

use function BrianHenryIE\Plugin_Meta_Kit\make_settings;

/**
 * Shows how a plugin passes its own settings, here a dependency, on top of what the library infers.
 */
class Library_Setup {
	/** When this option is true the example dependency is reported missing, so its admin notice can be seen and tested. */
	public const MISSING_DEPENDENCY_OPTION = 'development_plugin_missing_dependency';

	/**
	 * Add the hooks which replace the library's zero-config setup.
	 */
	public function register_hooks(): void {
		remove_action(
			'admin_init',
			'\\BrianHenryIE\\Plugin_Meta_Kit\\initialize_plugin_meta_kit',
		);
		add_action( 'admin_init', array( $this, 'initialize_library' ) );

		add_action( 'init', array( $this, 'register_missing_dependency_setting' ) );
	}

	/**
	 * Start the library with the settings it infers, plus the example dependency.
	 *
	 * @hooked admin_init
	 * @see self::register_hooks()
	 */
	public function initialize_library(): void {
		$settings = $this->get_settings();

		if ( null === $settings ) {
			return;
		}

		Plugin_Meta_Kit::initialize( $settings );
	}

	/**
	 * The settings the library infers, plus the example dependency.
	 *
	 * @return ?Plugin_Meta_Kit_Settings Null when the library cannot tell which plugin it is in.
	 */
	public function get_settings(): ?Plugin_Meta_Kit_Settings {
		$inferred_settings = make_settings();

		if ( null === $inferred_settings ) {
			return null;
		}

		return new Plugin_Meta_Kit_Settings(
			plugin_slug: $inferred_settings->plugin_slug,
			plugin_basename: $inferred_settings->plugin_basename,
			plugin_name: $inferred_settings->plugin_name,
			dependencies: array( $this->get_example_dependency() ),
		);
	}

	/**
	 * Show every dismissed notice again, to every user.
	 */
	public function reset_dismissed_notices(): void {
		$settings = $this->get_settings();

		if ( null === $settings ) {
			return;
		}

		( new Plugin_Meta_Kit( $settings ) )->reset_dismissed_notices();
	}

	/**
	 * A PHP extension as the example. A real plugin's check would be `fn (): bool => extension_loaded( 'gmp' )`;
	 * here it reads an option, so the notice can be switched on and off.
	 */
	protected function get_example_dependency(): Dependency {
		return new Dependency(
			name: 'GMP (GNU Multiple Precision Arithmetic Library) PHP extension',
			is_satisfied: fn (): bool => true !== (bool) get_option( self::MISSING_DEPENDENCY_OPTION, false ),
			description: 'It is needed for the calculations to derive Bitcoin payment addresses. Please <strong>contact your hosting provider for support</strong>.',
			url: 'https://www.php.net/manual/en/book.gmp.php',
		);
	}

	/**
	 * Expose the option in the core settings REST endpoint, which is how the Playwright tests set it.
	 *
	 * @hooked init
	 * @see self::register_hooks()
	 */
	public function register_missing_dependency_setting(): void {
		register_setting(
			'development_plugin',
			self::MISSING_DEPENDENCY_OPTION,
			array(
				'type'              => 'boolean',
				'description'       => 'Report the example dependency of the development plugin as missing.',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);
	}
}
