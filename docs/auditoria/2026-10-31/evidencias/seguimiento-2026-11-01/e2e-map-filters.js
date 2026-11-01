// R-04: every page of a search uses the filters captured when it started. With 210 results, the
// first map page is held while a search term is typed without searching; the following pages must
// not use it. Then the same for "Cargar más". Runs against the QA stack (8091), tiles blocked.
const { chromium } = require( 'C:/radioedu-conecta/node_modules/playwright-core' );

const BASE = 'http://localhost:8091';

( async () => {
	const browser = await chromium.launch( { channel: 'msedge' } );
	const page = await browser.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.route( '**/tile.openstreetmap.org/**', ( route ) => route.abort() );

	const requests = [];
	let release;
	const held = new Promise( ( resolve ) => ( release = resolve ) );
	await page.route( '**/radioedu-conecta/v1/centros**', async ( route ) => {
		const url = new URL( route.request().url() );
		requests.push( { page: url.searchParams.get( 'page' ), perPage: url.searchParams.get( 'per_page' ), search: url.searchParams.get( 'search' ) } );
		if ( '100' === url.searchParams.get( 'per_page' ) && '1' === url.searchParams.get( 'page' ) ) {
			await held;
		}
		await route.continue();
	} );

	await page.goto( `${ BASE }/traspasando-fronteras/`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.radioedu-card', { timeout: 60000 } );

	// Map: page 1 of 100 is held while a term is typed, without pressing "Buscar".
	await page.click( '#radioedu-btn-toggle-map' );
	await page.click( '#radioedu-btn-accept-map' );
	await page.waitForFunction( () => true );
	while ( ! requests.some( ( r ) => '100' === r.perPage ) ) {
		await page.waitForTimeout( 50 );
	}
	await page.fill( '#radioedu-filter-search', 'ZZZ-NO-MATCH' );
	release();
	await page.waitForFunction( () => document.querySelectorAll( '.leaflet-marker-icon' ).length >= 210, null, { timeout: 30000 } ).catch( () => {} );
	const markers = await page.locator( '.leaflet-marker-icon' ).count();
	const mapPages = requests.filter( ( r ) => '100' === r.perPage );

	// Cards: back to the grid, a fresh page, a term typed without searching, then "Cargar más".
	await page.goto( `${ BASE }/traspasando-fronteras/`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.radioedu-card', { timeout: 60000 } );
	requests.length = 0;
	await page.fill( '#radioedu-filter-search', 'ZZZ-NO-MATCH' );
	await page.click( '#radioedu-btn-load-more' );
	await page.waitForFunction( () => document.querySelectorAll( '.radioedu-card' ).length > 24, null, { timeout: 30000 } ).catch( () => {} );
	const cards = await page.locator( '.radioedu-card' ).count();
	const loadMore = requests.filter( ( r ) => '24' === r.perPage );

	const result = { markers, mapPages, cards, loadMore, errors };
	console.log( JSON.stringify( result, null, 2 ) );
	const ok = 210 === markers && 3 === mapPages.length && mapPages.every( ( r ) => null === r.search ) &&
		48 === cards && loadMore.length >= 1 && loadMore.every( ( r ) => null === r.search ) && 0 === errors.length;
	console.log( ok ? 'PASS' : 'FAIL' );
	await browser.close();
	process.exit( ok ? 0 : 1 );
} )();
