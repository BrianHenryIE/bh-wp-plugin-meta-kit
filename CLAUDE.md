Shared rules for all BrianHenryIE projects are in `~/.claude/CLAUDE.md`; the rules here are this project's.

## The project

* This is a library, not a plugin: it ships inside another plugin at `wp-content/plugins/{slug}/vendor/brianhenryie/bh-wp-plugin-meta-kit`, and works out which plugin that is from its own path
* The code is in `includes`; `includes/admin` is wp-admin UI only
* `development-plugin` is the plugin the library runs for in wp-env. Keep it in sync with changes to the library
* wp-env runs on port 8884, MySQL on 33064 (`npx wp-env start`)
* wp-env maps the project root to `wp-content/uploads/development-plugin`, and `development-plugin/class-mappings.php` tells WordPress that is the plugin's directory. That hides path and bootstrap bugs a real `vendor` install would show, so do not treat a pass in wp-env as proof `plugin_basename()` logic is right
* The default `php` on this machine is 8.0 and the library needs 8.2: run Composer, PHPStan, Codeception and rector with `/opt/homebrew/opt/php@8.2/bin/php`, and php-cs-fixer with `/opt/homebrew/opt/php@8.4/bin/php`

## PHP

* Write `declare( strict_types = 1 );` as php-cs-fixer formats it; add it when editing a file which lacks it
* PHP `@package` is `brianhenryie/bh-wp-plugin-meta-kit`. Don't use `@subpackage` or `@since`
* A `@param` tag has a description; php-cs-fixer removes one which only repeats the native type, and phpcs then reports the parameter as undocumented
* Don't add a property's type in PhpDoc when it is clear from the PHP code
* Don't use `if ( ! defined( 'ABSPATH' ) ) {` when the file only contains a class
* Don't use `if ( class_exists( '*' ) )` around classes
* The library runs inside someone else's plugin: catch exceptions where it meets WordPress (hooked methods, `bootstrap2.php`) and log them, so it never takes down wp-admin
* Prefer PSR `LoggerInterface` for logging, almost never `error_log`
* A new constructor property on a class consumers build, e.g. `Plugin_Meta_Kit_Settings`, needs a default, so existing callers keep working
* Action, filter and option names use underscores, never hyphens; convert the plugin slug when a name is built from it. WordPress core hook names are the only exception
* PHP must not print `<script>` tags: enqueue a file and pass data with `wp_add_inline_script( ..., 'before' )` (`composer no-inline-scripts` checks)
* Prefer under 10 files per directory, then use categorised subdirectories

## Checks

* `composer cs-fix` runs php-cs-fixer, phpcbf, PHPStan and phpcs
* PHPStan runs at level 10. New and changed code must pass it without new `phpstan-baseline.neon` entries or `@phpstan-ignore` comments; the baseline holds only errors which predate level 10, and is regenerated only to remove fixed ones
* A `phpcs:ignore` names the sniff it suppresses and gives the reason after ` -- `
* Write unit or wpunit tests covering every new method
* In Playwright tests, set fixed settings in `tests/wp-env/initialize-internal.sh`; add a REST endpoint in `development-plugin/rest` only when a test must change something while it runs
* Do not commit generated files (e.g. `.pot`, built JavaScript)

* Auto-commit after implementation unless asked not to 
