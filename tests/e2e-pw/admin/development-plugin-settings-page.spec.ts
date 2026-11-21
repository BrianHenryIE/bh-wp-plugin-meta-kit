/**
 * The development plugin's settings screen is reached from the WordPress admin menu.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/ui/login';

test.describe( 'development plugin settings screen', () => {
	test( 'is a top-level item of the admin menu', async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'index.php' );

		const menuItem = page.locator( '#adminmenu > li > a' ).filter( { hasText: 'Meta Kit Development' } );

		await expect( menuItem ).toBeVisible();

		await menuItem.click();

		await expect( page ).toHaveURL( /\/wp-admin\/admin\.php\?page=development-plugin$/ );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Meta Kit Development' } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Reset dismissed notices' } ) ).toBeVisible();
	} );
} );
