<?php

namespace BrianHenryIE\Plugin_Meta_Kit;

interface Plugin_Meta_Kit_Settings_Interface {
	public function get_plugin_slug(): string;
	public function get_plugin_basename(): string;

	public function get_plugin_name(): string;
}
