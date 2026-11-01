// F-12: the map shows every result, not only the first page of cards. Runs against the QA stack
// (8091) with 66 published centros that have coordinates. Map tiles are blocked, so nothing is
// sent to OpenStreetMap.
const { chromium } = require( 'C:/radioedu-conecta/node_modules/playwright-core' );

const BASE = 'http://localhost:8091';

( async () => {
	const browser = await chromium.launch( { channel: 'msedge' } );
	const page = await browser.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.route( '**/tile.openstreetmap.org/**', ( route ) => route.abort() );
	const centrosRequests = [];
	page.on( 'request', ( r ) => {
		if ( r.url().includes( '/radioedu-conecta/v1/centros' ) ) {
			centrosRequests.push( new URL( r.url() ).searchParams.get( 'per_page' ) );
		}
	} );

	await page.goto( `${ BASE }/traspasando-fronteras/`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.radioedu-card', { timeout: 60000 } );
	const cards = await page.locator( '.radioedu-card' ).count();
	const total = ( await page.locator( '#radioedu-count-number' ).textContent() ).trim();

	await page.click( '#radioedu-btn-toggle-map' );
	await page.click( '#radioedu-btn-accept-map' );
	await page.waitForFunction( () => document.querySelectorAll( '.leaflet-marker-icon' ).length > 24, null, { timeout: 30000 } );
	await page.waitForTimeout( 500 );
	const markers = await page.locator( '.leaflet-marker-icon' ).count();
	const noteHidden = await page.locator( '#radioedu-map-note' ).evaluate( ( n ) => n.hidden );

	const result = { cards, total, markers, noteHidden, centrosRequests, errors };
	console.log( JSON.stringify( result, null, 2 ) );
	const ok = cards === 24 && total === '66' && markers === 66 && noteHidden && errors.length === 0;
	console.log( ok ? 'PASS' : 'FAIL' );
	await browser.close();
	process.exit( ok ? 0 : 1 );
} )();
