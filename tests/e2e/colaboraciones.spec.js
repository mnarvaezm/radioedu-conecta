/**
 * Proposals between two coordinators, end to end and without intercepting the requests: sending
 * from the directory, answering from the Colaboraciones screen, the contact data shared only after
 * acceptance, a retry after a lost reply, the panel for visitors who cannot propose, the public
 * data that never carries the coordinator, and the radios per user limit.
 *
 * The tests create their own users, stations and directory page and delete them afterwards. The
 * proposals stay in the plugin table, which has no deletion endpoint: run them only on a
 * disposable site. Emails may fail on a test site; the messages say so and the tests accept it.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const RUN = Date.now().toString( 36 );
const PREFIX = `Radio E2E colaboraciones ${ RUN }`;
const PASSWORD = `E2e-${ RUN }-clave!`;
const COLLABORATIONS =
	'/wp-admin/edit.php?post_type=radioedu_centro&page=radioedu-conecta-colaboraciones';

const people = {
	a: {
		username: `coord-a-${ RUN }`,
		email: `coord-a-${ RUN }@example.org`,
		contact: `contacto-a-${ RUN }@example.org`,
		name: `Coordinadora A ${ RUN }`,
		roles: [ 'author' ],
	},
	b: {
		username: `coord-b-${ RUN }`,
		email: `coord-b-${ RUN }@example.org`,
		contact: `contacto-b-${ RUN }@example.org`,
		name: `Coordinador B ${ RUN }`,
		roles: [ 'author' ],
	},
	subscriber: {
		username: `suscriptor-${ RUN }`,
		email: `suscriptor-${ RUN }@example.org`,
		roles: [ 'subscriber' ],
	},
};

/**
 * Opens a browser context signed in as a user, or anonymous without one.
 *
 * @param {Object}  browser Playwright browser.
 * @param {string}  baseURL Site URL.
 * @param {?Object} person  User, or null for an anonymous visitor.
 * @return {Promise<Object>} Context and page.
 */
async function openAs( browser, baseURL, person ) {
	// Without the administrator session the test config gives every context.
	const context = await browser.newContext( {
		baseURL,
		storageState: { cookies: [], origins: [] },
	} );
	// The admin bar avatar comes from Gravatar, which can hold the load event of every admin page.
	await context.route( /gravatar\.com/, ( route ) => route.abort() );
	const page = await context.newPage();
	if ( person ) {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', person.username );
		await page.fill( '#user_pass', PASSWORD );
		await Promise.all( [
			page.waitForURL( /wp-admin/, { waitUntil: 'domcontentloaded' } ),
			page.click( '#wp-submit' ),
		] );
	}
	return { context, page };
}

test.describe( 'Propuestas de colaboración entre coordinadores', () => {
	test.describe.configure( { mode: 'serial' } );

	const centros = {};
	let directoryPage = null;
	let directoryUrl = '';

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'radioedu-conecta' );
		for ( const person of Object.values( people ) ) {
			const user = await requestUtils.createUser( {
				username: person.username,
				email: person.email,
				password: PASSWORD,
				roles: person.roles,
			} );
			person.id = user.id;
		}
		for ( const key of [ 'a', 'b' ] ) {
			centros[ key ] = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/centros',
				data: {
					title: `${ PREFIX } emisora ${ key.toUpperCase() }`,
					status: 'publish',
					author: people[ key ].id,
					meta: {
						_radioedu_coordinador_email: people[ key ].contact,
						_radioedu_coordinador_nombre: people[ key ].name,
					},
				},
			} );
		}
		directoryPage = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: `Directorio ${ PREFIX }`,
				status: 'publish',
				content: '<!-- wp:radioedu-conecta/directorio /-->',
			},
		} );
		// Only the stations of this file, whatever else the site holds.
		directoryUrl = `${ directoryPage.link }${
			directoryPage.link.includes( '?' ) ? '&' : '?'
		}buscar=${ encodeURIComponent( PREFIX ) }`;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( directoryPage ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ directoryPage.id }`,
				params: { force: true },
			} );
		}
		for ( const centro of Object.values( centros ) ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/centros/${ centro.id }`,
				params: { force: true },
			} );
		}
		for ( const person of Object.values( people ) ) {
			if ( person.id ) {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `/wp/v2/users/${ person.id }`,
					params: { force: true, reassign: 1 },
				} );
			}
		}
	} );

	/**
	 * Proposals stored with an idea, read as an administrator.
	 *
	 * @param {Object} requestUtils Request utilities.
	 * @param {string} idea         Project idea.
	 * @return {Promise<Array<Object>>} Proposals.
	 */
	const proposalsWith = async ( requestUtils, idea ) => {
		const data = await requestUtils.rest( {
			path: '/radioedu-conecta/v1/colaboraciones',
			params: { per_page: 100 },
		} );
		return data.colaboraciones.filter(
			( proposal ) => proposal.idea_proyecto === idea
		);
	};

	/**
	 * Opens the proposal panel for the station B card and fills in a proposal from station A.
	 *
	 * @param {Object} page Page signed in as coordinator A.
	 * @param {string} idea Project idea.
	 */
	const writeProposalToB = async ( page, idea ) => {
		await page.goto( directoryUrl );
		const card = page.locator( '.radioedu-card', {
			hasText: `${ PREFIX } emisora B`,
		} );
		await card.locator( '.radioedu-btn-match' ).click();
		await expect( page.locator( '#radioedu-match-drawer' ) ).toHaveClass(
			/is-open/
		);
		await expect(
			page.locator( '#radioedu-match-receptor-txt' )
		).toHaveValue( new RegExp( `^${ PREFIX } emisora B` ) );
		await page.selectOption(
			'select#radioedu-match-emisor',
			String( centros.a.id )
		);
		await page.fill( '#radioedu-match-idea', idea );
		await page.fill( '#radioedu-match-edades', '12-14 años' );
	};

	test( 'un coordinador envía una propuesta desde el directorio', async ( {
		browser,
		baseURL,
		requestUtils,
	} ) => {
		const idea = `Podcast conjunto ${ RUN }`;
		const { context, page } = await openAs( browser, baseURL, people.a );
		try {
			// A required field left empty is marked, and nothing is sent.
			await writeProposalToB( page, '' );
			await page.click( '#radioedu-btn-submit-match' );
			await expect(
				page.locator( '#radioedu-match-idea' )
			).toHaveAttribute( 'aria-invalid', 'true' );
			expect( await proposalsWith( requestUtils, '' ) ).toHaveLength( 0 );

			await page.fill( '#radioedu-match-idea', idea );
			await page.click( '#radioedu-btn-submit-match' );
			await expect(
				page.locator( '#radioedu-drawer-feedback' )
			).toContainText( 'enviada con éxito' );

			const [ proposal ] = await proposalsWith( requestUtils, idea );
			expect( proposal ).toMatchObject( {
				centro_emisor_id: centros.a.id,
				centro_receptor_id: centros.b.id,
				curso_edades: '12-14 años',
				estado: 'pendiente',
			} );

			// The sender sees it as pending, with no contact data and no way to answer it.
			await page.goto( COLLABORATIONS );
			const row = page.locator( 'tr', { hasText: idea } );
			await expect( row ).toContainText( 'Pendiente' );
			await expect( row.locator( 'a[href^="mailto:"]' ) ).toHaveCount(
				0
			);
			await expect(
				row.getByRole( 'button', { name: 'Aceptar' } )
			).toHaveCount( 0 );
		} finally {
			await context.close();
		}
	} );

	test( 'el receptor la acepta y solo entonces se comparten los contactos', async ( {
		browser,
		baseURL,
		requestUtils,
	} ) => {
		const idea = `Podcast conjunto ${ RUN }`;
		const reply = `Aceptamos, hablamos pronto ${ RUN }`;
		const receiver = await openAs( browser, baseURL, people.b );
		try {
			await receiver.page.goto( COLLABORATIONS );
			const row = receiver.page.locator( 'tr', { hasText: idea } );
			await expect( row ).toContainText( 'Pendiente' );
			await expect( row ).not.toContainText( people.a.contact );

			await row
				.locator( 'textarea[name="respuesta_mensaje"]' )
				.fill( reply );
			await Promise.all( [
				receiver.page.waitForURL( /match_updated=aceptada/, {
					waitUntil: 'domcontentloaded',
				} ),
				row.getByRole( 'button', { name: 'Aceptar' } ).click(),
			] );
			await expect(
				receiver.page.locator( '.notice', {
					hasText:
						'La respuesta a la propuesta de colaboración se ha guardado',
				} )
			).toBeVisible();
			const answered = receiver.page.locator( 'tr', { hasText: idea } );
			await expect( answered ).toContainText( 'Aceptada' );
			await expect( answered ).toContainText( reply );
			await expect(
				answered.locator( `a[href="mailto:${ people.a.contact }"]` )
			).toBeVisible();
		} finally {
			await receiver.context.close();
		}

		const [ proposal ] = await proposalsWith( requestUtils, idea );
		expect( proposal.estado ).toBe( 'aceptada' );
		expect( proposal.respuesta_mensaje ).toBe( reply );

		// The sender now sees the receiver's contact and the reply.
		const sender = await openAs( browser, baseURL, people.a );
		try {
			await sender.page.goto( COLLABORATIONS );
			const row = sender.page.locator( 'tr', { hasText: idea } );
			await expect( row ).toContainText( 'Aceptada' );
			await expect( row ).toContainText( reply );
			await expect(
				row.locator( `a[href="mailto:${ people.b.contact }"]` )
			).toBeVisible();
		} finally {
			await sender.context.close();
		}
	} );

	test( 'una respuesta perdida no duplica la propuesta al reintentar', async ( {
		browser,
		baseURL,
		requestUtils,
	} ) => {
		const idea = `Segunda idea ${ RUN }`;
		const { context, page } = await openAs( browser, baseURL, people.a );
		try {
			// The first proposal reaches the server, but its reply is lost on the way back.
			let lost = false;
			await page.route(
				'**/radioedu-conecta/v1/colaboraciones**',
				async ( route ) => {
					if ( 'POST' !== route.request().method() || lost ) {
						return route.continue();
					}
					lost = true;
					await route.fetch();
					return route.abort( 'failed' );
				}
			);
			await writeProposalToB( page, idea );
			await page.click( '#radioedu-btn-submit-match' );
			await expect(
				page.locator( '#radioedu-drawer-feedback' )
			).toContainText( 'Ha ocurrido un error' );
			await expect(
				page.locator( '#radioedu-btn-submit-match' )
			).toBeEnabled();

			await page.click( '#radioedu-btn-submit-match' );
			await expect(
				page.locator( '#radioedu-drawer-feedback' )
			).toContainText( 'enviada con éxito' );
			expect( await proposalsWith( requestUtils, idea ) ).toHaveLength(
				1
			);
		} finally {
			await context.close();
		}
	} );

	test( 'quien no puede proponer ve por qué y no puede enviar', async ( {
		browser,
		baseURL,
	} ) => {
		const openPanel = async ( page ) => {
			await page.goto( directoryUrl );
			await page
				.locator( '.radioedu-card', {
					hasText: `${ PREFIX } emisora B`,
				} )
				.locator( '.radioedu-btn-match' )
				.click();
			await expect(
				page.locator( '#radioedu-match-drawer' )
			).toHaveClass( /is-open/ );
		};

		const subscriber = await openAs( browser, baseURL, people.subscriber );
		try {
			await openPanel( subscriber.page );
			await expect(
				subscriber.page.locator( '#radioedu-drawer-feedback' )
			).toContainText( 'no dispone de permisos de coordinador' );
			await expect(
				subscriber.page.locator( '#radioedu-btn-submit-match' )
			).toBeDisabled();
		} finally {
			await subscriber.context.close();
		}

		const visitor = await openAs( browser, baseURL, null );
		try {
			await openPanel( visitor.page );
			const feedback = visitor.page.locator(
				'#radioedu-drawer-feedback'
			);
			await expect( feedback ).toContainText( 'Debes iniciar sesión' );
			await expect(
				feedback.getByRole( 'link', { name: 'Iniciar sesión' } )
			).toHaveAttribute( 'href', /wp-login\.php/ );
			await expect(
				visitor.page.locator( '#radioedu-btn-submit-match' )
			).toBeDisabled();
		} finally {
			await visitor.context.close();
		}
	} );

	test( 'los datos del coordinador no salen en la web ni en la REST pública', async ( {
		browser,
		baseURL,
	} ) => {
		const visitor = await openAs( browser, baseURL, null );
		try {
			// Each body is read before the next navigation, which discards it.
			const bodies = [];
			for ( const load of [
				() => visitor.page.goto( directoryUrl ),
				() => visitor.page.goto( centros.a.link ),
				() =>
					visitor.context.request.get(
						'/?rest_route=/radioedu-conecta/v1/centros&per_page=100'
					),
			] ) {
				const response = await load();
				expect( response.status() ).toBe( 200 );
				bodies.push( await response.text() );
			}
			expect( bodies[ 2 ] ).toContain( `${ PREFIX } emisora A` );
			for ( const body of bodies ) {
				for ( const secret of [
					people.a.contact,
					people.a.name,
					people.b.contact,
					people.b.name,
				] ) {
					expect( body ).not.toContain( secret );
				}
			}
		} finally {
			await visitor.context.close();
		}
	} );

	test( 'el límite de radios por usuario impide dar de alta otra', async ( {
		admin,
		page,
		browser,
		baseURL,
	} ) => {
		const setLimit = async ( value ) => {
			await admin.visitAdminPage(
				'edit.php',
				'post_type=radioedu_centro&page=radioedu-conecta-settings'
			);
			await page.fill( '#radioedu_max_centros_per_user', value );
			await Promise.all( [
				page.waitForURL( /settings-updated=true/, {
					waitUntil: 'domcontentloaded',
				} ),
				page.click( '#submit' ),
			] );
		};

		await setLimit( '1' );
		const coordinator = await openAs( browser, baseURL, people.a );
		try {
			await coordinator.page.goto(
				'/wp-admin/edit.php?post_type=radioedu_centro'
			);
			await expect(
				coordinator.page.locator( '.notice', {
					hasText: 'Has alcanzado el máximo de 1 radio por usuario',
				} )
			).toBeVisible();
			await expect(
				coordinator.page.locator( '.page-title-action' )
			).toHaveCount( 0 );
			const response = await coordinator.page.goto(
				'/wp-admin/post-new.php?post_type=radioedu_centro'
			);
			expect( response.status() ).toBe( 403 );
		} finally {
			await coordinator.context.close();
			await setLimit( '' );
		}
	} );
} );
