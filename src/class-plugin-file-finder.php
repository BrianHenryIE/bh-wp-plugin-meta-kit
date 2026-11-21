<?php
/**
 * Find a file in the plugin's directory whatever its capitalisation.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

/**
 * Plugins capitalise the same files differently: `readme.txt`, `README.txt`; `CHANGELOG.md`, `changelog.md`.
 */
class Plugin_File_Finder {
	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings The plugin whose directory is searched.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
	) {
	}

	/**
	 * Find a file in the root of the plugin's directory.
	 *
	 * The directory is listed, rather than each spelling tried, because filesystems differ on case-sensitivity.
	 *
	 * @param string $filename The file's name, in any capitalisation, e.g. `readme.txt`.
	 *
	 * @return ?string The file's path, with the capitalisation it has on disk, or null when the plugin has none.
	 */
	public function find( string $filename ): ?string {
		$plugin_directory = constant( 'WP_PLUGIN_DIR' ) . '/' . $this->settings->plugin_slug;

		if ( ! is_dir( $plugin_directory ) ) {
			return null;
		}

		foreach ( scandir( $plugin_directory ) ?: array() as $directory_entry ) {
			$file_path = $plugin_directory . '/' . $directory_entry;

			if ( 0 === strcasecmp( $filename, $directory_entry ) && is_file( $file_path ) && is_readable( $file_path ) ) {
				return $file_path;
			}
		}

		return null;
	}
}
