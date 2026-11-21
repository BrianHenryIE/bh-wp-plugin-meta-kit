<?php
/**
 * Remembers when the plugin was last activated, and when its settings screen was last visited.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

use BrianHenryIE\Plugin_Meta_Kit\Admin\Activation_Admin_Notice;
use Psr\Log\LoggerInterface;

/**
 * The two times {@see Activation_Admin_Notice} needs, each saved in an option as a Unix timestamp.
 */
class Plugin_Activation {
	/**
	 * Constructor.
	 *
	 * @param string          $plugin_slug The plugin's directory name, e.g. `my-plugin`.
	 * @param LoggerInterface $logger      Records when the times are saved.
	 */
	public function __construct(
		protected string $plugin_slug,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Save now as the time the plugin was last activated.
	 *
	 * @hooked activate_{basename}
	 * @see Plugin_Meta_Kit::activation()
	 */
	public function record_activation(): void {
		$this->save_time( $this->get_activation_time_option_name() );
	}

	/**
	 * When the plugin was last activated.
	 *
	 * @return int A Unix timestamp, or 0 when no activation has been recorded.
	 */
	public function get_activation_time(): int {
		return $this->get_time( $this->get_activation_time_option_name() );
	}

	/**
	 * Save now as the time the plugin's settings screen was last visited.
	 */
	public function record_settings_visit(): void {
		$this->save_time( $this->get_settings_visit_time_option_name() );
	}

	/**
	 * When the plugin's settings screen was last visited.
	 *
	 * @return int A Unix timestamp, or 0 when no visit has been recorded.
	 */
	public function get_settings_visit_time(): int {
		return $this->get_time( $this->get_settings_visit_time_option_name() );
	}

	/**
	 * The option the activation time is saved in, e.g. `my_plugin_bhpmk_last_activated`.
	 */
	public function get_activation_time_option_name(): string {
		return str_replace( '-', '_', $this->plugin_slug ) . '_bhpmk_last_activated';
	}

	/**
	 * The option the settings visit time is saved in, e.g. `my_plugin_bhpmk_settings_visited`.
	 */
	public function get_settings_visit_time_option_name(): string {
		return str_replace( '-', '_', $this->plugin_slug ) . '_bhpmk_settings_visited';
	}

	/**
	 * Save the current time, in UTC, in an option.
	 *
	 * @param string $option_name The option to save it in.
	 */
	protected function save_time( string $option_name ): void {
		$time = time();

		update_option( $option_name, $time, false );

		$this->logger->info(
			'Saved {option_name} for {plugin_slug} as {time}.',
			array(
				'option_name' => $option_name,
				'plugin_slug' => $this->plugin_slug,
				'time'        => $time,
			),
		);
	}

	/**
	 * Read a time saved in an option.
	 *
	 * @param string $option_name The option it is saved in.
	 *
	 * @return int A Unix timestamp, or 0 when the option is not set.
	 */
	protected function get_time( string $option_name ): int {
		$time = get_option( $option_name, 0 );

		return is_numeric( $time ) ? (int) $time : 0;
	}
}
