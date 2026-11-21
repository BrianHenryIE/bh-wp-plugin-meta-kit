<?php
/**
 * Plugin Name:   BH Plugin Meta Kit Development/Test/Demo plugin
 * Plugin URI:    https://github.com/BrianHenryIE/bh-wp-plugin-meta-kit
 * Description:   A plugin to showcase the library and add test helpers.
 * Version:       1.0.0
 * Author:        BrianHenryIE
 * Author URI:    https://bhwp.ie
 * Settings Link: admin.php?page=development-plugin
 * GitHub Link:   https://github.com/BrianHenryIE/bh-wp-plugin-meta-kit
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit\Development_Plugin;

require_once __DIR__ . '/class-mappings.php';

( new Mappings() )->register_hooks();

/**
 * The library is the project root, not a Composer dependency of this plugin, so there is no
 * `development-plugin/vendor`. wp-env maps the project root to `wp-content/uploads/development-plugin`, which is
 * where the library's own `vendor/autoload.php` (and its `includes`) are inside the container.
 *
 * @see .wp-env.json
 */
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';

	// When loading via odd wp-env mappings.
} elseif ( file_exists( ABSPATH . '/wp-content/uploads/development-plugin/vendor/autoload.php' ) ) {
	require_once ABSPATH . '/wp-content/uploads/development-plugin/vendor/autoload.php';
}

require_once __DIR__ . '/class-library-setup.php';
require_once __DIR__ . '/ui/class-settings-page.php';
require_once __DIR__ . '/rest/class-dismissed-notices-controller.php';

$library_setup = new Library_Setup();
$library_setup->register_hooks();

( new UI\Settings_Page( $library_setup ) )->register_hooks();
( new Rest\Dismissed_Notices_Controller( $library_setup ) )->register_hooks();
