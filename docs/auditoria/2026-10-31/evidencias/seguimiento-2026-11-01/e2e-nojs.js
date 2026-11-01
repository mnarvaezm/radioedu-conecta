// F-16: without JavaScript the directory shows the real count and a link to the list of radios;
// with JavaScript the notice is hidden. Runs against the QA stack (8091).
const { chromium } = require( 'C:/radioedu-conecta/node_modules/playwright-core' );

const BASE = 'http://localhost:8091';

( async () => {
	const browser = await chromium.launch( { channel: 'msedge' } );

	const noJs = await browser.newContext( { javaScriptEnabled: false } );
	const page = await noJs.newPage();
	await page.goto( `${ BASE }/traspasando-fronteras/`, { waitUntil: 'domcontentloaded' } );
	const count = ( await page.locator( '#radioedu-count-number' ).textContent() ).trim();
	const fallbackVisible = await page.locator( '#radioedu-js-fallback' ).isVisible();
	const link = await page.locator( '#radioedu-js-fallback a' ).getAttribute( 'href' );
	await page.goto( link, { waitUntil: 'domcontentloaded' } );
	const archiveStatus = await page.evaluate( () => document.title );
	const archiveHasRadio = ( await page.content() ).includes( 'QA Map Radio' ) || ( await page.content() ).includes( 'IES Sierra de Gata' );

	const withJs = await browser.newPage();
	await withJs.goto( `${ BASE }/traspasando-fronteras/`, { waitUntil: 'domcontentloaded' } );
	await withJs.waitForSelector( '.radioedu-card', { timeout: 60000 } );
	const fallbackHiddenWithJs = ! ( await withJs.locator( '#radioedu-js-fallback' ).isVisible() );

	const result = { count, fallbackVisible, link, archiveStatus, archiveHasRadio, fallbackHiddenWithJs };
	console.log( JSON.stringify( result, null, 2 ) );
	const ok = '66' === count && fallbackVisible && archiveHasRadio && fallbackHiddenWithJs;
	console.log( ok ? 'PASS' : 'FAIL' );
	await browser.close();
	process.exit( ok ? 0 : 1 );
} )();
