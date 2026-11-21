/**
 * An admin notice is shown for each of the plugin's dependencies the site does not have, and stays dismissed for the
 * user who dismissed it.
 *
 * The development plugin declares one dependency, whose check reads the `development_plugin_missing_dependency`
 * option. The tests set it through the core settings REST endpoint, and reset dismissals through the development
 * plugin's REST route. The tests share that state, so they run one at a time.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { Locator, Page } from '@playwright/test';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginName = 'BH Plugin Meta Kit Development/Test/Demo plugin';
const dependencyName = 'GMP (GNU Multiple Precision Arithmetic Library) PHP extension';

const noticeOn = ( page: Page ): Locator =>
	page.locator( '.notice' ).filter( { hasText: 'is required but is not available on this site.' } );

/**
 * Dismiss the notice and wait until the dismissal has been saved.
 */
const dismissNotice = async ( page: Page ): Promise< void > => {
	const dismissalSaved = page.waitForResponse(
		( response ) => response.url().includes( 'admin-ajax.php' ) && response.request().method() === 'POST'
	);

	await noticeOn( page ).getByRole( 'button', { name: 'Dismiss this notice.' } ).click();

	await dismissalSaved;
};

test.describe.configure( { mode: 'serial' } );

test.describe( 'dependencies notice', () => {
	test.beforeEach( async ( { requestUtils, page } ) => {
		await requestUtils.rest( { method: 'DELETE', path: '/development-plugin/v1/dismissed-notices' } );
		await requestUtils.updateSiteSettings( { development_plugin_missing_dependency: true } as any );

		await loginAsAdmin( page );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.updateSiteSettings( { development_plugin_missing_dependency: false } as any );
		await requestUtils.rest( { method: 'DELETE', path: '/development-plugin/v1/dismissed-notices' } );
	} );

	test( 'says what is missing, why it is needed, and offers to deactivate the plugin', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		const notice = noticeOn( page );

		await expect( notice ).toHaveCount( 1 );
		await expect( notice ).toContainText( `${ pluginName }: ${ dependencyName } is required but is not available on this site.` );
		await expect( notice ).toContainText( 'It is needed for the calculations to derive Bitcoin payment addresses.' );
		await expect( notice.locator( 'strong' ).last() ).toHaveText( 'contact your hosting provider for support' );

		await expect( notice.getByRole( 'link', { name: dependencyName } ) ).toHaveAttribute(
			'href',
			'https://www.php.net/manual/en/book.gmp.php'
		);

		const deactivateHref = await notice.getByRole( 'link', { name: `Deactivate ${ pluginName }` } ).getAttribute( 'href' );
		const deactivateUrl = new URL( deactivateHref ?? '', page.url() );

		expect( deactivateUrl.pathname ).toMatch( /\/wp-admin\/plugins\.php$/ );
		expect( deactivateUrl.searchParams.get( 'action' ) ).toBe( 'deactivate' );
		expect( deactivateUrl.searchParams.get( 'plugin' ) ).toBe( 'development-plugin/development-plugin.php' );
		expect( deactivateUrl.searchParams.get( '_wpnonce' ) ).toBeTruthy();
	} );

	test( 'is not shown when the dependency is satisfied', async ( { admin, page, requestUtils } ) => {
		await requestUtils.updateSiteSettings( { development_plugin_missing_dependency: false } as any );

		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( '#wpbody-content' ) ).toBeVisible();
		await expect( noticeOn( page ) ).toHaveCount( 0 );
	} );

	test( 'stays dismissed on later pages', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		await dismissNotice( page );

		await expect( noticeOn( page ) ).toBeHidden();

		await admin.visitAdminPage( 'plugins.php' );

		await expect( page.locator( '#wpbody-content' ) ).toBeVisible();
		await expect( noticeOn( page ) ).toHaveCount( 0 );
	} );

	test( 'is shown again after "Reset dismissed notices" on the development plugin settings screen', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await dismissNotice( page );

		await admin.visitAdminPage( 'admin.php', 'page=development-plugin' );

		await expect( noticeOn( page ) ).toHaveCount( 0 );

		await page.getByRole( 'button', { name: 'Reset dismissed notices' } ).click();

		await expect( page.locator( '#development-plugin-dismissed-notices-reset' ) ).toHaveText(
			'Dismissed notices will be shown again.'
		);
		await expect( noticeOn( page ) ).toHaveCount( 1 );
	} );

	test( 'can be switched on from the development plugin settings screen', async ( { admin, page, requestUtils } ) => {
		await requestUtils.updateSiteSettings( { development_plugin_missing_dependency: false } as any );

		await admin.visitAdminPage( 'admin.php', 'page=development-plugin' );

		await expect( noticeOn( page ) ).toHaveCount( 0 );

		await page.getByRole( 'checkbox', { name: /Report the example dependency/ } ).check();
		await page.getByRole( 'button', { name: 'Save' } ).click();

		// options.php redirects back to the screen, where the notice now shows.
		await expect( page.getByRole( 'checkbox', { name: /Report the example dependency/ } ) ).toBeChecked();
		await expect( noticeOn( page ) ).toHaveCount( 1 );
	} );
} );
