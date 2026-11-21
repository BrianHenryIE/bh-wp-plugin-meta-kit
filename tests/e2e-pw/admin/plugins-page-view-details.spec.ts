/**
 * The library adds a "View details" link to its plugin's row on plugins.php, which core only prints for plugins
 * hosted on WordPress.org.
 *
 * Nothing is arranged or created: the development plugin bundles the library with its zero-config bootstrap.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { Locator, Page } from '@playwright/test';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginBasename = 'development-plugin/development-plugin.php';
const pluginSlug = 'development-plugin';
const pluginName = 'BH Plugin Meta Kit Development/Test/Demo plugin';

/**
 * Core gives the link the visible text "View details" and an aria-label naming the plugin.
 */
const viewDetailsLinkIn = ( page: Page ): Locator =>
	page
		.locator( `tr[data-plugin="${ pluginBasename }"]` )
		.getByRole( 'link', { name: `More information about ${ pluginName }` } );

test.describe( 'plugins.php "View details" link', () => {
	test.beforeEach( async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'plugins.php' );
	} );

	test( 'is added to the row of the plugin the library is bundled in', async ( { page } ) => {
		const viewDetailsLink = viewDetailsLinkIn( page );

		await expect( viewDetailsLink ).toBeVisible();
		await expect( viewDetailsLink ).toHaveText( 'View details' );

		const href = new URL( ( await viewDetailsLink.getAttribute( 'href' ) ) ?? '', page.url() );
		expect( href.pathname ).toMatch( /\/wp-admin\/plugin-install\.php$/ );
		expect( href.searchParams.get( 'tab' ) ).toBe( 'plugin-information' );
		expect( href.searchParams.get( 'plugin' ) ).toBe( pluginSlug );
	} );

	test( 'is only added to that one row', async ( { page } ) => {
		const linksToThisPlugin = page.locator( `a[href*="plugin=${ pluginSlug }&"]` );

		await expect( linksToThisPlugin ).toHaveCount( 1 );
	} );

	test( 'opens the plugin details modal for the plugin', async ( { page } ) => {
		await viewDetailsLinkIn( page ).click();

		const modal = page.frameLocator( '#TB_iframeContent' );

		await expect( modal.locator( '#plugin-information-title' ) ).toContainText( pluginName );
	} );
} );
