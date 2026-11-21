/**
 * The library adds a "Settings" link to its plugin's row on plugins.php, pointing at the screen named in the
 * `Settings Link` header of the plugin's main file.
 *
 * Nothing is arranged or created: `development-plugin/development-plugin.php` has the header.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginBasename = 'development-plugin/development-plugin.php';

test.describe( 'plugins.php "Settings" link', () => {
	test.beforeEach( async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'plugins.php' );
	} );

	test( 'is the first action in the row of the plugin the library is bundled in', async ( { page } ) => {
		const rowActions = page.locator( `tr[data-plugin="${ pluginBasename }"] .row-actions > span` );

		await expect( rowActions.first() ).toHaveClass( 'settings' );
		await expect( rowActions.first().getByRole( 'link' ) ).toHaveText( 'Settings' );
		await expect( rowActions.nth( 1 ) ).toHaveClass( 'deactivate' );
	} );

	test( 'opens the settings screen named in the plugin header', async ( { page } ) => {
		await page
			.locator( `tr[data-plugin="${ pluginBasename }"] .row-actions .settings` )
			.getByRole( 'link', { name: 'Settings' } )
			.click();

		await expect( page ).toHaveURL( /\/wp-admin\/admin\.php\?page=development-plugin$/ );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Meta Kit Development' } ) ).toBeVisible();
	} );

	test( 'is not added to other plugins', async ( { page } ) => {
		await expect( page.locator( 'tr[data-plugin="hello.php"] .row-actions .settings' ) ).toHaveCount( 0 );
	} );
} );
