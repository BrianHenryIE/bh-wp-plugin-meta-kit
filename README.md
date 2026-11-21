# BH Plugin Meta Kit

A library to help you manage your plugin on plugins.php

* Add Settings link on plugins.php
* Add `View details` modal link on plugins.php (absent on plugins not hosted on .org)
* Add Settings link to update.php
* Add temporary admin notice on plugin activation

??

Add main settings link which appears in the three main spots.

API to add more links on plugins.php.
Auto-scan menus after plugins_loaded to add links to plugins.php and update.php.
Add activation hook and record last activation time.
Possible to detect via menu registered page that the settings page has ever been visited?

does admin_init run on plugin activation, e.g. during wp cli plugin activation. 
i.e. can we hide everything behind it for performance 


How to handle conditional menu items? (e.g. settings only appears when WooCommerce is active.)

screenshots tab
GitHub plugins.php link from plugin headers

GitHub Action as a step to confirm readme has been updated (since last release -- version and changelog)


```bash
composer require brianhenryie/bh-wp-plugin-meta-kit
```

```php
add_action( 'admin_init', array ( Plugin_Meta_Kit::class, 'initialize' ) );
```

## Dependencies notice

Tell the library what your plugin needs, and users who can deactivate plugins see a dismissible admin notice for each thing the site is missing:

```php
use BrianHenryIE\Plugin_Meta_Kit\Model\Dependency;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit;
use BrianHenryIE\Plugin_Meta_Kit\Plugin_Meta_Kit_Settings;

// Replace the zero-config setup with your own settings.
remove_action( 'admin_init', '\\BrianHenryIE\\Plugin_Meta_Kit\\initialize_plugin_meta_kit' );

add_action(
	'admin_init',
	function () {
		Plugin_Meta_Kit::initialize(
			new Plugin_Meta_Kit_Settings(
				plugin_slug: 'my-plugin',
				plugin_basename: 'my-plugin/my-plugin.php',
				plugin_name: 'My Plugin',
				dependencies: array(
					new Dependency(
						name: 'GMP (GNU Multiple Precision Arithmetic Library) PHP extension',
						is_satisfied: fn (): bool => extension_loaded( 'gmp' ),
						description: 'It is needed for the calculations to derive Bitcoin payment addresses. Please <strong>contact your hosting provider for support</strong>.',
						url: 'https://www.php.net/manual/en/book.gmp.php',
					),
				),
			)
		);
	}
);
```

The check runs on every wp-admin page for those users, so keep it cheap. A dismissal is remembered per user; `Plugin_Meta_Kit::get_instance()->reset_dismissed_notices()` shows the notices again to everyone.


## Settings link

Add a `Settings Link` header to your plugin's main file and the zero-config setup adds a "Settings" link to the plugin's row on plugins.php. It is relative to wp-admin, or a full URL:

```php
/**
 * Plugin Name:   My Plugin
 * Settings Link: options-general.php?page=my-plugin
 */
```

When you build `Plugin_Meta_Kit_Settings` yourself, pass the full URL as `settings_url`.
