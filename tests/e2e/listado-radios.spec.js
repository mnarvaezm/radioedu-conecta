/**
 * Pattern "Listado de radios": a query loop of stations whose blocks are bound to the Block
 * Bindings source radioedu-conecta/centro. The editor must accept its markup, and the page must
 * show the data of each station and leave out what a station lacks.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PATTERN = 'radioedu-conecta/listado-radios';
const PREFIX = 'Listado E2E';
const WITH_PODCAST = `${ PREFIX } con podcast`;
const WITHOUT_PODCAST = `${ PREFIX } sin podcast`;
const PODCAST_URL = 'https://example.org/podcast-e2e';

test.describe( 'Patrón Listado de radios', () => {
	const centroIds = [];
	let listPage = null;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'radioedu-conecta' );
		const stations = [
			[
				WITH_PODCAST,
				{
					_radioedu_podcast_url: PODCAST_URL,
					_radioedu_edades: '12-14 años',
				},
			],
			[ WITHOUT_PODCAST, {} ],
		];
		for ( const [ title, meta ] of stations ) {
			const centro = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/centros',
				data: { title, status: 'publish', meta },
			} );
			centroIds.push( centro.id );
		}

		const patterns = await requestUtils.rest( {
			path: '/wp/v2/block-patterns/patterns',
		} );
		const pattern = patterns.find( ( item ) => PATTERN === item.name );
		// Only the stations of this test, whatever else the site holds.
		const content = pattern.content.replace(
			'"inherit":false',
			`"inherit":false,"search":"${ PREFIX }"`
		);
		listPage = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: 'Listado de radios E2E',
				status: 'publish',
				content,
			},
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( listPage ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ listPage.id }`,
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

	test( 'muestra los datos de cada radio y omite los que no tiene', async ( {
		page,
	} ) => {
		await page.goto( listPage.link );

		const items = page.locator( '.wp-block-post' );
		await expect( items ).toHaveCount( 2 );
		const withPodcast = items.filter( { hasText: WITH_PODCAST } );
		const withoutPodcast = items.filter( { hasText: WITHOUT_PODCAST } );

		await expect( withPodcast ).toContainText( '12-14 años' );
		await expect(
			withPodcast.locator( 'a.wp-block-button__link' )
		).toHaveAttribute( 'href', PODCAST_URL );
		// No podcast, ages or cover: no button, no row of buttons, no empty paragraph or image.
		await expect(
			withoutPodcast.locator( '.wp-block-buttons' )
		).toHaveCount( 0 );
		await expect( withoutPodcast.locator( 'p' ) ).toHaveCount( 0 );
		await expect( withoutPodcast.locator( 'img' ) ).toHaveCount( 0 );
	} );

	test( 'el editor acepta el marcado del patrón', async ( {
		admin,
		editor,
	} ) => {
		await admin.editPost( listPage.id );
		// The query loop shows each post twice in the editor: the template being edited and a preview.
		await expect(
			editor.canvas.getByText( WITH_PODCAST ).first()
		).toBeVisible( { timeout: 30_000 } );
		await expect(
			editor.canvas.locator( '.block-editor-warning' )
		).toHaveCount( 0 );
	} );
} );
