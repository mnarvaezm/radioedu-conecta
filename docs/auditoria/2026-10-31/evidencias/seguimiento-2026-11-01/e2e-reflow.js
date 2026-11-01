// F-13: at 320 CSS px the directory must not need horizontal scrolling. Reports every element of the
// directory that sticks out of the viewport. Usage: node e2e-reflow.js [path] [screenshot]
const { chromium } = require( 'C:/radioedu-conecta/node_modules/playwright-core' );

const BASE = 'http://localhost:8091';
const path = process.argv[ 2 ] || '/traspasando-fronteras/';
const shot = process.argv[ 3 ] || '';

( async () => {
	const browser = await chromium.launch( { channel: 'msedge' } );
	const page = await browser.newPage( { viewport: { width: 320, height: 800 } } );
	await page.goto( BASE + path, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.radioedu-card', { timeout: 60000 } );
	await page.waitForTimeout( 500 );
	const result = await page.evaluate( () => {
		const vw = document.documentElement.clientWidth;
		const wide = [];
		document.querySelectorAll( '.radioedu-directory-app *, [class*="radioedu-"]' ).forEach( ( el ) => {
			const r = el.getBoundingClientRect();
			if ( r.width > 0 && ( r.right > vw + 1 || r.left < -1 ) ) {
				wide.push( `${ el.tagName.toLowerCase() }.${ String( el.className ).split( ' ' )[ 0 ] } [${ Math.round( r.left ) }, ${ Math.round( r.right ) }]` );
			}
		} );
		return { viewport: vw, documentWidth: document.documentElement.scrollWidth, overflowing: [ ...new Set( wide ) ].slice( 0, 15 ) };
	} );
	if ( shot ) {
		await page.screenshot( { path: shot, fullPage: false } );
	}
	console.log( JSON.stringify( result, null, 2 ) );
	const ok = result.documentWidth <= result.viewport && result.overflowing.length === 0;
	console.log( ok ? 'PASS' : 'FAIL' );
	await browser.close();
	process.exit( ok ? 0 : 1 );
} )();
