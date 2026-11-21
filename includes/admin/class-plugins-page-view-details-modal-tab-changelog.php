<?php
/**
 * Fills the Changelog tab of the "View details" modal from the plugin's `CHANGELOG.md`.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Admin;

use BrianHenryIE\Plugin_Meta_Kit\Changelog_Merger;
use BrianHenryIE\Plugin_Meta_Kit\Changelog_Parser;
use BrianHenryIE\Plugin_Meta_Kit\Model\Changelog_Entry;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_File_Finder;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;
use stdClass;
use Syntatis\WPPluginReadMeParser\Markdown;

/**
 * When the plugin has a `CHANGELOG.md`, the Changelog tab shows it merged with the changelog in `readme.txt`:
 * one file is often updated and the other forgotten. Without a `CHANGELOG.md` the readme's changelog is left as it is.
 */
class Plugins_Page_View_Details_Modal_Tab_Changelog {
	use Plugin_Information_Fields;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Meta_Kit_Settings $settings           The plugin.
	 * @param Plugin_File_Finder       $plugin_file_finder Finds `CHANGELOG.md` and `readme.txt` in the plugin's directory.
	 * @param Changelog_Parser         $changelog_parser   Splits a changelog into its versions.
	 * @param Changelog_Merger         $changelog_merger   Combines the two files' entries for each version.
	 */
	public function __construct(
		protected Plugin_Meta_Kit_Settings $settings,
		protected Plugin_File_Finder $plugin_file_finder,
		protected Changelog_Parser $changelog_parser,
		protected Changelog_Merger $changelog_merger,
	) {
	}

	/**
	 * Set the plugin information's `changelog` section.
	 *
	 * Runs after {@see Plugins_Page_View_Details_Modal_Readme::add_tabs()}, whose readme changelog it replaces.
	 *
	 * @hooked plugins_api
	 * @see Plugin_Meta_Kit::view_details_modal()
	 *
	 * @param false|object|array<mixed> $result The result object or array. Default false.
	 * @param string                    $action The type of information being requested from the Plugin Installation API.
	 * @param object                    $args   Plugin API arguments.
	 *
	 * @return false|object|array<mixed>
	 */
	public function add_changelog_tab( $result, $action, $args ) {
		$requested_slug = get_object_vars( $args )['slug'] ?? null;

		if ( $requested_slug !== $this->settings->plugin_slug ) {
			return $result;
		}

		$changelog_entries = $this->get_changelog_entries();

		if ( array() === $changelog_entries ) {
			return $result;
		}

		if ( ! is_object( $result ) ) {
			$result = is_array( $result ) ? (object) $result : new stdClass();
		}

		$sections = get_object_vars( $result )['sections'] ?? array();
		$sections = is_array( $sections ) ? $sections : array();

		$sections['changelog'] = $this->get_changelog_html( $changelog_entries );

		return $this->set_fields( $result, array( 'sections' => $sections ) );
	}

	/**
	 * The entries of `CHANGELOG.md`, merged with those of the readme's changelog.
	 *
	 * @return array<string, Changelog_Entry> By version, newest first. Empty when the plugin has no `CHANGELOG.md`.
	 */
	protected function get_changelog_entries(): array {
		$changelog_contents = $this->get_file_contents( 'CHANGELOG.md' );

		if ( null === $changelog_contents ) {
			return array();
		}

		$changelog_entries = $this->changelog_parser->parse( $changelog_contents );

		$readme_contents = $this->get_file_contents( 'readme.txt' );
		$readme_entries  = null === $readme_contents ? array() : $this->changelog_parser->parse_readme( $readme_contents );

		return $this->changelog_merger->merge( $changelog_entries, $readme_entries );
	}

	/**
	 * Read a file in the root of the plugin's directory.
	 *
	 * @param string $filename The file's name, in any capitalisation.
	 *
	 * @return ?string Its contents, or null when it does not exist or is empty.
	 */
	protected function get_file_contents( string $filename ): ?string {
		$file_path = $this->plugin_file_finder->find( $filename );

		if ( null === $file_path ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file; WP_Filesystem is for writing.
		$contents = file_get_contents( $file_path );

		return false === $contents || '' === trim( $contents ) ? null : $contents;
	}

	/**
	 * Render the entries: a heading for each version, then its changes.
	 *
	 * @param array<string, Changelog_Entry> $changelog_entries The entries, in the order to print them.
	 */
	protected function get_changelog_html( array $changelog_entries ): string {
		$markdown = array();

		foreach ( $changelog_entries as $changelog_entry ) {
			$heading = '#### ' . $changelog_entry->version;

			if ( '' !== $changelog_entry->date ) {
				$heading .= ' – ' . $changelog_entry->date;
			}

			$markdown[] = $heading;
			$markdown[] = '';

			foreach ( $changelog_entry->lines as $line ) {
				// A line which is not a list item, e.g. `**Added**`, is its own paragraph.
				if ( ! str_starts_with( $line, '* ' ) ) {
					$markdown[] = '';
					$markdown[] = $line;
					$markdown[] = '';
					continue;
				}

				$markdown[] = $line;
			}

			$markdown[] = '';
		}

		return ( new Markdown() )->transform( implode( "\n", $markdown ) );
	}
}
