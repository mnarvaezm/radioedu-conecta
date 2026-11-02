/**
 * Directorio de RadioEdu Conecta: tarjetas, filtros, mapa, favoritos y panel de propuesta.
 *
 * Store of the Interactivity API. The server renders the first page of results with the same
 * directives (DirectoryStore.php), so the page works without JavaScript and this module only
 * takes over what changes in the browser. Nothing is built from HTML strings: the directives
 * write text and attributes, and the map popups are DOM nodes.
 *
 * Translated texts come from the server in the store config, because a script module cannot
 * import `@wordpress/i18n`.
 *
 * @since 1.2
 */
import {
	store,
	getContext,
	getElement,
	getConfig,
	withSyncEvent,
} from '@wordpress/interactivity';

const NAMESPACE = 'radioedu-conecta/directorio';

// Taxonomy filters, with the same name in the page URL and in the REST endpoint.
const FILTER_KEYS = [ 'ubicacion', 'idioma', 'etapa', 'tematica', 'formato' ];
// "search" and "page" are public WordPress query vars, so the page URL uses other names.
const URL_SEARCH = 'buscar';
const URL_ORDER = 'orden';
const URL_PAGE = 'pagina';
const ORDERS = [ 'afines', 'recientes', 'alfa' ];
const TAG_COLORS = [ 'mint', 'coral', 'blue' ];
const PROPOSAL_FIELDS = [
	'idea_proyecto',
	'curso_edades',
	'idiomas',
	'fechas_aprox',
];

// The map fetches the results itself, 100 per request, up to this ceiling: the largest expected
// network has about 1,000 school radios.
const MAP_PAGE_SIZE = 100;
const MAP_MAX_CENTROS = 1000;

// Objects that must not become reactive: requests in flight, Leaflet and the focus to restore.
const runtime = {
	directoryRequest: null,
	mapRequest: null,
	leaflet: null,
	map: null,
	mapVersion: -1,
	markers: [],
	lastFocused: null,
	// Grows on every opening of the panel, so a reply to a proposal sent from an earlier opening is ignored.
	drawerSession: 0,
	drawerTimer: null,
};

/**
 * Returns the store config printed by the server.
 *
 * @return {Object} Config.
 */
function config() {
	return getConfig( NAMESPACE );
}

/**
 * Returns a translated text from the config.
 *
 * @param {string} key Text key.
 * @return {string} Text, or an empty string.
 */
function text( key ) {
	const i18n = config().i18n || {};
	return String( i18n[ key ] || '' );
}

/**
 * Keeps only http(s) URLs, so a javascript: or data: URL never reaches an attribute.
 *
 * @param {string} rawUrl URL to check.
 * @return {string} Absolute URL, or an empty string.
 */
function safeHttpUrl( rawUrl ) {
	if ( ! rawUrl || typeof rawUrl !== 'string' ) {
		return '';
	}
	try {
		const parsed = new URL( rawUrl, window.location.origin );
		if ( parsed.protocol === 'http:' || parsed.protocol === 'https:' ) {
			return parsed.href;
		}
	} catch {
		return '';
	}
	return '';
}

/**
 * Builds a CSS url() value, encoding what could close it, as the server does.
 *
 * @param {string} rawUrl Image URL.
 * @return {string} CSS value, or an empty string.
 */
function cssUrl( rawUrl ) {
	const url = safeHttpUrl( rawUrl );
	if ( ! url ) {
		return '';
	}
	const encoded = url.replace(
		/["'()\\ ]/g,
		( char ) => '%' + char.charCodeAt( 0 ).toString( 16 ).toUpperCase()
	);
	return 'url("' + encoded + '")';
}

/**
 * Formats a number with the thousands separator of the site language, like number_format_i18n().
 *
 * @param {number} value Number.
 * @return {string} Formatted number.
 */
function formatNumber( value ) {
	const separator = String( config().thousandsSep || '' );
	return String( Math.max( 0, Math.trunc( Number( value ) || 0 ) ) ).replace(
		/\B(?=(\d{3})+(?!\d))/g,
		separator
	);
}

/**
 * Replaces %s, %1$s and %2$s in a translated text.
 *
 * @param {string}             template Text with placeholders.
 * @param {...(string|number)} values   Values in order.
 * @return {string} Text.
 */
function format( template, ...values ) {
	let next = 0;
	return String( template ).replace(
		/%(?:(\d)\$)?s/g,
		( match, position ) => {
			const index = position ? Number( position ) - 1 : next++;
			return String( values[ index ] ?? '' );
		}
	);
}

/**
 * Announces a message to screen readers with wp.a11y.speak(), loaded on first use.
 *
 * @param {string} message    Message.
 * @param {string} politeness 'polite' or 'assertive'.
 */
async function announce( message, politeness = 'polite' ) {
	if ( ! message ) {
		return;
	}
	try {
		const { speak } = await import( '@wordpress/a11y' );
		speak( String( message ), politeness );
	} catch {
		// Announcements are an aid; the visible state already changed.
	}
}

/**
 * Key under which this browser keeps the favourites of this site.
 *
 * The sites of a network in subdirectories share one localStorage, so each has its own key.
 *
 * @return {string} Storage key.
 */
function favoritesKey() {
	return String( config().favoritesKey || 'radioedu_favoritos' );
}

/**
 * Reads the favourite stations kept in this browser for this site.
 *
 * @return {Array<number>} Station IDs.
 */
function readFavorites() {
	try {
		const parsed = JSON.parse(
			window.localStorage.getItem( favoritesKey() ) || '[]'
		);
		return Array.isArray( parsed ) ? parsed : [];
	} catch {
		return [];
	}
}

/**
 * Reads the search a form holds now.
 *
 * @param {HTMLFormElement} form Filters form.
 * @return {Object} Filters, free text and order.
 */
function readForm( form ) {
	const data = new window.FormData( form );
	const filters = {};
	FILTER_KEYS.forEach( ( key ) => {
		const value = String( data.get( key ) || '' ).trim();
		if ( value ) {
			filters[ key ] = value;
		}
	} );
	const orden = String( data.get( URL_ORDER ) || 'afines' );
	return {
		filters,
		search: String( data.get( URL_SEARCH ) || '' )
			.trim()
			.slice( 0, 200 ),
		orden: ORDERS.includes( orden ) ? orden : 'afines',
	};
}

/**
 * URL of the centros endpoint with the filters of the search being shown.
 *
 * Every page of a search (more cards, map pages) uses the search captured when it started, not
 * what the form holds now: the user may have edited it without searching.
 *
 * @param {number} page    Page to request.
 * @param {number} perPage Results per page.
 * @param {string} orden   Sort order.
 * @return {string} Absolute URL.
 */
function centrosUrl( page, perPage, orden ) {
	const endpoint = new URL(
		config().restUrl + 'centros',
		window.location.href
	);
	endpoint.searchParams.set( 'page', String( page ) );
	endpoint.searchParams.set( 'per_page', String( perPage ) );
	endpoint.searchParams.set( 'orden', orden );
	// No afines_a: the endpoint picks the user's own centro itself.
	const filters = state.applied.filters || {};
	FILTER_KEYS.forEach( ( key ) => {
		if ( filters[ key ] ) {
			endpoint.searchParams.set( key, filters[ key ] );
		}
	} );
	if ( state.applied.search ) {
		endpoint.searchParams.set( 'search', state.applied.search );
	}
	return endpoint.href;
}

/**
 * URL of this page showing the applied search, as the server reads it without JavaScript.
 *
 * @param {number} page Page of results, 1 for none.
 * @return {string} Absolute URL.
 */
function pageUrl( page ) {
	const url = new URL( window.location.href );
	[ ...FILTER_KEYS, URL_SEARCH, URL_ORDER, URL_PAGE ].forEach( ( key ) =>
		url.searchParams.delete( key )
	);
	const filters = state.applied.filters || {};
	FILTER_KEYS.forEach( ( key ) => {
		if ( filters[ key ] ) {
			url.searchParams.set( key, filters[ key ] );
		}
	} );
	if ( state.applied.search ) {
		url.searchParams.set( URL_SEARCH, state.applied.search );
	}
	if ( state.applied.orden && state.applied.orden !== 'afines' ) {
		url.searchParams.set( URL_ORDER, state.applied.orden );
	}
	if ( page > 1 ) {
		url.searchParams.set( URL_PAGE, String( page ) );
	}
	url.hash = '';
	return url.href;
}

/**
 * Request headers: the REST nonce for signed-in users.
 *
 * @return {Object} Headers.
 */
function fetchHeaders() {
	const { isLoggedIn, restNonce } = config();
	return isLoggedIn && restNonce ? { 'X-WP-Nonce': String( restNonce ) } : {};
}

/**
 * Points the page links at the pages around the ones shown.
 */
function updatePageLinks() {
	state.nextPageUrl = pageUrl( state.page + 1 );
	state.prevPageUrl =
		state.firstPage > 1 ? pageUrl( state.firstPage - 1 ) : '';
}

/**
 * Loads a page of results from the REST API.
 *
 * @param {boolean} append True for the next page of the search shown, false for a new search.
 */
async function fetchCentros( append ) {
	if ( runtime.directoryRequest ) {
		runtime.directoryRequest.abort();
	}
	const request = new window.AbortController();
	runtime.directoryRequest = request;
	const page = append ? state.page + 1 : 1;
	state.hasError = false;
	if ( append ) {
		state.isAppending = true;
	} else {
		state.isSearching = true;
		if ( runtime.mapRequest ) {
			runtime.mapRequest.abort();
			runtime.mapRequest = null;
		}
	}

	try {
		const response = await window.fetch(
			centrosUrl( page, config().perPage || 24, state.applied.orden ),
			{ headers: fetchHeaders(), signal: request.signal }
		);
		if ( ! response.ok ) {
			throw new Error( 'HTTP ' + response.status );
		}
		const data = await response.json();
		if ( request.signal.aborted ) {
			return;
		}
		const fetched = Array.isArray( data.centros ) ? data.centros : [];
		if ( append ) {
			state.centros.push( ...fetched );
		} else {
			state.centros = fetched;
			state.firstPage = 1;
		}
		state.page = page;
		state.totalPages = Number( data.total_pages || 0 );
		state.total = Number( data.total ?? state.centros.length );
		state.totalLabel = formatNumber( state.total );
		updatePageLinks();
		if ( ! append ) {
			state.searchVersion++;
			window.history.replaceState(
				window.history.state,
				'',
				pageUrl( 1 )
			);
			announce( format( text( 'resultsCount' ), state.totalLabel ) );
		}
	} catch ( error ) {
		if ( error.name === 'AbortError' ) {
			return;
		}
		state.hasError = true;
		announce( text( 'errorOccurred' ), 'assertive' );
	} finally {
		if ( runtime.directoryRequest === request ) {
			runtime.directoryRequest = null;
			state.isSearching = false;
			state.isAppending = false;
		}
	}
}

/**
 * Loads Leaflet on first use: the library is downloaded only after the visitor accepts the map.
 *
 * @return {Promise<Object>} The Leaflet namespace.
 */
async function loadLeaflet() {
	if ( ! runtime.leaflet ) {
		const leaflet = await import(
			/* webpackChunkName: "leaflet" */ 'leaflet'
		);
		runtime.leaflet = leaflet.default || leaflet;
	}
	return runtime.leaflet;
}

/**
 * Creates the map in its container, with the attribution built from DOM nodes.
 *
 * @param {Object}      L       Leaflet.
 * @param {HTMLElement} element Map container.
 */
function createMap( L, element ) {
	const center = config().mapCenter || {
		lat: 39.4699,
		lng: -6.3708,
		zoom: 8,
	};
	runtime.map = L.map( element, { attributionControl: false } ).setView(
		[ center.lat, center.lng ],
		center.zoom
	);
	const attribution = L.control( { position: 'bottomright' } );
	attribution.onAdd = function () {
		const container = document.createElement( 'div' );
		container.className = 'leaflet-control-attribution';
		const link = document.createElement( 'a' );
		link.href = 'https://www.openstreetmap.org/copyright';
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = text( 'mapAttribution' );
		container.append( document.createTextNode( 'Leaflet | © ' ), link );
		return container;
	};
	attribution.addTo( runtime.map );
	L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 18,
	} ).addTo( runtime.map );
}

/**
 * Draws a marker for each station with valid coordinates, and says when the map leaves some out.
 *
 * @param {Object}        L       Leaflet.
 * @param {Array<Object>} centros Stations to show.
 */
function showMapCentros( L, centros ) {
	runtime.markers.forEach( ( marker ) => runtime.map.removeLayer( marker ) );
	runtime.markers = [];
	centros.forEach( ( centro ) => {
		const lat = parseFloat( centro.lat );
		const lng = parseFloat( centro.lng );
		if (
			! Number.isFinite( lat ) ||
			! Number.isFinite( lng ) ||
			Math.abs( lat ) > 90 ||
			Math.abs( lng ) > 180
		) {
			return;
		}
		const popup = document.createElement( 'div' );
		const name = document.createElement( 'strong' );
		name.textContent = String( centro.nombre || '' );
		const place = document.createElement( 'span' );
		place.textContent = String( centro.ubicacion || '' );
		const link = document.createElement( 'a' );
		link.href = safeHttpUrl( centro.enlace_perfil ) || '#';
		link.style.color = '#00838f';
		link.style.fontWeight = '600';
		link.textContent = text( 'viewProfile' );
		popup.append(
			name,
			document.createElement( 'br' ),
			place,
			document.createElement( 'br' ),
			link
		);
		runtime.markers.push(
			L.marker( [ lat, lng ] ).addTo( runtime.map ).bindPopup( popup )
		);
	} );
	state.mapNoteText =
		centros.length < state.total
			? format(
					text( 'mapPartial' ),
					formatNumber( centros.length ),
					state.totalLabel
				)
			: '';
}

/**
 * Shows every result of the search on the map, up to MAP_MAX_CENTROS.
 *
 * The cards load 24 at a time, so the map fetches the results itself when the cards do not
 * hold them all.
 *
 * @param {HTMLElement} element Map container.
 * @param {number}      version Search the map is drawn for; a later call makes this one stale.
 */
async function refreshMap( element, version ) {
	runtime.mapVersion = version;
	let L;
	try {
		L = await loadLeaflet();
	} catch {
		state.mapNoteText = text( 'errorOccurred' );
		return;
	}
	if ( version !== runtime.mapVersion ) {
		return;
	}
	if ( ! runtime.map ) {
		createMap( L, element );
	} else {
		runtime.map.invalidateSize();
	}
	if ( runtime.mapRequest ) {
		runtime.mapRequest.abort();
	}

	const wanted = Math.min( state.total, MAP_MAX_CENTROS );
	if ( state.centros.length >= wanted ) {
		showMapCentros( L, state.centros.slice( 0, MAP_MAX_CENTROS ) );
		return;
	}

	const request = new window.AbortController();
	runtime.mapRequest = request;
	const collected = [];
	try {
		for ( let page = 1; collected.length < MAP_MAX_CENTROS; page++ ) {
			const response = await window.fetch(
				centrosUrl( page, MAP_PAGE_SIZE, 'alfa' ),
				{ headers: fetchHeaders(), signal: request.signal }
			);
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			const data = await response.json();
			collected.push(
				...( Array.isArray( data.centros ) ? data.centros : [] )
			);
			if ( page >= Number( data.total_pages || 1 ) ) {
				break;
			}
		}
		if ( ! request.signal.aborted ) {
			showMapCentros( L, collected.slice( 0, MAP_MAX_CENTROS ) );
		}
	} catch {
		// Keep what the cards already loaded rather than an empty map.
		if ( ! request.signal.aborted ) {
			showMapCentros( L, state.centros.slice( 0, MAP_MAX_CENTROS ) );
		}
	} finally {
		if ( runtime.mapRequest === request ) {
			runtime.mapRequest = null;
		}
	}
}

/**
 * Shows a message in the proposal panel.
 *
 * @param {string}  message Message.
 * @param {boolean} success Whether it reports a success.
 */
function showFeedback( message, success ) {
	state.drawer.feedback = String( message || '' );
	state.drawer.isSuccess = Boolean( success );
	state.drawer.showLogin = false;
}

/**
 * Moves the focus to the first field of the open panel.
 */
function focusDrawer() {
	const panel = document.getElementById( 'radioedu-match-drawer' );
	if ( ! panel ) {
		return;
	}
	const field = panel.querySelector(
		'select:not([disabled]), textarea, input:not([type="hidden"]):not([disabled]):not([readonly])'
	);
	( field || panel ).focus();
}

/**
 * Finds the station a proposal may be addressed to.
 *
 * The client runtime also runs directives written in content the plugin does not control, such
 * as a station description, and those can set any context. The receiver must therefore be a card
 * of the results or the station of the profile, and its name comes from the server state.
 *
 * @param {number} id Station ID given by the context.
 * @return {?{id: number, label: string}} Receiver, or null when the server did not list it.
 */
function proposalReceiver( id ) {
	if ( ! id ) {
		return null;
	}
	const centro = state.centros.find( ( item ) => item.id === id );
	if ( centro ) {
		return {
			id,
			label:
				String( centro.nombre || '' ) +
				( centro.ubicacion ? ' (' + centro.ubicacion + ')' : '' ),
		};
	}
	return id === profileReceiver.id ? profileReceiver : null;
}

const { state, actions } = store( NAMESPACE, {
	state: {
		// Only what the server never sets: a value given here overrides the server state.
		hasJs: true,
		isSearching: false,
		isAppending: false,
		hasError: false,
		searchVersion: 0,
		favorites: [],
		isMapView: false,
		hasMapConsent: false,
		mapNoteText: '',
		drawer: {
			isVisible: false,
			isOpen: false,
			feedback: '',
			isSuccess: false,
			showLogin: false,
			isBlocked: false,
			isSubmitting: false,
		},

		get cardsHidden() {
			return (
				state.isMapView ||
				state.isSearching ||
				state.centros.length === 0
			);
		},
		get noResultsHidden() {
			return (
				state.isSearching || state.hasError || state.centros.length > 0
			);
		},
		get loadingHidden() {
			return ! state.isSearching && ! state.isAppending;
		},
		get nextHidden() {
			return state.page >= state.totalPages;
		},
		get prevHidden() {
			return state.firstPage <= 1;
		},
		get paginationHidden() {
			return (
				state.isMapView ||
				state.isSearching ||
				( state.nextHidden && state.prevHidden )
			);
		},

		// Card values, read from the station of the card.
		get coverStyle() {
			return cssUrl( getContext().centro.foto_cabecera );
		},
		get logoSrc() {
			return (
				safeHttpUrl( getContext().centro.logo_radio ) ||
				String( config().defaultLogo || '' )
			);
		},
		get flag() {
			const flags = config().flags || {};
			return flags[ getContext().centro.bandera ] || '📻';
		},
		get locationText() {
			return getContext().centro.ubicacion || text( 'defaultLocation' );
		},
		get cardTags() {
			const { tags } = getContext().centro;
			return Array.isArray( tags ) ? tags.slice( 0, 5 ) : [];
		},
		get tagClass() {
			const { color } = getContext().tag;
			return (
				'radioedu-tag radioedu-tag-' +
				( TAG_COLORS.includes( color ) ? color : 'mint' )
			);
		},
		get profileUrl() {
			return safeHttpUrl( getContext().centro.enlace_perfil ) || '#';
		},
		get isFavorite() {
			return state.favorites.includes( getContext().centro.id );
		},
		get favoriteLabel() {
			return text( state.isFavorite ? 'unfavorite' : 'favorite' );
		},
		get isUnavailable() {
			return getContext().centro.disponible === false;
		},
		// Without JavaScript the server hides the proposal buttons; here they always show.
		get matchHidden() {
			return false;
		},

		get submitDisabled() {
			return state.drawer.isSubmitting || state.drawer.isBlocked;
		},
		get submitLabel() {
			return text(
				state.drawer.isSubmitting ? 'sending' : 'submitProposal'
			);
		},
	},

	actions: {
		search: withSyncEvent( ( event ) => {
			event.preventDefault();
			const form = event.target.form || event.target;
			state.applied = readForm( form );
			fetchCentros( false );
		} ),

		loadMore: withSyncEvent( ( event ) => {
			event.preventDefault();
			if (
				state.isSearching ||
				state.isAppending ||
				state.page >= state.totalPages
			) {
				return;
			}
			fetchCentros( true );
		} ),

		resetFilters: withSyncEvent( ( event ) => {
			event.preventDefault();
			const form = document.getElementById( 'radioedu-filters-form' );
			if ( ! form ) {
				return;
			}
			FILTER_KEYS.concat( URL_SEARCH ).forEach( ( name ) => {
				const field = form.elements.namedItem( name );
				if ( field ) {
					field.value = '';
				}
			} );
			state.applied = readForm( form );
			fetchCentros( false );
		} ),

		toggleFavorite() {
			const { id } = getContext().centro;
			const favorites = state.favorites.includes( id )
				? state.favorites.filter( ( favorite ) => favorite !== id )
				: [ ...state.favorites, id ];
			try {
				window.localStorage.setItem(
					favoritesKey(),
					JSON.stringify( favorites )
				);
				state.favorites = favorites;
			} catch {
				// Without storage the favourite cannot be kept.
			}
		},

		toggleMap() {
			state.isMapView = ! state.isMapView;
		},

		acceptMap() {
			state.hasMapConsent = true;
			try {
				window.sessionStorage.setItem( 'radioedu_osm_consent', '1' );
			} catch {
				// The consent then lasts only while the page stays open.
			}
		},

		withdrawMap() {
			// Without consent the page asks OpenStreetMap for nothing more: the map is removed, and
			// a request or a drawing still pending is dropped.
			state.hasMapConsent = false;
			state.mapNoteText = '';
			runtime.mapVersion = -1;
			if ( runtime.mapRequest ) {
				runtime.mapRequest.abort();
				runtime.mapRequest = null;
			}
			if ( runtime.map ) {
				runtime.map.remove();
				runtime.map = null;
			}
			runtime.markers = [];
			try {
				window.sessionStorage.removeItem( 'radioedu_osm_consent' );
			} catch {
				// Nothing was stored.
			}
			announce( text( 'mapWithdrawn' ) );
			// The button that had the focus is now hidden: the focus goes to the one that accepts again.
			window.requestAnimationFrame( () => {
				const accept = document.getElementById(
					'radioedu-btn-accept-map'
				);
				if ( accept ) {
					accept.focus();
				}
			} );
		},

		openDrawer() {
			const { centro } = getContext();
			const receiver = proposalReceiver(
				Number( centro && centro.id ) || 0
			);
			if ( ! receiver ) {
				return;
			}
			const { isLoggedIn, canPropose } = config();
			const { drawer } = state;
			runtime.drawerSession++;
			window.clearTimeout( runtime.drawerTimer );
			runtime.lastFocused = getElement().ref;

			drawer.isSubmitting = false;
			drawer.receptorId = receiver.id;
			drawer.receptorLabel = receiver.label;

			// Without a session or the capability the panel opens with a notice instead of an alert().
			let blocked = '';
			if ( ! isLoggedIn ) {
				blocked = text( 'loginRequired' );
			} else if ( ! canPropose ) {
				blocked = text( 'noPermission' );
			}
			showFeedback( blocked, false );
			drawer.isBlocked = Boolean( blocked );
			drawer.showLogin = ! isLoggedIn;

			drawer.isVisible = true;
			runtime.drawerTimer = window.setTimeout( () => {
				drawer.isOpen = true;
				focusDrawer();
			}, 10 );
		},

		closeDrawer() {
			const { drawer } = state;
			drawer.isOpen = false;
			window.clearTimeout( runtime.drawerTimer );
			runtime.drawerTimer = window.setTimeout( () => {
				drawer.isVisible = false;
			}, 350 );
			if (
				runtime.lastFocused &&
				typeof runtime.lastFocused.focus === 'function'
			) {
				runtime.lastFocused.focus();
			}
		},

		markInvalid( event ) {
			const field = event.target;
			field.setAttribute( 'aria-invalid', 'true' );
			field.setAttribute(
				'aria-describedby',
				'radioedu-drawer-feedback'
			);
			showFeedback( field.validationMessage, false );
		},

		clearInvalid( event ) {
			event.target.removeAttribute( 'aria-invalid' );
			event.target.removeAttribute( 'aria-describedby' );
		},

		submitProposal: withSyncEvent( function* ( event ) {
			event.preventDefault();
			// Only the panel's own form, when it is submitted. Directives in a description can call
			// this action on any event, but cannot add a form: kses removes it for authors without
			// unfiltered_html.
			const form = event.target;
			if (
				event.type !== 'submit' ||
				! ( form instanceof window.HTMLFormElement ) ||
				form.id !== 'radioedu-match-form'
			) {
				return;
			}
			const { drawer } = state;
			const { isLoggedIn, canPropose, restUrl, restNonce } = config();
			if (
				drawer.isSubmitting ||
				drawer.isBlocked ||
				! isLoggedIn ||
				! canPropose
			) {
				return;
			}

			const fields = form.elements;
			const sender = fields.namedItem( 'centro_emisor_id' );
			const emisorId = sender ? Number( sender.value ) : 0;
			const receptorId = Number( drawer.receptorId ) || 0;
			if ( ! emisorId || ! receptorId ) {
				showFeedback( text( 'selectYourCenter' ), false );
				return;
			}
			const value = ( name ) => {
				const field = fields.namedItem( name );
				return field ? String( field.value ) : '';
			};

			drawer.isSubmitting = true;
			// The panel may be closed and opened for another station before the reply arrives.
			const session = runtime.drawerSession;
			let reply = null;
			try {
				const response = yield window.fetch(
					restUrl + 'colaboraciones',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce': String( restNonce || '' ),
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							centro_emisor_id: emisorId,
							centro_receptor_id: receptorId,
							idea_proyecto: value( 'idea_proyecto' ),
							curso_edades: value( 'curso_edades' ),
							idiomas: value( 'idiomas' ),
							fechas_aprox: value( 'fechas_aprox' ),
						} ),
					}
				);
				const result = yield response.json();
				reply = { ok: response.ok, result: result || {} };
			} catch {
				reply = null;
			}
			if ( session !== runtime.drawerSession ) {
				return;
			}
			drawer.isSubmitting = false;

			if ( reply && reply.ok && reply.result.success ) {
				showFeedback(
					reply.result.message || text( 'sendSuccess' ),
					true
				);
				// Only the proposal fields: the sender and the receiver stay for another proposal.
				PROPOSAL_FIELDS.forEach( ( name ) => {
					const field = fields.namedItem( name );
					if ( field ) {
						field.value = '';
					}
				} );
				return;
			}

			const invalid =
				reply && reply.result.data && reply.result.data.params;
			if ( invalid ) {
				Object.keys( invalid ).forEach( ( name ) => {
					const field = fields.namedItem( name );
					if ( field && typeof field.setAttribute === 'function' ) {
						field.setAttribute( 'aria-invalid', 'true' );
						field.setAttribute(
							'aria-describedby',
							'radioedu-drawer-feedback'
						);
					}
				} );
			}
			showFeedback(
				( reply && reply.result.message ) || text( 'errorOccurred' ),
				false
			);
		} ),
	},

	callbacks: {
		init() {
			state.favorites = readFavorites();
			try {
				state.hasMapConsent =
					window.sessionStorage.getItem( 'radioedu_osm_consent' ) ===
					'1';
			} catch {
				state.hasMapConsent = false;
			}
		},

		// Draws the map when it is shown with consent, and again after each new search.
		syncMap() {
			// Reading the version first makes every new search run this again.
			const version = state.searchVersion;
			if ( ! state.isMapView || ! state.hasMapConsent ) {
				return;
			}
			const element = document.getElementById( 'radioedu-leaflet-map' );
			if ( element ) {
				refreshMap( element, version );
			}
		},

		// Closes the panel with Escape and keeps the focus inside the modal dialog.
		onKeydown: withSyncEvent( ( event ) => {
			if ( ! state.drawer.isVisible ) {
				return;
			}
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				actions.closeDrawer();
				return;
			}
			if ( event.key !== 'Tab' ) {
				return;
			}
			const panel = document.getElementById( 'radioedu-match-drawer' );
			if ( ! panel ) {
				return;
			}
			const focusable = Array.prototype.filter.call(
				panel.querySelectorAll(
					'button, select, textarea, input:not([type="hidden"]), a[href]'
				),
				( element ) =>
					! element.disabled && element.offsetParent !== null
			);
			if ( focusable.length === 0 ) {
				return;
			}
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			const active = panel.ownerDocument.activeElement;
			if ( event.shiftKey && active === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && active === last ) {
				event.preventDefault();
				first.focus();
			}
		} ),
	},
} );

// What the server sets for one page but not the other: the profile has no results, and the
// directory has no preselected receiver. Filled in only where it is missing.
const serverDefaults = {
	centros: [],
	total: 0,
	totalLabel: '0',
	totalPages: 0,
	page: 1,
	firstPage: 1,
	applied: { filters: {}, search: '', orden: 'afines' },
	nextPageUrl: '',
	prevPageUrl: '',
};
Object.keys( serverDefaults ).forEach( ( key ) => {
	if ( state[ key ] === undefined ) {
		state[ key ] = serverDefaults[ key ];
	}
} );
if ( state.drawer.receptorId === undefined ) {
	state.drawer.receptorId = 0;
}
if ( state.drawer.receptorLabel === undefined ) {
	state.drawer.receptorLabel = '';
}

// The receiver the server preselected on a profile, kept before the panel changes the state.
const profileReceiver = {
	id: Number( state.drawer.receptorId ) || 0,
	label: String( state.drawer.receptorLabel ),
};
