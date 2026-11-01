// F-11: a late reply to a proposal sent to centro A must not change the drawer reopened for centro B.
// Runs against the QA stack (8091). The proposal POST is intercepted and answered with a fake 201,
// so nothing is stored and no mail is sent.
const { chromium } = require( 'C:/radioedu-conecta/node_modules/playwright-core' );

const BASE = 'http://localhost:8091';

( async () => {
	const browser = await chromium.launch( { channel: 'msedge' } );
	const errors = [];
	const ctx = await browser.newContext();
	const page = await ctx.newPage();
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( `${ BASE }/wp-login.php?redirect_to=${ encodeURIComponent( BASE + '/traspasando-fronteras/' ) }` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForURL( /traspasando-fronteras/, { waitUntil: 'domcontentloaded', timeout: 60000 } ), page.click( '#wp-submit' ) ] );
	await page.waitForSelector( '.radioedu-card .radioedu-btn-match', { timeout: 60000 } );

	let release;
	const held = new Promise( ( resolve ) => ( release = resolve ) );
	let posted = null;
	await page.route( '**/radioedu-conecta/v1/colaboraciones**', async ( route ) => {
		if ( route.request().method() !== 'POST' ) {
			return route.continue();
		}
		posted = route.request().postDataJSON();
		await held;
		await route.fulfill( { status: 201, contentType: 'application/json', body: JSON.stringify( { success: true, message: 'Sent to A', id: 999 } ) } );
	} );

	const cards = page.locator( '.radioedu-card' );
	const idA = await cards.nth( 0 ).getAttribute( 'data-id' );
	const idB = await cards.nth( 1 ).getAttribute( 'data-id' );

	// Send to A and close the panel while the request is still pending.
	await cards.nth( 0 ).locator( '.radioedu-btn-match' ).click();
	await page.waitForSelector( '#radioedu-match-drawer.is-open' );
	const emisor = page.locator( 'select#radioedu-match-emisor' );
	if ( await emisor.count() ) {
		const options = await emisor.locator( 'option' ).evaluateAll( ( o, a ) => o.map( ( x ) => x.value ).filter( ( v ) => v && v !== a ), idA );
		await emisor.selectOption( options[ 0 ] );
	}
	await page.fill( '#radioedu-match-idea', 'Idea for A' );
	await page.click( '#radioedu-btn-submit-match' );
	while ( ! posted ) {
		await page.waitForTimeout( 50 );
	}
	await page.click( '#radioedu-btn-close-drawer' );

	// Reopen for B straight away (inside the 350 ms closing animation) and start a draft.
	await cards.nth( 1 ).locator( '.radioedu-btn-match' ).click();
	await page.waitForTimeout( 500 );
	const visibleAfterQuickReopen = await page.locator( '#radioedu-match-drawer' ).isVisible();
	const submitEnabledBeforeReply = await page.locator( '#radioedu-btn-submit-match' ).isEnabled();
	await page.fill( '#radioedu-match-idea', 'Draft for B' );

	// The reply for A arrives now.
	release();
	await page.waitForTimeout( 800 );

	const state = await page.evaluate( () => ( {
		receptorId: document.getElementById( 'radioedu-match-receptor' ).value,
		receptorTxt: document.getElementById( 'radioedu-match-receptor-txt' ).value,
		idea: document.getElementById( 'radioedu-match-idea' ).value,
		feedback: document.getElementById( 'radioedu-drawer-feedback' ).textContent,
		submitDisabled: document.getElementById( 'radioedu-btn-submit-match' ).disabled,
	} ) );
	const nameB = ( await cards.nth( 1 ).locator( 'h3' ).first().textContent() ).trim();

	const result = {
		postedTo: String( posted.centro_receptor_id ),
		idA,
		idB,
		nameB,
		visibleAfterQuickReopen,
		submitEnabledBeforeReply,
		...state,
		errors,
	};
	console.log( JSON.stringify( result, null, 2 ) );
	const ok = result.postedTo === idA && state.receptorId === idB && state.receptorTxt.startsWith( nameB ) &&
		state.idea === 'Draft for B' && ! state.feedback.includes( 'Sent to A' ) && ! state.submitDisabled &&
		visibleAfterQuickReopen && submitEnabledBeforeReply && errors.length === 0;
	console.log( ok ? 'PASS' : 'FAIL' );
	await browser.close();
	process.exit( ok ? 0 : 1 );
} )();
