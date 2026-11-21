/**
 * The Changelog tab of the plugin details modal merges the plugin's CHANGELOG.md with the changelog in its readme.txt.
 *
 * Nothing is arranged or created: `development-plugin/CHANGELOG.md` and `development-plugin/readme.txt` are
 * deliberately out of step with each other.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { Locator } from '@playwright/test';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginBasename = 'development-plugin/development-plugin.php';
const pluginName = 'BH Plugin Meta Kit Development/Test/Demo plugin';

test.describe( 'plugin details modal changelog tab', () => {
	let changelog: Locator;

	test.beforeEach( async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'plugins.php' );

		await page
			.locator( `tr[data-plugin="${ pluginBasename }"]` )
			.getByRole( 'link', { name: `More information about ${ pluginName }` } )
			.click();

		const modal = page.frameLocator( '#TB_iframeContent' );

		await modal.getByRole( 'link', { name: 'Changelog' } ).click();

		changelog = modal.locator( '#section-changelog' );

		await expect( changelog ).toBeVisible();
	} );

	test( 'shows each version as a heading, with its date, newest first', async () => {
		await expect( changelog.getByRole( 'heading', { level: 4 } ) ).toHaveText( [
			'1.0.0 – 2026-11-20',
			'0.9.0 – 2026-11-01',
		] );

		// `= 1.0.0 =` and `## [1.0.0]` are heading syntax, which must be rendered, not printed.
		await expect( changelog ).not.toContainText( '= 1.0.0 =' );
		await expect( changelog ).not.toContainText( '[1.0.0]' );
	} );

	test( 'shows a line which is in both files once', async () => {
		await expect( changelog.getByText( 'First version of the development plugin.' ) ).toHaveCount( 1 );
	} );

	test( 'shows the lines which are only in one of the files', async () => {
		await expect( changelog.getByRole( 'listitem' ) ).toHaveText( [
			'First version of the development plugin.',
			'This line is only in CHANGELOG.md',
			'This line is only in readme.txt',
			'This version is only in CHANGELOG.md',
		] );
	} );

	test( 'does not show text from outside the versions', async () => {
		await expect( changelog ).not.toContainText( 'Deliberately out of step' );
	} );
} );
