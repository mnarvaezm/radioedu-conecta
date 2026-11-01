/**
 * Network admin page: syncs the fichas of every network site in batches.
 *
 * Calls POST /radioedu-conecta/v1/network-sync in a loop with the `next_offset` of each
 * response, shows the progress and prints a summary built with DOM nodes (no innerHTML).
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { speak } from '@wordpress/a11y';
import { __, _n, sprintf } from '@wordpress/i18n';

const settings = window.radioeduConectaNetworkSync || {};

/**
 * Creates an element with text content.
 *
 * @param {string} tag  Tag name.
 * @param {string} text Text content.
 * @return {HTMLElement} Element.
 */
function el( tag, text = '' ) {
	const node = document.createElement( tag );
	node.textContent = text;
	return node;
}

/**
 * Returns the URL when it is an http(s) URL, or an empty string.
 *
 * @param {string} url URL from the server.
 * @return {string} Safe URL.
 */
function safeUrl( url ) {
	try {
		const parsed = new URL( url, window.location.href );
		return [ 'http:', 'https:' ].includes( parsed.protocol )
			? parsed.href
			: '';
	} catch {
		return '';
	}
}

/**
 * Returns a link when the URL is http(s), or plain text otherwise.
 *
 * @param {string} url  URL from the server.
 * @param {string} text Link text.
 * @return {Node} Link or text node.
 */
function linkOrText( url, text ) {
	const safe = safeUrl( url );
	if ( ! safe ) {
		return document.createTextNode( text );
	}
	const link = el( 'a', text );
	link.href = safe;
	return link;
}

/**
 * Builds the table of the stations synced in this run.
 *
 * @param {Array} synced Fichas returned by every batch.
 * @return {HTMLElement} Section with a heading and the table.
 */
function buildSyncedTable( synced ) {
	const outcomes = {
		created: __( 'Creada', 'radioedu-conecta' ),
		updated: __( 'Actualizada', 'radioedu-conecta' ),
		reused: __( 'Reutilizada (ficha hecha a mano)', 'radioedu-conecta' ),
		skipped: __( 'Omitida (en la papelera)', 'radioedu-conecta' ),
	};

	const section = el( 'div' );
	section.className = 'radioedu-network-sync-list';
	section.append(
		el(
			'h2',
			sprintf(
				/* translators: %d: number of synced stations. */
				_n(
					'%d emisora sincronizada',
					'%d emisoras sincronizadas',
					synced.length,
					'radioedu-conecta'
				),
				synced.length
			)
		)
	);
	if ( ! synced.length ) {
		return section;
	}

	const table = el( 'table' );
	table.className = 'widefat striped';
	const caption = el(
		'caption',
		__( 'Resultado de la sincronización por emisora', 'radioedu-conecta' )
	);
	caption.className = 'screen-reader-text';
	table.append( caption );

	const headRow = el( 'tr' );
	[
		__( 'Emisora', 'radioedu-conecta' ),
		__( 'Sitio de origen', 'radioedu-conecta' ),
		__( 'Resultado', 'radioedu-conecta' ),
		__( 'Rol Autor añadido', 'radioedu-conecta' ),
	].forEach( ( label ) => {
		const th = el( 'th', label );
		th.scope = 'col';
		headRow.append( th );
	} );
	const thead = el( 'thead' );
	thead.append( headRow );
	table.append( thead );

	const tbody = el( 'tbody' );
	synced.forEach( ( ficha ) => {
		const row = el( 'tr' );
		const name = el( 'td' );
		name.append(
			linkOrText(
				ficha.edit_url,
				ficha.title ||
					/* translators: %d: ficha ID. */
					sprintf( __( 'Ficha %d', 'radioedu-conecta' ), ficha.id )
			)
		);
		const site = el( 'td' );
		site.append( linkOrText( ficha.site_url, ficha.site_url ) );
		row.append(
			name,
			site,
			el( 'td', outcomes[ ficha.outcome ] || ficha.outcome ),
			el(
				'td',
				ficha.author_added
					? __( 'Sí', 'radioedu-conecta' )
					: __( 'No', 'radioedu-conecta' )
			)
		);
		tbody.append( row );
	} );
	table.append( tbody );
	section.append( table );

	return section;
}

/**
 * Builds the summary notice.
 *
 * @param {Object} totals  Accumulated counts and errors.
 * @param {Array}  missing Fichas whose source site is gone.
 * @return {HTMLElement} Notice.
 */
function buildSummary( totals, missing ) {
	const hasProblems = totals.errors.length > 0 || missing.length > 0;
	const notice = el( 'div' );
	notice.className = `notice ${
		hasProblems ? 'notice-warning' : 'notice-success'
	}`;

	notice.append(
		el( 'p', __( 'Sincronización terminada.', 'radioedu-conecta' ) )
	);

	const counts = el( 'ul' );
	[
		/* translators: %d: number of fichas. */
		_n(
			'%d ficha creada.',
			'%d fichas creadas.',
			totals.created,
			'radioedu-conecta'
		),
		/* translators: %d: number of fichas. */
		_n(
			'%d ficha actualizada.',
			'%d fichas actualizadas.',
			totals.updated,
			'radioedu-conecta'
		),
		/* translators: %d: number of fichas. */
		_n(
			'%d ficha creada a mano reutilizada.',
			'%d fichas creadas a mano reutilizadas.',
			totals.reused,
			'radioedu-conecta'
		),
		/* translators: %d: number of fichas. */
		_n(
			'%d ficha omitida porque está en la papelera.',
			'%d fichas omitidas porque están en la papelera.',
			totals.skipped,
			'radioedu-conecta'
		),
		/* translators: %d: number of coordinators. */
		_n(
			'%d coordinador ha recibido el rol Autor en el sitio principal.',
			'%d coordinadores han recibido el rol Autor en el sitio principal.',
			totals.authorsAdded,
			'radioedu-conecta'
		),
	].forEach( ( format, index ) => {
		const value = [
			totals.created,
			totals.updated,
			totals.reused,
			totals.skipped,
			totals.authorsAdded,
		][ index ];
		counts.append( el( 'li', sprintf( format, value ) ) );
	} );
	notice.append( counts );

	if ( totals.errors.length ) {
		notice.append(
			el(
				'p',
				__(
					'Sitios que no se han podido sincronizar:',
					'radioedu-conecta'
				)
			)
		);
		const list = el( 'ul' );
		totals.errors.forEach( ( error ) => {
			list.append(
				el(
					'li',
					sprintf(
						/* translators: 1: site ID, 2: error message. */
						__( 'Sitio %1$d: %2$s', 'radioedu-conecta' ),
						error.blog_id,
						error.message
					)
				)
			);
		} );
		notice.append( list );
	}

	if ( missing.length ) {
		notice.append(
			el(
				'p',
				__(
					'Fichas cuyo sitio ya no existe (no se han borrado):',
					'radioedu-conecta'
				)
			)
		);
		const list = el( 'ul' );
		missing.forEach( ( ficha ) => {
			const item = el( 'li' );
			const url = safeUrl( ficha.edit_url );
			if ( url ) {
				const link = el( 'a', ficha.title );
				link.href = url;
				item.append( link );
			} else {
				item.append( ficha.title );
			}
			item.append(
				' ',
				sprintf(
					/* translators: %d: ID of the site that no longer exists. */
					__( '(sitio %d)', 'radioedu-conecta' ),
					ficha.blog_id
				)
			);
			list.append( item );
		} );
		notice.append( list );
	}

	return notice;
}

domReady( () => {
	const button = document.getElementById( 'radioedu-network-sync-start' );
	const progress = document.getElementById(
		'radioedu-network-sync-progress'
	);
	const bar = document.getElementById( 'radioedu-network-sync-bar' );
	const status = document.getElementById( 'radioedu-network-sync-status' );
	const result = document.getElementById( 'radioedu-network-sync-result' );
	const grantAuthor = document.getElementById(
		'radioedu-network-sync-grant-author'
	);
	if (
		! button ||
		! grantAuthor ||
		! progress ||
		! bar ||
		! status ||
		! result ||
		! settings.path
	) {
		return;
	}

	button.addEventListener( 'click', async () => {
		// The same choice applies to every batch of this run.
		const grantAuthorRole = grantAuthor.checked;
		button.disabled = true;
		grantAuthor.disabled = true;
		progress.hidden = false;
		result.replaceChildren();
		bar.value = 0;
		status.textContent = __( 'Sincronizando…', 'radioedu-conecta' );

		const totals = {
			created: 0,
			updated: 0,
			reused: 0,
			skipped: 0,
			authorsAdded: 0,
			synced: [],
			errors: [],
		};
		let missing = [];
		let offset = 0;

		try {
			for (;;) {
				const batch = await apiFetch( {
					path: settings.path,
					method: 'POST',
					data: { offset, grant_author_role: grantAuthorRole },
				} );
				totals.created += batch.created;
				totals.updated += batch.updated;
				totals.reused += batch.reused;
				totals.skipped += batch.skipped;
				totals.authorsAdded += batch.authors_added;
				totals.errors.push( ...batch.errors );
				totals.synced.push( ...( batch.synced || [] ) );

				bar.max = Math.max( 1, batch.total );
				bar.value = Math.min( batch.next_offset, bar.max );
				status.textContent = sprintf(
					/* translators: 1: processed sites, 2: total sites. */
					__( 'Procesados %1$d de %2$d sitios…', 'radioedu-conecta' ),
					Math.min( batch.next_offset, batch.total ),
					batch.total
				);

				// Stop when done, or if the offset stops moving (sites removed meanwhile).
				if ( batch.done || batch.next_offset <= offset ) {
					missing = batch.missing || [];
					break;
				}
				offset = batch.next_offset;
			}

			result.append(
				buildSummary( totals, missing ),
				buildSyncedTable( totals.synced )
			);
			status.textContent = __(
				'Sincronización terminada.',
				'radioedu-conecta'
			);
			speak(
				sprintf(
					/* translators: 1: created fichas, 2: updated fichas, 3: problems. */
					__(
						'Sincronización terminada: %1$d creadas, %2$d actualizadas, %3$d avisos.',
						'radioedu-conecta'
					),
					totals.created,
					totals.updated + totals.reused,
					totals.errors.length + missing.length
				)
			);
		} catch ( error ) {
			const message = sprintf(
				/* translators: %s: error message. */
				__(
					'La sincronización se ha detenido: %s',
					'radioedu-conecta'
				),
				error?.message || __( 'error desconocido', 'radioedu-conecta' )
			);
			const notice = el( 'div' );
			notice.className = 'notice notice-error';
			notice.append( el( 'p', message ) );
			result.append( notice );
			status.textContent = message;
			speak( message, 'assertive' );
		} finally {
			button.disabled = false;
			grantAuthor.disabled = false;
		}
	} );
} );
