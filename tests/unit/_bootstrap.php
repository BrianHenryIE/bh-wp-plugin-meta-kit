<?php
/**
 * PHPUnit bootstrap file for WP_Mock.
 *
 * @package           brianhenryie/bh-wp-plugin-meta-kit
 */

// NB: Patchwork breaks JsonMapper tests.
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
