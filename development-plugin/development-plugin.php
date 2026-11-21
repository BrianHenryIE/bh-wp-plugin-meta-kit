<?php
/**
 * Plugin Name:   BH Plugin Meta Kit Development/Test/Demo plugin
 * Description:   A plugin to showcase the library and add test helpers.
 * Version:       1.0.0
 * Author:        BrianHenryIE
 * Author URI:    https://bhwp.ie
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare(strict_types=1);

namespace BrianHenryIE\Plugin_Meta_Kit\Development_Plugin;

/**
 * The library is the project root, not a Composer dependency of this plugin, so there is no
 * `development-plugin/vendor`. wp-env maps the project root to `wp-content/uploads/development-plugin`, which is
 * where the library's own `vendor/autoload.php` (and its `src`) are inside the container.
 *
 * @see .wp-env.json
 */
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';

	// When loading via odd wp-env mappings.
} elseif ( file_exists( ABSPATH . '/wp-content/uploads/development-plugin/vendor/autoload.php' ) ) {
	require_once ABSPATH . '/wp-content/uploads/development-plugin/vendor/autoload.php';
}
