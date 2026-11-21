<?php
/**
 * Try to do a zero config install.
 *
 * Add to files autoloader.
 *
 * @package brianhenryie/bh-wp-plugin-meta-kit
 */

declare( strict_types = 1 );

namespace BrianHenryIE\Plugin_Meta_Kit;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// This `bootstrap.php` is in Composer's `files` autoloader, so needs to exist,
// but consumers of the library can delete the second file to skip automatically configuring,
// i.e. to explicitly configure the library, e.g. to supply a logger instance.
if ( ! file_exists( __DIR__ . '/bootstrap2.php' ) ) {
	return;
}

require_once __DIR__ . '/bootstrap2.php';
