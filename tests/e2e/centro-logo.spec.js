/**
 * Logo picker of the centro meta box in the block editor.
 *
 * WordPress 7.1 always renders the editor canvas in an iframe, also when a post type keeps
 * classic meta boxes; the meta box and the media modal stay in the outer document.
 */
const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const LOGO_FILE = path.join(
	__dirname,
	'../../assets/images/demo/logo-sierra-gata.jpg'
);

test.describe( 'Logo de la emisora en el editor de centros', () => {
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

	test( 'se elige en la biblioteca de medios, se guarda y se muestra en la ficha', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( {
			postType: 'radioedu_centro',
			title: 'Centro E2E con logo',
		} );
		centroIds.push(
			await page.evaluate( () =>
				window.wp.data.select( 'core/editor' ).getCurrentPostId()
			)
		);
		// The canvas is iframed even though the post type has a classic meta box.
		await expect(
			page.locator( 'iframe[name="editor-canvas"]' )
		).toBeAttached();
		// Meta boxes live in a collapsible pane below the canvas, closed by default. The welcome
		// guide is dismissed again because a new user's stored preferences can load late.
		await editor.setPreferences( 'core/edit-post', {
			metaBoxesMainIsOpen: true,
			welcomeGuide: false,
		} );

		await page
			.locator( '#radioedu_centro_detalles' )
			.getByRole( 'button', { name: 'Subir / Elegir Logo' } )
			.click();
		const modal = page.locator( '.media-modal:visible' );
		// Core tab ids instead of labels, which depend on the site language.
		await modal.locator( '#menu-item-browse' ).click();
		await modal.locator( `li.attachment[data-id="${ media.id }"]` ).click();
		await modal.getByRole( 'button', { name: 'Usar este logo' } ).click();

		await expect( page.locator( '#radioedu_logo_id' ) ).toHaveValue(
			String( media.id )
		);
		await expect(
			page.locator( '#radioedu-logo-preview img' )
		).toBeVisible();

		// The editor submits the meta box form after saving the post through REST.
		const metaBoxSaved = page.waitForResponse( ( response ) =>
			response.url().includes( 'meta-box-loader=1' )
		);
		await editor.publishPost();
		await metaBoxSaved;

		await page.reload();
		await expect( page.locator( '#radioedu_logo_id' ) ).toHaveValue(
			String( media.id )
		);

		const permalink = await page.evaluate( () =>
			window.wp.data.select( 'core/editor' ).getPermalink()
		);
		await page.goto( permalink );
		await expect(
			page.locator( 'img.radioedu-profile-logo' )
		).toHaveAttribute( 'src', /logo-sierra-gata/ );
	} );
} );
