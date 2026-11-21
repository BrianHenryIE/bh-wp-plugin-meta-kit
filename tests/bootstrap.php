<?php
/**
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

use Alley_Interactive\Autoloader\Autoloader;
use lucatume\WPBrowser\Module\WPLoader;

$GLOBALS['project_root_dir']   = $project_root_dir  = dirname( __DIR__, 1 );
$GLOBALS['plugin_root_dir']    = $plugin_root_dir   = $project_root_dir;
$GLOBALS['plugin_slug']        = $plugin_slug       = basename( $project_root_dir );
$GLOBALS['plugin_file_php']    = $plugin_file_php   = $plugin_slug . '.php';
$GLOBALS['plugin_path_php']    = $plugin_root_dir . '/' . $plugin_file_php;
$GLOBALS['plugin_basename']    = $plugin_slug . '/' . $plugin_file_php;
$GLOBALS['wordpress_root_dir'] = $project_root_dir . '/wordpress';

Autoloader::generate(
	'BrianHenryIE\\Plugin_Meta_Kit',
	__DIR__ . '/unit',
)->register();

Autoloader::generate(
	'BrianHenryIE\\Plugin_Meta_Kit',
	__DIR__ . '/wpunit',
)->register();

// If there is a secrets file, load it here.
if ( file_exists( $project_root_dir . '/.env.secret' ) ) {
	$dotenv = Dotenv\Dotenv::createImmutable( $project_root_dir, '.env.secret' );
	$dotenv->load();
}

/**
 * Fix "sh: php: command not found" when running wpunit tests in PhpStorm.
 *
 * @see WPLoader::includeCorePHPUniteSuiteBootstrapFile()
 */
$command_line_arguments = is_array( $GLOBALS['argv'] ?? null ) ? $GLOBALS['argv'] : array();
$is_phpstorm            = array_reduce(
	$command_line_arguments,
	fn ( bool $carry, $argument ): bool => $carry || ( is_string( $argument ) && str_contains( $argument, 'PhpStorm' ) ),
	false,
);
if ( $is_phpstorm ) {
	define( 'WP_PHP_BINARY', PHP_BINARY );
}
