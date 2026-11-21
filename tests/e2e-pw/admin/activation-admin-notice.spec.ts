/**
 * For a week after the plugin is activated, administrators are invited to its settings screen, until they visit it
 * or dismiss the notice.
 *
 * The library saves when the plugin was activated and when its settings were last visited in two options. The tests
 * set them through the core settings REST endpoint, rather than deactivating the plugin the other tests are using,
 * and reset dismissals through the development plugin's REST route. The tests share that state, so they run one
 * at a time.
 *
 * Other test files visit the settings screen while these run, which counts as a visit. Where a test needs the notice
 * to be showing, it says the plugin was activated a moment in the future, which no visit can be later than.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { Locator, Page } from '@playwright/test';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginName = 'BH Plugin Meta Kit Development/Test/Demo plugin';

const noticeOn = ( page: Page ): Locator =>
	page.locator( '.notice' ).filter( { hasText: 'options can be configured in its settings.' } );

const now = (): number => Math.floor( Date.now() / 1000 );

const hour = 60 * 60;
const day = 24 * hour;

test.describe.configure( { mode: 'serial' } );

test.describe( 'activation admin notice', () => {
	/**
	 * Say when the plugin was activated and when its settings were last visited, as Unix timestamps.
	 */
	const setTimes = async ( requestUtils: any, activated: number, settingsVisited: number = 0 ): Promise< void > => {
		await requestUtils.updateSiteSettings( {
			development_plugin_bhpmk_last_activated: activated,
			development_plugin_bhpmk_settings_visited: settingsVisited,
		} );
	};

	test.beforeEach( async ( { requestUtils, page } ) => {
		await requestUtils.rest( { method: 'DELETE', path: '/development-plugin/v1/dismissed-notices' } );

		await loginAsAdmin( page );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await setTimes( requestUtils, 0 );
		await requestUtils.rest( { method: 'DELETE', path: '/development-plugin/v1/dismissed-notices' } );
	} );

	test( 'names the plugin and links to its settings screen', async ( { admin, page, requestUtils } ) => {
		await setTimes( requestUtils, now() + hour );

		await admin.visitAdminPage( 'index.php' );

		const notice = noticeOn( page );

		await expect( notice ).toHaveCount( 1 );
		await expect( notice.locator( 'p' ) ).toHaveText( `${ pluginName } options can be configured in its settings.` );
		await expect( notice.locator( 'strong' ) ).toHaveText( pluginName );

		await notice.getByRole( 'link', { name: 'settings' } ).click();

		await expect( page ).toHaveURL( /\/wp-admin\/admin\.php\?page=development-plugin$/ );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Meta Kit Development' } ) ).toBeVisible();
	} );

	test( 'is not shown on the settings screen itself', async ( { admin, page, requestUtils } ) => {
		await setTimes( requestUtils, now() + hour );

		await admin.visitAdminPage( 'admin.php', 'page=development-plugin' );

		await expect( page.getByRole( 'heading', { level: 1, name: 'Meta Kit Development' } ) ).toBeVisible();
		await expect( noticeOn( page ) ).toHaveCount( 0 );
	} );

	test( 'is not shown again once the settings screen has been visited', async ( { admin, page, requestUtils } ) => {
		await setTimes( requestUtils, now() - 60 );

		await admin.visitAdminPage( 'admin.php', 'page=development-plugin' );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Meta Kit Development' } ) ).toBeVisible();

		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( '#wpbody-content' ) ).toBeVisible();
		await expect( noticeOn( page ) ).toHaveCount( 0 );
	} );

	test( 'is not shown when the plugin was activated more than a week ago', async ( { admin, page, requestUtils } ) => {
		await setTimes( requestUtils, now() - 8 * day );

		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( '#wpbody-content' ) ).toBeVisible();
		await expect( noticeOn( page ) ).toHaveCount( 0 );
	} );

	test( 'stays dismissed on later pages', async ( { admin, page, requestUtils } ) => {
		await setTimes( requestUtils, now() + hour );

		await admin.visitAdminPage( 'index.php' );

		const dismissalSaved = page.waitForResponse(
			( response ) => response.url().includes( 'admin-ajax.php' ) && response.request().method() === 'POST'
		);
		await noticeOn( page ).getByRole( 'button', { name: 'Dismiss this notice.' } ).click();
		await dismissalSaved;

		await admin.visitAdminPage( 'plugins.php' );

		await expect( page.locator( '#wpbody-content' ) ).toBeVisible();
		await expect( noticeOn( page ) ).toHaveCount( 0 );
	} );

	test( 'is shown again after "Reset dismissed notices" on the development plugin settings screen', async ( { admin, page, requestUtils } ) => {
		await setTimes( requestUtils, now() + hour );

		await admin.visitAdminPage( 'index.php' );

		const dismissalSaved = page.waitForResponse(
			( response ) => response.url().includes( 'admin-ajax.php' ) && response.request().method() === 'POST'
		);
		await noticeOn( page ).getByRole( 'button', { name: 'Dismiss this notice.' } ).click();
		await dismissalSaved;

		await admin.visitAdminPage( 'admin.php', 'page=development-plugin' );
		await page.getByRole( 'button', { name: 'Reset dismissed notices' } ).click();
		await expect( page.locator( '#development-plugin-dismissed-notices-reset' ) ).toBeVisible();

		await admin.visitAdminPage( 'index.php' );

		await expect( noticeOn( page ) ).toHaveCount( 1 );
	} );
} );
