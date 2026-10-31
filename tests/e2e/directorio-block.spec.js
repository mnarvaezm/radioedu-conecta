/**
 * PHP-only directory block (supports.autoRegister) and the SVG icons it renders.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const CENTRO_TITLE = 'Centro E2E del directorio';

test.describe( 'Bloque Directorio Traspasando Fronteras', () => {
	const pageIds = [];
	const centroIds = [];

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'radioedu-conecta' );
		const centro = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/centros',
			data: { title: CENTRO_TITLE, status: 'publish' },
		} );
		centroIds.push( centro.id );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of pageIds ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ id }`,
				params: { force: true },
			} );
		}
		for ( const id of centroIds ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/centros/${ id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'se inserta sin JavaScript propio, se previsualiza en el editor y funciona en la web', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( {
			postType: 'page',
			title: 'Directorio E2E',
		} );
		pageIds.push(
			await page.evaluate( () =>
				window.wp.data.select( 'core/editor' ).getCurrentPostId()
			)
		);

		// Core registers the block in the editor and previews it with ServerSideRender; the first
		// server render of a cold site can take several seconds.
		await editor.insertBlock( { name: 'radioedu-conecta/directorio' } );
		await expect(
			editor.canvas.locator( '#radioedu-conecta-app' )
		).toBeVisible( { timeout: 30_000 } );

		await editor.publishPost();
		const permalink = await page.evaluate( () =>
			window.wp.data.select( 'core/editor' ).getPermalink()
		);
		await page.goto( permalink );

		await expect(
			page.locator(
				'.radioedu-filters-bar .radioedu-svg-icon--map-marker'
			)
		).toBeVisible();
		const card = page.locator( '.radioedu-card', {
			hasText: CENTRO_TITLE,
		} );
		await expect(
			card.locator( '.radioedu-btn-match .radioedu-svg-icon--send' )
		).toBeVisible();

		// The favourite button swaps icons cloned from the server-rendered <template>.
		const favorite = card.locator( '.radioedu-card-fav-btn' );
		await expect(
			favorite.locator( '.radioedu-svg-icon--star-empty' )
		).toBeVisible();
		await favorite.click();
		await expect( favorite ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect(
			favorite.locator( '.radioedu-svg-icon--star-filled' )
		).toBeVisible();
	} );
} );
