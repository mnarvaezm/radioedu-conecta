/**
 * Playwright configuration for the end-to-end tests in tests/e2e.
 *
 * Extends the `@wordpress/scripts` defaults: wp-env test site on http://localhost:8889 with the
 * admin/password account. WP_BASE_URL points the tests at another disposable site instead, and
 * PLAYWRIGHT_CHANNEL (e.g. "msedge") uses an installed browser instead of the bundled Chromium.
 * The tests create and delete their own content, so never run them against a site with real data.
 */
const { defineConfig, devices } = require( '@playwright/test' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = defineConfig( {
	...baseConfig,
	testDir: './tests/e2e',
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				channel: process.env.PLAYWRIGHT_CHANNEL || undefined,
			},
		},
	],
} );
