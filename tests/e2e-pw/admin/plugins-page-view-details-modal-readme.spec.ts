/**
 * The plugin details modal, opened from "View details" on plugins.php, is filled from the plugin's readme.txt.
 *
 * Nothing is arranged or created: the expected values are those in `development-plugin/readme.txt` and the headers
 * of `development-plugin/development-plugin.php`.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { FrameLocator } from '@playwright/test';
import { loginAsAdmin } from '../helpers/ui/login';

const pluginBasename = 'development-plugin/development-plugin.php';
const pluginName = 'BH Plugin Meta Kit Development/Test/Demo plugin';

test.describe( 'plugin details modal readme', () => {
	let modal: FrameLocator;

	test.beforeEach( async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'plugins.php' );

		await page
			.locator( `tr[data-plugin="${ pluginBasename }"]` )
			.getByRole( 'link', { name: `More information about ${ pluginName }` } )
			.click();

		modal = page.frameLocator( '#TB_iframeContent' );
	} );

	test( 'shows the readme description', async () => {
		const description = modal.locator( '#section-description' );

		await expect( description ).toBeVisible();
		await expect( description ).toContainText(
			'This plugin bundles the BH Plugin Meta Kit library so its additions to plugins.php can be seen and tested.'
		);
		// The readme's markdown is rendered, not printed.
		await expect( description.locator( 'strong' ) ).toHaveText( 'BH Plugin Meta Kit' );
		await expect( description.locator( 'li' ) ).toHaveCount( 2 );
	} );

	test( 'shows the readme requirements', async () => {
		const requirements = modal.locator( '.fyi' );

		await expect( requirements ).toContainText( 'Requires WordPress Version: 6.2 or higher' );
		await expect( requirements ).toContainText( 'Requires PHP Version: 8.2 or higher' );
		await expect( requirements ).toContainText( 'Compatible up to: 6.5' );
	} );

	test( 'shows the version, author and homepage from the plugin headers', async () => {
		const details = modal.locator( '.fyi' );

		await expect( details ).toContainText( 'Version: 1.0.0' );

		await expect( details ).toContainText( 'Author: BrianHenryIE' );
		await expect( details.getByRole( 'link', { name: 'BrianHenryIE', exact: true } ) ).toHaveAttribute(
			'href',
			'https://bhwp.ie'
		);

		await expect( details.getByRole( 'link', { name: 'Plugin Homepage' } ) ).toHaveAttribute(
			'href',
			'https://github.com/BrianHenryIE/bh-wp-plugin-meta-kit'
		);
	} );

	test( 'lists the readme contributors, linked to their WordPress.org profiles', async () => {
		const contributor = modal.locator( '.contributors' ).getByRole( 'link', { name: 'brianhenryie' } );

		await expect( contributor ).toBeVisible();
		await expect( contributor ).toHaveAttribute( 'href', 'https://profiles.wordpress.org/brianhenryie/' );
	} );

	test( 'does not warn that the plugin is untested on a patch release of the tested version', async () => {
		await expect( modal.locator( '#section-holder' ) ).toBeVisible();
		await expect( modal.getByText( 'has not been tested with your current version of WordPress' ) ).toHaveCount( 0 );
	} );

	test( 'shows the readme FAQ on its own tab', async () => {
		await modal.getByRole( 'link', { name: 'FAQ' } ).click();

		const faq = modal.locator( '#section-faq' );

		await expect( faq ).toBeVisible();
		await expect( faq ).toContainText( 'Should I install this on a live site?' );
		await expect( faq ).toContainText( 'No. It exists for developing and testing the library.' );
	} );

	test( 'shows the readme changelog on its own tab', async () => {
		await modal.getByRole( 'link', { name: 'Changelog' } ).click();

		const changelog = modal.locator( '#section-changelog' );

		await expect( changelog ).toBeVisible();
		// `= 1.0.0 =` is readme.txt's heading syntax, which must be rendered, not printed.
		await expect( changelog.getByRole( 'heading', { name: '1.0.0', exact: true } ) ).toBeVisible();
		await expect( changelog ).not.toContainText( '= 1.0.0 =' );
		await expect( changelog.locator( 'li' ) ).toHaveText( 'First version of the development plugin.' );
	} );
} );
