/**
 * Takes the screenshots in `docs/screenshots`, which the README shows.
 *
 * Skipped unless `UPDATE_DOCS_SCREENSHOTS=1`: it is not a test, and it changes the options the notice tests use,
 * so run it on its own:
 *
 * `UPDATE_DOCS_SCREENSHOTS=1 npx playwright test tests/e2e-pw/docs-screenshots.spec.ts --project=chromium`
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import path from 'path';
import { loginAsAdmin } from './helpers/ui/login';

const pluginBasename = 'development-plugin/development-plugin.php';
const pluginName = 'BH Plugin Meta Kit Development/Test/Demo plugin';

const screenshotPath = ( name: string ): string => path.join( process.cwd(), 'docs', 'screenshots', `${ name }.png` );

test.describe.configure( { mode: 'serial' } );

test.describe( 'docs screenshots', () => {
	test.skip( process.env.UPDATE_DOCS_SCREENSHOTS !== '1', 'Set UPDATE_DOCS_SCREENSHOTS=1 to retake the screenshots.' );

	test.use( { viewport: { width: 1280, height: 900 } } );

	test.beforeEach( async ( { requestUtils, page } ) => {
		await requestUtils.rest( { method: 'DELETE', path: '/development-plugin/v1/dismissed-notices' } );
		// Neither notice, unless the screenshot is of it.
		await requestUtils.updateSiteSettings( {
			development_plugin_missing_dependency: false,
			development_plugin_bhpmk_last_activated: 0,
			development_plugin_bhpmk_settings_visited: 0,
		} as any );

		await loginAsAdmin( page );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.updateSiteSettings( {
			development_plugin_missing_dependency: false,
			development_plugin_bhpmk_last_activated: 0,
			development_plugin_bhpmk_settings_visited: 0,
		} as any );
	} );

	test( 'the plugin row on plugins.php', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const row = page.locator( `tr[data-plugin="${ pluginBasename }"]` );

		await expect( row.getByRole( 'link', { name: 'View on GitHub' } ) ).toBeVisible();
		// The row's action links are only fully visible while it is hovered.
		await row.hover();

		await row.screenshot( { path: screenshotPath( 'plugins-page-row' ) } );
	} );

	test( 'the plugin details modal', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );

		await page
			.locator( `tr[data-plugin="${ pluginBasename }"]` )
			.getByRole( 'link', { name: `More information about ${ pluginName }` } )
			.click();

		const modal = page.frameLocator( '#TB_iframeContent' );

		await expect( modal.locator( '#section-description' ) ).toBeVisible();
		await page.locator( '#TB_window' ).screenshot( { path: screenshotPath( 'plugin-details-modal' ) } );

		await modal.getByRole( 'link', { name: 'Changelog' } ).click();

		await expect( modal.locator( '#section-changelog' ) ).toBeVisible();
		await page.locator( '#TB_window' ).screenshot( { path: screenshotPath( 'plugin-details-modal-changelog' ) } );
	} );

	test( 'the dependencies notice', async ( { admin, page, requestUtils } ) => {
		await requestUtils.updateSiteSettings( { development_plugin_missing_dependency: true } as any );

		await admin.visitAdminPage( 'index.php' );

		const notice = page.locator( '.notice' ).filter( { hasText: 'is required but is not available on this site.' } );

		await expect( notice.getByRole( 'button', { name: 'Dismiss this notice.' } ) ).toBeVisible();
		await notice.screenshot( { path: screenshotPath( 'dependencies-notice' ) } );
	} );

	test( 'the activation notice', async ( { admin, page, requestUtils } ) => {
		await requestUtils.updateSiteSettings( {
			development_plugin_bhpmk_last_activated: Math.floor( Date.now() / 1000 ),
		} as any );

		await admin.visitAdminPage( 'index.php' );

		const notice = page.locator( '.notice' ).filter( { hasText: 'options can be configured in its settings.' } );

		await expect( notice.getByRole( 'button', { name: 'Dismiss this notice.' } ) ).toBeVisible();
		await notice.screenshot( { path: screenshotPath( 'activation-notice' ) } );
	} );
} );
