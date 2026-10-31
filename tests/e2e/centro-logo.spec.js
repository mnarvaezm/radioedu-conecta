/**
 * Centro fields in the block editor sidebar panels.
 *
 * The panels replace the classic meta box in the block editor and save the registered meta
 * with the centro through the REST API. The media modal opens in the outer document, outside
 * the iframed editor canvas.
 */
const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const LOGO_FILE = path.join(
	__dirname,
	'../../assets/images/demo/logo-sierra-gata.jpg'
);

// WordPress 7.1 logs this for its own global styles in the iframed editor, with or without the plugin.
const CORE_WARNINGS = [
	/^global-styles-css-custom-properties-inline-css was added to the iframe incorrectly/,
];

test.describe( 'Datos del centro en el panel lateral del editor', () => {
	let media;
	const centroIds = [];

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'radioedu-conecta' );
		media = await requestUtils.uploadMedia( LOGO_FILE );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of centroIds ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/centros/${ id }`,
				params: { force: true },
			} );
		}
		await requestUtils.deleteMedia( media.id );
	} );

	test( 'se rellenan en el panel, se guardan y se muestran en la ficha', async ( {
		admin,
		editor,
		page,
	} ) => {
		const consoleProblems = [];
		page.on( 'console', ( message ) => {
			const text = message.text();
			if (
				[ 'error', 'warning' ].includes( message.type() ) &&
				! CORE_WARNINGS.some( ( pattern ) => pattern.test( text ) )
			) {
				consoleProblems.push( text );
			}
		} );

		await admin.createNewPost( {
			postType: 'radioedu_centro',
			title: 'Centro E2E con logo',
		} );
		centroIds.push(
			await page.evaluate( () =>
				window.wp.data.select( 'core/editor' ).getCurrentPostId()
			)
		);
		// The welcome guide is dismissed again because a new user's stored preferences can load late.
		await editor.setPreferences( 'core/edit-post', {
			welcomeGuide: false,
		} );
		await editor.openDocumentSettingsSidebar();

		// The classic meta box is only for the classic editor.
		await expect( page.locator( '#radioedu_centro_detalles' ) ).toHaveCount(
			0
		);

		// The main panel opens by itself the first time.
		const sidebar = page.getByRole( 'region', { name: 'Editor settings' } );
		await expect(
			sidebar.getByRole( 'button', { name: 'Datos de la emisora' } )
		).toHaveAttribute( 'aria-expanded', 'true' );

		await sidebar
			.getByRole( 'button', { name: 'Subir / Elegir Logo' } )
			.click();
		const modal = page.locator( '.media-modal:visible' );
		// Core ids and classes instead of labels, which depend on the site language.
		await modal.locator( '#menu-item-browse' ).click();
		await modal.locator( `li.attachment[data-id="${ media.id }"]` ).click();
		await modal.locator( '.media-button-select' ).click();
		await expect(
			sidebar.locator( '.radioedu-centro-panel__logo img' )
		).toBeVisible();

		await sidebar
			.getByRole( 'combobox', { name: 'Bandera / Región' } )
			.selectOption( 'es-ex' );
		await sidebar.getByRole( 'button', { name: 'Coordinación' } ).click();
		await sidebar
			.getByRole( 'textbox', { name: 'Email del coordinador/a' } )
			.fill( 'coordinacion-e2e@example.org' );
		await sidebar
			.getByRole( 'button', { name: 'Ubicación en el mapa' } )
			.click();
		await sidebar
			.getByRole( 'spinbutton', { name: 'Latitud' } )
			.fill( '39.4699' );

		await editor.publishPost();
		await page.reload();

		const meta = await page.evaluate( () =>
			window.wp.data
				.select( 'core/editor' )
				.getEditedPostAttribute( 'meta' )
		);
		expect( meta._radioedu_logo_id ).toBe( media.id );
		expect( meta._radioedu_bandera ).toBe( 'es-ex' );
		expect( meta._radioedu_coordinador_email ).toBe(
			'coordinacion-e2e@example.org'
		);
		expect( meta._radioedu_lat ).toBe( '39.4699' );
		// Untouched toggle: still available after saving every key.
		expect( meta._radioedu_disponible_colaborar ).toBe( '1' );

		const permalink = await page.evaluate( () =>
			window.wp.data.select( 'core/editor' ).getPermalink()
		);
		await page.goto( permalink );
		await expect(
			page.locator( 'img.radioedu-profile-logo' )
		).toHaveAttribute( 'src', /logo-sierra-gata/ );
		// The coordinator's email is private.
		await expect( page.locator( 'body' ) ).not.toContainText(
			'coordinacion-e2e@example.org'
		);

		expect( consoleProblems ).toEqual( [] );
	} );
} );
