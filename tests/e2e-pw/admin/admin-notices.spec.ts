/**
 * The tests of the library's dismissible admin notices.
 *
 * They are in one spec file because they share state: "Reset dismissed notices" shows every notice again, so a
 * reset in one set of tests would un-dismiss the notice another set had just dismissed. Playwright runs spec files
 * in parallel, and the tests in one file, in serial mode, one after another.
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

import './notices/dependencies-notice';
import './notices/activation-admin-notice';

test.describe.configure( { mode: 'serial' } );
