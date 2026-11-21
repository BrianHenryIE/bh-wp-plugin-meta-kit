<?php

namespace BrianHenryIE\Plugin_Meta_Kit;

if ( ! defined('ABSPATH') ) {
	return;
}

add_action( 'admin_init', array ( Plugin_Meta_Kit::class, 'initialize' ) );
