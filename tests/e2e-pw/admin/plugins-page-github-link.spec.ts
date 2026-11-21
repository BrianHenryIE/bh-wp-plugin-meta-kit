/**
 * The library adds a "View on GitHub" link to its plugin's row on plugins.php, pointing at the repository named in
 * the `GitHub Link` header of the plugin's main file.
 *
 * Nothing is arranged or created: `development-plugin/development-plugin.php` has the header.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginBasename = 'development-plugin/development-plugin.php';

test.describe( 'plugins.php "View on GitHub" link', () => {
	test.beforeEach( async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'plugins.php' );
	} );

	test( 'is in the row of the plugin the library is bundled in, after its version and author', async ( { page } ) => {
		const rowMeta = page.locator( `tr[data-plugin="${ pluginBasename }"] .plugin-version-author-uri` );
		const githubLink = rowMeta.getByRole( 'link', { name: 'View on GitHub' } );

		await expect( githubLink ).toBeVisible();
		await expect( githubLink ).toHaveAttribute( 'href', 'https://github.com/BrianHenryIE/bh-wp-plugin-meta-kit' );
		await expect( githubLink ).toHaveAttribute( 'target', '_blank' );

		await expect( rowMeta ).toHaveText( /^Version 1\.0\.0 \| By BrianHenryIE \|.*View on GitHub/ );
	} );

	test( 'is not added to other plugins', async ( { page } ) => {
		await expect( page.getByRole( 'link', { name: 'View on GitHub' } ) ).toHaveCount( 1 );
	} );
} );
