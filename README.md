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