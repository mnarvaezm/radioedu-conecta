// Q-02: the directory block, now registered from block.json, still appears in the editor and
// previews with ServerSideRender. Runs against the QA stack (8091).
const { chromium } = require( 'C:/radioedu-conecta/node_modules/playwright-core' );

const BASE = 'http://localhost:8091';

( async () => {
	const browser = await chromium.launch( { channel: 'msedge' } );
	const page = await browser.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( `${ BASE }/wp-login.php?redirect_to=${ encodeURIComponent( BASE + '/wp-admin/post-new.php?post_type=page' ) }` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForURL( /post-new\.php/, { timeout: 90000 } ), page.click( '#wp-submit' ) ] );
	await page.waitForFunction( () => window.wp && wp.blocks && wp.blocks.getBlockType( 'radioedu-conecta/directorio' ), null, { timeout: 90000 } );

	const type = await page.evaluate( () => {
		const t = wp.blocks.getBlockType( 'radioedu-conecta/directorio' );
		return { title: t.title, category: t.category, keywords: t.keywords, multiple: t.supports.multiple };
	} );
	// Insert it once the editor has loaded the post, and wait for the server-side preview.
	await page.waitForSelector( '.block-editor-default-block-appender, iframe[name="editor-canvas"]', { timeout: 90000 } );
	await page.waitForTimeout( 3000 );
	await page.evaluate( () => wp.data.dispatch( 'core/block-editor' ).insertBlocks( wp.blocks.createBlock( 'radioedu-conecta/directorio' ) ) );
	await page.waitForFunction( () => wp.data.select( 'core/block-editor' ).getBlocks().some( ( b ) => 'radioedu-conecta/directorio' === b.name ), null, { timeout: 30000 } );
	const frames = [ page.mainFrame(), ...page.frames() ];
	let previewRendered = false;
	for ( let i = 0; i < 60 && ! previewRendered; ++i ) {
		for ( const f of frames ) {
			if ( await f.locator( '#radioedu-results-heading' ).count().catch( () => 0 ) ) {
				previewRendered = true;
			}
		}
		await page.waitForTimeout( 1000 );
	}

	console.log( JSON.stringify( { type, previewRendered, errors }, null, 2 ) );
	const ok = 'Directorio Traspasando Fronteras' === type.title && 'widgets' === type.category && false === type.multiple && previewRendered && errors.length === 0;
	console.log( ok ? 'PASS' : 'FAIL' );
	await browser.close();
	process.exit( ok ? 0 : 1 );
} )();
