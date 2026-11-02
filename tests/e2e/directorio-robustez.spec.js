/**
 * Directory behaviour fixed after the 2026-10-31 audit: the match panel with a late reply
 * (F-11), the map with every result (F-12), "Cargar más" and the map keeping the filters of
 * the search being shown (R-04), reflow at 320 px (F-13) and the page without JavaScript (F-16),
 * which since 1.2 renders its cards on the server, searches by GET and links its pages.
 *
 * Map tiles are blocked, so nothing is sent to OpenStreetMap, and the proposal request of the
 * match panel is answered by the test, so no proposal is stored and no email is sent. Leaflet
 * itself must not be downloaded before the map is accepted.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PREFIX = 'Radio E2E robustez';
const COUNT = 30;
const CARDS_PER_PAGE = 24;

test.describe( 'Directorio: robustez', () => {
	const centroIds = [];
	let pageId = 0;
	let directoryUrl = '';

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'radioedu-conecta' );
		// More stations with coordinates than fit on the first page of cards.
		for ( let i = 1; i <= COUNT; i++ ) {
			const centro = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/centros',
				data: {
					title: `${ PREFIX } ${ String( i ).padStart( 2, '0' ) }`,
					status: 'publish',
					meta: {
						_radioedu_lat: String( 38 + i / 100 ),
						_radioedu_lng: String( -6.5 + i / 100 ),
					},
				},
			} );
			centroIds.push( centro.id );
		}
		const directoryPage = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: 'Directorio E2E robustez',
				status: 'publish',
				content: '<!-- wp:radioedu-conecta/directorio /-->',
			},
		} );
		pageId = directoryPage.id;
		directoryUrl = directoryPage.link;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( pageId ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ pageId }`,
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

	test.beforeEach( async ( { page } ) => {
		await page.route( '**/tile.openstreetmap.org/**', ( route ) =>
			route.abort()
		);
	} );

	test( 'el mapa muestra todos los resultados, no solo las tarjetas cargadas', async ( {
		page,
	} ) => {
		const tileRequests = [];
		const leafletRequests = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'tile.openstreetmap.org' ) ) {
				tileRequests.push( request.url() );
			}
			if ( request.url().includes( '/build/leaflet.js' ) ) {
				leafletRequests.push( request.url() );
			}
		} );

		await page.goto( directoryUrl );
		await expect( page.locator( '.radioedu-card' ) ).toHaveCount(
			CARDS_PER_PAGE
		);
		await expect( page.locator( '#radioedu-js-fallback' ) ).toBeHidden();

		await page.click( '#radioedu-btn-toggle-map' );
		// Neither a tile nor the map library is requested before the visitor accepts the map.
		expect( tileRequests ).toHaveLength( 0 );
		expect( leafletRequests ).toHaveLength( 0 );
		await page.click( '#radioedu-btn-accept-map' );
		await expect.poll( () => leafletRequests.length ).toBe( 1 );

		await expect
			.poll( () => page.locator( '.leaflet-marker-icon' ).count(), {
				timeout: 30_000,
			} )
			.toBeGreaterThanOrEqual( COUNT );
		await expect( page.locator( '#radioedu-map-note' ) ).toBeHidden();
	} );

	test( '«Cargar más» conserva los filtros de la búsqueda mostrada', async ( {
		page,
	} ) => {
		await page.goto( directoryUrl );
		await expect( page.locator( '.radioedu-card' ) ).toHaveCount(
			CARDS_PER_PAGE
		);

		// A term typed without pressing "Buscar" belongs to no search yet.
		await page.fill( '#radioedu-filter-search', 'ZZZ-SIN-RESULTADOS' );
		const nextPage = page.waitForRequest(
			( request ) =>
				request.url().includes( '/radioedu-conecta/v1/centros' ) &&
				'2' === new URL( request.url() ).searchParams.get( 'page' )
		);
		await page.click( '#radioedu-btn-load-more' );

		expect(
			new URL( ( await nextPage ).url() ).searchParams.get( 'search' )
		).toBeNull();
		await expect
			.poll( () => page.locator( '.radioedu-card' ).count() )
			.toBeGreaterThan( CARDS_PER_PAGE );
	} );

	test( 'el mapa pide sus resultados con los filtros de la búsqueda mostrada', async ( {
		page,
	} ) => {
		await page.goto( directoryUrl );
		await page.fill( '#radioedu-filter-search', PREFIX );
		await page.click( '#radioedu-btn-buscar' );
		await expect( page.locator( '.radioedu-card' ) ).toHaveCount(
			CARDS_PER_PAGE
		);
		// The address shows the search, so reloading or sharing it opens the same results.
		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'buscar' ) )
			.toBe( PREFIX );

		// Edited after searching: the map must keep showing the search above.
		await page.fill( '#radioedu-filter-search', 'ZZZ-SIN-RESULTADOS' );
		const mapPage = page.waitForRequest(
			( request ) =>
				request.url().includes( '/radioedu-conecta/v1/centros' ) &&
				'100' ===
					new URL( request.url() ).searchParams.get( 'per_page' )
		);
		await page.click( '#radioedu-btn-toggle-map' );
		await page.click( '#radioedu-btn-accept-map' );

		expect(
			new URL( ( await mapPage ).url() ).searchParams.get( 'search' )
		).toBe( PREFIX );
		await expect
			.poll( () => page.locator( '.leaflet-marker-icon' ).count(), {
				timeout: 30_000,
			} )
			.toBe( COUNT );
	} );

	test( 'una respuesta tardía no cambia el panel abierto para otra radio', async ( {
		page,
	} ) => {
		let release;
		const held = new Promise( ( resolve ) => ( release = resolve ) );
		let posted = null;
		await page.route(
			'**/radioedu-conecta/v1/colaboraciones**',
			async ( route ) => {
				if ( 'POST' !== route.request().method() ) {
					return route.continue();
				}
				posted = route.request().postDataJSON();
				await held;
				await route.fulfill( {
					status: 201,
					contentType: 'application/json',
					body: JSON.stringify( {
						success: true,
						message: 'Enviada a la radio A',
						id: 999,
					} ),
				} );
			}
		);

		await page.goto( directoryUrl );
		const cardA = page.locator( '.radioedu-card', {
			hasText: `${ PREFIX } 01`,
		} );
		const cardB = page.locator( '.radioedu-card', {
			hasText: `${ PREFIX } 02`,
		} );
		const idA = await cardA.getAttribute( 'data-id' );
		const idB = await cardB.getAttribute( 'data-id' );

		// Send to A and close the panel while the request is still pending.
		await cardA.locator( '.radioedu-btn-match' ).click();
		await expect( page.locator( '#radioedu-match-drawer' ) ).toHaveClass(
			/is-open/
		);
		const sender = page.locator( 'select#radioedu-match-emisor' );
		if ( await sender.count() ) {
			const options = await sender
				.locator( 'option' )
				.evaluateAll(
					( nodes, skip ) =>
						nodes
							.map( ( node ) => node.value )
							.filter( ( value ) => value && value !== skip ),
					idA
				);
			await sender.selectOption( options[ 0 ] );
		}
		await page.fill( '#radioedu-match-idea', 'Idea para la radio A' );
		await page.click( '#radioedu-btn-submit-match' );
		await expect.poll( () => posted ).not.toBeNull();
		await page.click( '#radioedu-btn-close-drawer' );

		// Open it for B straight away, inside the closing animation, and start a draft.
		await cardB.locator( '.radioedu-btn-match' ).click();
		await page.fill( '#radioedu-match-idea', 'Borrador para la radio B' );

		// The reply for A arrives now.
		const reply = page.waitForResponse(
			( response ) =>
				response
					.url()
					.includes( '/radioedu-conecta/v1/colaboraciones' ) &&
				'POST' === response.request().method()
		);
		release();
		await reply;

		expect( String( posted.centro_receptor_id ) ).toBe( idA );
		await expect( page.locator( '#radioedu-match-drawer' ) ).toBeVisible();
		await expect( page.locator( '#radioedu-match-receptor' ) ).toHaveValue(
			idB
		);
		await expect(
			page.locator( '#radioedu-match-receptor-txt' )
		).toHaveValue( new RegExp( `^${ PREFIX } 02` ) );
		await expect( page.locator( '#radioedu-match-idea' ) ).toHaveValue(
			'Borrador para la radio B'
		);
		await expect(
			page.locator( '#radioedu-drawer-feedback' )
		).not.toContainText( 'Enviada a la radio A' );
		await expect(
			page.locator( '#radioedu-btn-submit-match' )
		).toBeEnabled();
	} );

	test( 'las directivas escritas en el contenido no usan el store del plugin', async ( {
		page,
		requestUtils,
	} ) => {
		const posted = [];
		await page.route(
			'**/radioedu-conecta/v1/colaboraciones**',
			async ( route ) => {
				if ( 'POST' !== route.request().method() ) {
					return route.continue();
				}
				posted.push( route.request().postDataJSON() );
				await route.fulfill( {
					status: 201,
					contentType: 'application/json',
					body: JSON.stringify( { success: true, id: 999 } ),
				} );
			}
		);

		// A hover over the fieldset would send a proposal, and the button would open the panel
		// for a listed station under another name.
		const store = 'data-wp-interactive="radioedu-conecta/directorio"';
		const trap =
			`<fieldset id="trap" ${ store } data-wp-on--mouseover="actions.submitProposal" style="padding:120px;border:0">` +
			`<textarea name="centro_emisor_id" style="display:none">${ centroIds[ 1 ] }</textarea>` +
			'<textarea name="idea_proyecto" style="display:none">Idea que nadie ha escrito</textarea></fieldset>';
		const spoof =
			`<button id="spoof" ${ store } data-wp-context='{"centro":{"id":${ centroIds[ 0 ] },"nombre":"Nombre falso"}}' ` +
			'data-wp-on--mouseover="actions.openDrawer" style="padding:40px">Pasa el ratón</button>';
		const centro = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/centros',
			data: {
				title: 'Radio E2E con directivas',
				status: 'publish',
				content: trap,
			},
		} );
		const pageWithTraps = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: 'Directorio E2E con directivas',
				status: 'publish',
				content: `<!-- wp:radioedu-conecta/directorio /-->${ spoof }${ trap }`,
			},
		} );

		// Requests are routed in the order they leave, so once the proposal sent through the
		// panel is in, one sent earlier by a hover would be in as well.
		const ideas = () => posted.map( ( item ) => item.idea_proyecto );
		const sendRealProposal = async ( idea ) => {
			await expect(
				page.locator( '#radioedu-match-drawer' )
			).toHaveClass( /is-open/ );
			const receiver = await page
				.locator( '#radioedu-match-receptor' )
				.inputValue();
			const sender = page.locator( 'select#radioedu-match-emisor' );
			if ( await sender.count() ) {
				const options = await sender
					.locator( 'option' )
					.evaluateAll(
						( nodes, skip ) =>
							nodes
								.map( ( node ) => node.value )
								.filter( ( value ) => value && value !== skip ),
						receiver
					);
				await sender.selectOption( options[ 0 ] );
			}
			await page.fill( '#radioedu-match-idea', idea );
			await page.click( '#radioedu-btn-submit-match' );
			await expect.poll( ideas ).toContain( idea );
			await page.click( '#radioedu-btn-close-drawer' );
		};

		try {
			// The profile description loses the region of the plugin store.
			await page.goto( centro.link );
			const match = page.locator( '#radioedu-single-btn-match' );
			await expect( match ).toBeVisible();
			await expect( page.locator( '#trap' ) ).not.toHaveAttribute(
				'data-wp-interactive',
				/.*/
			);
			await page.locator( '#trap' ).hover( { position: { x: 5, y: 5 } } );
			await match.click();
			await sendRealProposal( 'Propuesta desde la ficha' );
			expect( ideas() ).toEqual( [ 'Propuesta desde la ficha' ] );

			// Next to the directory the directives stay, but the store ignores them.
			await page.goto( pageWithTraps.link );
			const card = page.locator( '.radioedu-card', {
				hasText: `${ PREFIX } 02`,
			} );
			await expect( card.locator( '.radioedu-btn-match' ) ).toBeVisible();
			await page.locator( '#spoof' ).hover();
			await expect(
				page.locator( '#radioedu-match-receptor-txt' )
			).toHaveValue( new RegExp( `^${ PREFIX } 01` ) );
			await page.mouse.move( 5, 5 );
			await page.keyboard.press( 'Escape' );
			await expect(
				page.locator( '#radioedu-match-drawer' )
			).toBeHidden();
			await page.locator( '#trap' ).hover( { position: { x: 5, y: 5 } } );
			await card.locator( '.radioedu-btn-match' ).click();
			await sendRealProposal( 'Propuesta desde el directorio' );
			expect( ideas() ).toEqual( [
				'Propuesta desde la ficha',
				'Propuesta desde el directorio',
			] );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ pageWithTraps.id }`,
				params: { force: true },
			} );
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/centros/${ centro.id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'a 320 px de ancho no hay desplazamiento horizontal', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 320, height: 800 } );
		await page.goto( directoryUrl );
		await expect( page.locator( '.radioedu-card' ).first() ).toBeVisible();

		const layout = await page.evaluate( () => {
			const width = document.documentElement.clientWidth;
			const outside = [];
			document
				.querySelectorAll( '#radioedu-conecta-app *' )
				.forEach( ( element ) => {
					const box = element.getBoundingClientRect();
					if (
						box.width > 0 &&
						( box.right > width + 1 || box.left < -1 )
					) {
						outside.push(
							String( element.className ).split( ' ' )[ 0 ]
						);
					}
				} );
			return {
				width,
				scrollWidth: document.documentElement.scrollWidth,
				outside: [ ...new Set( outside ) ],
			};
		} );

		expect( layout.outside ).toEqual( [] );
		expect( layout.scrollWidth ).toBeLessThanOrEqual( layout.width );
	} );

	test( 'sin JavaScript muestra las radios, busca y pasa de página', async ( {
		browser,
		requestUtils,
	} ) => {
		const directory = await requestUtils.rest( {
			path: '/radioedu-conecta/v1/centros',
			params: { per_page: 1 },
		} );

		const context = await browser.newContext( {
			javaScriptEnabled: false,
		} );
		try {
			const noScript = await context.newPage();
			await noScript.goto( directoryUrl );
			const cards = noScript.locator( '.radioedu-card' );

			// The server renders the first page, with the real count.
			await expect(
				noScript.locator( '#radioedu-count-number' )
			).toHaveText( String( directory.total ) );
			await expect( cards ).toHaveCount( CARDS_PER_PAGE );
			await expect(
				noScript.locator( '#radioedu-js-fallback' )
			).toBeVisible();
			// What needs JavaScript is not offered.
			await expect(
				noScript.locator( '#radioedu-btn-toggle-map' )
			).toBeHidden();
			await expect(
				cards.first().locator( '.radioedu-btn-match' )
			).toBeHidden();
			await expect(
				cards.first().locator( '.radioedu-card-fav-btn' )
			).toBeHidden();

			// The pages of results are links.
			await noScript.click( '#radioedu-btn-load-more' );
			expect(
				new URL( noScript.url() ).searchParams.get( 'pagina' )
			).toBe( '2' );
			await expect( cards ).toHaveCount(
				Math.min( directory.total - CARDS_PER_PAGE, CARDS_PER_PAGE )
			);
			await expect(
				noScript.locator( '#radioedu-btn-load-prev' )
			).toBeVisible();

			// The filters form is sent by GET and the server applies it.
			await noScript.fill( '#radioedu-filter-search', `${ PREFIX } 07` );
			await noScript.click( '#radioedu-btn-buscar' );
			expect(
				new URL( noScript.url() ).searchParams.get( 'buscar' )
			).toBe( `${ PREFIX } 07` );
			expect(
				new URL( noScript.url() ).searchParams.get( 'pagina' )
			).toBeNull();
			await expect( cards ).toHaveCount( 1 );
			await expect( cards ).toContainText( `${ PREFIX } 07` );
			await expect(
				noScript.locator( '#radioedu-filter-search' )
			).toHaveValue( `${ PREFIX } 07` );
		} finally {
			await context.close();
		}
	} );
} );
