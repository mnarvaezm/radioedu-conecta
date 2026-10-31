import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';

/**
 * Lógica interactiva en el frontal de RadioEdu Conecta - Traspasando Fronteras.
 * Cero innerHTML y cero concatenación HTML para prevención estricta de XSS.
 */

/* global L */
( function () {
	'use strict';

	function initRadioeduConecta() {
		const appContainer = document.getElementById( 'radioedu-conecta-app' );
		const singleProfile = document.querySelector(
			'.radioedu-single-profile'
		);
		if ( ! appContainer && ! singleProfile ) {
			return;
		}

		// Configuración inyectada desde PHP.
		const config = window.radioeduConectaData || {};
		const restUrl = config.restUrl || '/wp-json/radioedu-conecta/v1/';
		const restNonce = config.restNonce || '';
		const i18n = {
			/* translators: %s: Number of schools found. */
			resultsCount: __( 'Resultados (%s centros)', 'radioedu-conecta' ),
			noResults: __(
				'No se encontraron centros con los filtros seleccionados.',
				'radioedu-conecta'
			),
			loading: __( 'Cargando centros…', 'radioedu-conecta' ),
			viewProfile: __( 'Ver perfil', 'radioedu-conecta' ),
			proposeMatch: __( 'Proponer match', 'radioedu-conecta' ),
			submitProposal: __( 'Enviar propuesta', 'radioedu-conecta' ),
			unavailable: __( 'No disponible', 'radioedu-conecta' ),
			favorite: __( 'Añadir a favoritos', 'radioedu-conecta' ),
			unfavorite: __( 'Quitar de favoritos', 'radioedu-conecta' ),
			defaultLocation: __( 'Extremadura', 'radioedu-conecta' ),
			sending: __( 'Enviando propuesta…', 'radioedu-conecta' ),
			sendSuccess: __(
				'¡Propuesta enviada con éxito!',
				'radioedu-conecta'
			),
			loginRequired: __(
				'Debes iniciar sesión con tu cuenta de coordinador de centro para enviar propuestas de colaboración.',
				'radioedu-conecta'
			),
			noPermission: __(
				'Tu cuenta actual no dispone de permisos de coordinador para proponer colaboraciones.',
				'radioedu-conecta'
			),
			selectYourCenter: __(
				'Por favor, selecciona tu centro escolar.',
				'radioedu-conecta'
			),
			errorOccurred: __(
				'Ha ocurrido un error al procesar la solicitud.',
				'radioedu-conecta'
			),
			logIn: __( 'Iniciar sesión', 'radioedu-conecta' ),
			mapAttribution: __(
				'Colaboradores de OpenStreetMap',
				'radioedu-conecta'
			),
		};

		// Elementos del DOM.
		const cardsContainer = document.getElementById(
			'radioedu-cards-container'
		);
		const countNumber = document.getElementById( 'radioedu-count-number' );
		const loadingState = document.getElementById( 'radioedu-loading' );
		const noResultsState = document.getElementById( 'radioedu-no-results' );
		const paginationWrap = document.getElementById(
			'radioedu-pagination-wrap'
		);
		const btnLoadMore = document.getElementById( 'radioedu-btn-load-more' );
		const btnBuscar = document.getElementById( 'radioedu-btn-buscar' );
		const btnReset = document.getElementById( 'radioedu-btn-reset' );
		const btnToggleMap = document.getElementById(
			'radioedu-btn-toggle-map'
		);
		const mapView = document.getElementById( 'radioedu-map-view' );
		const mapConsent = document.getElementById( 'radioedu-map-consent' );
		const btnAcceptMap = document.getElementById(
			'radioedu-btn-accept-map'
		);
		const leafletMapEl = document.getElementById( 'radioedu-leaflet-map' );
		const sortSelect = document.getElementById( 'radioedu-sort-select' );
		const filtersForm = document.getElementById( 'radioedu-filters-form' );
		const iconTemplates = document.getElementById(
			'radioedu-icon-templates'
		);

		/**
		 * Clona un icono SVG renderizado en el servidor con la API de iconos de WordPress.
		 *
		 * @param {string} slug Nombre del icono sin colección (p. ej. "send").
		 * @return {?Element} Copia del <svg> o null si la plantilla no lo incluye.
		 */
		function createIcon( slug ) {
			const icon = iconTemplates
				? iconTemplates.content.querySelector(
						'.radioedu-svg-icon--' + slug
					)
				: null;
			return icon ? icon.cloneNode( true ) : null;
		}

		// Drawer de Match.
		const drawerOverlay = document.getElementById(
			'radioedu-drawer-overlay'
		);
		const drawerPanel = document.getElementById( 'radioedu-match-drawer' );
		const btnCloseDrawer = document.getElementById(
			'radioedu-btn-close-drawer'
		);
		const matchForm = document.getElementById( 'radioedu-match-form' );
		const matchReceptorTxt = document.getElementById(
			'radioedu-match-receptor-txt'
		);
		const matchReceptorId = document.getElementById(
			'radioedu-match-receptor'
		);
		const matchEmisorId = document.getElementById(
			'radioedu-match-emisor'
		);
		const drawerFeedback = document.getElementById(
			'radioedu-drawer-feedback'
		);
		const btnSubmitMatch = document.getElementById(
			'radioedu-btn-submit-match'
		);
		const btnSubmitLabel = btnSubmitMatch
			? btnSubmitMatch.querySelector( '.radioedu-btn-label' )
			: null;
		let lastFocusedElement = null;

		// Filtros.
		const filterUbicacion = document.getElementById(
			'radioedu-filter-ubicacion'
		);
		const filterIdioma = document.getElementById(
			'radioedu-filter-idioma'
		);
		const filterEtapa = document.getElementById( 'radioedu-filter-etapa' );
		const filterTematica = document.getElementById(
			'radioedu-filter-tematica'
		);
		const filterFormato = document.getElementById(
			'radioedu-filter-formato'
		);
		const filterSearch = document.getElementById(
			'radioedu-filter-search'
		);

		let isMapView = false;
		let hasMapConsent = false;
		let leafletMap = null;
		let mapMarkers = [];
		let currentCentros = [];
		let currentPage = 1;
		let totalPages = 1;
		let directoryRequest = null;
		let submitting = false;

		try {
			hasMapConsent =
				sessionStorage.getItem( 'radioedu_osm_consent' ) === '1';
		} catch {
			hasMapConsent = false;
		}

		/**
		 * Valida que una URL sea segura (http/https) para prevenir XSS (javascript:/data:).
		 *
		 * @param {string} rawUrl URL de entrada.
		 * @return {string} URL validada o cadena vacía.
		 */
		function sanitizeHttpUrl( rawUrl ) {
			if ( ! rawUrl || typeof rawUrl !== 'string' ) {
				return '';
			}
			try {
				const parsed = new URL( rawUrl, window.location.origin );
				if (
					parsed.protocol === 'http:' ||
					parsed.protocol === 'https:'
				) {
					return parsed.href;
				}
			} catch {
				return '';
			}
			return '';
		}

		/**
		 * Anuncia un mensaje a lectores de pantalla mediante wp.a11y.speak().
		 *
		 * @param {string} message    Mensaje a anunciar.
		 * @param {string} politeness 'polite' o 'assertive'.
		 */
		function announce( message, politeness ) {
			if ( message ) {
				speak( String( message ), politeness || 'polite' );
			}
		}

		// Favoritos locales en localStorage.
		function getFavorites() {
			try {
				const favs = localStorage.getItem( 'radioedu_favoritos' );
				const parsed = favs ? JSON.parse( favs ) : [];
				return Array.isArray( parsed ) ? parsed : [];
			} catch {
				return [];
			}
		}

		function toggleFavorite( id ) {
			try {
				let favs = getFavorites();
				if ( favs.includes( id ) ) {
					favs = favs.filter( ( fId ) => fId !== id );
				} else {
					favs.push( id );
				}
				localStorage.setItem(
					'radioedu_favoritos',
					JSON.stringify( favs )
				);
				return favs.includes( id );
			} catch {
				return false;
			}
		}

		// Consulta a la REST API con los filtros activos.
		function fetchCentros( appendPage ) {
			const isAppend = Boolean( appendPage );
			if ( directoryRequest ) {
				directoryRequest.abort();
			}
			const request = new AbortController();
			directoryRequest = request;
			const requestedPage = isAppend ? currentPage + 1 : 1;
			if ( btnLoadMore ) {
				btnLoadMore.disabled = true;
			}
			const errorState = document.getElementById(
				'radioedu-directory-error'
			);
			if ( errorState ) {
				errorState.hidden = true;
			}
			if ( ! isAppend ) {
				currentPage = 1;
				cardsContainer.style.display = 'none';
				noResultsState.style.display = 'none';
				if ( paginationWrap ) {
					paginationWrap.style.display = 'none';
				}
			}
			loadingState.style.display = 'block';

			const params = new URLSearchParams( {
				page: String( requestedPage ),
				per_page: '24',
				orden: sortSelect ? sortSelect.value : 'afines',
			} );

			// No afines_a: the endpoint picks the user's own centro itself, and skips it for global
			// managers, whose first "managed" centro (userCenterId) is an arbitrary one.
			if ( filterUbicacion && filterUbicacion.value ) {
				params.append( 'ubicacion', filterUbicacion.value );
			}
			if ( filterIdioma && filterIdioma.value ) {
				params.append( 'idioma', filterIdioma.value );
			}
			if ( filterEtapa && filterEtapa.value ) {
				params.append( 'etapa', filterEtapa.value );
			}
			if ( filterTematica && filterTematica.value ) {
				params.append( 'tematica', filterTematica.value );
			}
			if ( filterFormato && filterFormato.value ) {
				params.append( 'formato', filterFormato.value );
			}
			if ( filterSearch && filterSearch.value.trim() ) {
				params.append( 'search', filterSearch.value.trim() );
			}

			const fetchHeaders = {};
			if (
				config.isLoggedIn &&
				restNonce &&
				typeof restNonce === 'string' &&
				restNonce.trim().length > 0
			) {
				fetchHeaders[ 'X-WP-Nonce' ] = restNonce.trim();
			}

			const endpoint = new URL(
				restUrl + 'centros',
				window.location.href
			);
			params.forEach( ( value, key ) =>
				endpoint.searchParams.set( key, value )
			);
			fetch( endpoint.href, {
				headers: fetchHeaders,
				signal: request.signal,
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}
					return response.json();
				} )
				.then( function ( data ) {
					if ( request.signal.aborted ) {
						return;
					}
					currentPage = requestedPage;
					loadingState.style.display = 'none';
					const fetched = Array.isArray( data.centros )
						? data.centros
						: [];
					totalPages = Number( data.total_pages || 1 );

					if ( isAppend ) {
						currentCentros = currentCentros.concat( fetched );
					} else {
						currentCentros = fetched;
					}

					const totalCount = String(
						data.total !== undefined
							? data.total
							: currentCentros.length
					);
					if ( countNumber ) {
						countNumber.textContent = totalCount;
					}
					if ( ! isAppend && i18n.resultsCount ) {
						announce(
							String( i18n.resultsCount ).replace(
								'%s',
								totalCount
							)
						);
					}

					if ( currentCentros.length === 0 ) {
						noResultsState.style.display = 'block';
						cardsContainer.style.display = 'none';
						if ( paginationWrap ) {
							paginationWrap.style.display = 'none';
						}
					} else {
						renderCards( fetched, isAppend );
						if ( ! isMapView ) {
							cardsContainer.style.display = 'grid';
						}
						if ( paginationWrap ) {
							paginationWrap.style.display =
								currentPage < totalPages && ! isMapView
									? 'block'
									: 'none';
						}
					}

					if ( isMapView && leafletMap ) {
						updateMapMarkers( currentCentros );
					}
				} )
				.catch( function ( err ) {
					if ( err.name === 'AbortError' ) {
						return;
					}
					loadingState.style.display = 'none';
					if ( errorState ) {
						errorState.hidden = false;
					}
					announce( i18n.errorOccurred, 'assertive' );
				} )
				.finally( function () {
					if ( directoryRequest === request ) {
						directoryRequest = null;
						if ( btnLoadMore ) {
							btnLoadMore.disabled = false;
						}
					}
				} );
		}

		// Mapeo seguro de banderas a iconos emoji/insignias representativas.
		function getFlagEmoji( code ) {
			const flags = {
				'es-ex': '🟩⬜⬛',
				'es-ct': '🟨🟥',
				'es-vc': '🇪🇸',
				'es-ar': '🇪🇸',
				'es-an': '🟩⬜🟩',
				'es-md': '🇪🇸',
				'es-ga': '🇪🇸',
				'es-pv': '🇪🇸',
				es: '🇪🇸',
				pt: '🇵🇹',
				fr: '🇫🇷',
				it: '🇮🇹',
				ie: '🇮🇪',
				uk: '🇬🇧',
				de: '🇩🇪',
				mx: '🇲🇽',
				ar: '🇦🇷',
				co: '🇨🇴',
				cl: '🇨🇱',
				eu: '🇪🇺',
				world: '🌍',
			};
			return flags[ code ] || '📻';
		}

		// Renderizado seguro en el DOM (sin innerHTML).
		function renderCards( centros, append ) {
			if ( ! append ) {
				while ( cardsContainer.firstChild ) {
					cardsContainer.removeChild( cardsContainer.firstChild );
				}
			}

			const favs = getFavorites();

			centros.forEach( function ( centro ) {
				const card = document.createElement( 'article' );
				card.className = 'radioedu-card';
				card.dataset.id = String( centro.id );

				// 1. Cover
				const cover = document.createElement( 'div' );
				cover.className = 'radioedu-card-cover';
				const safeCoverUrl = sanitizeHttpUrl( centro.foto_cabecera );
				if ( safeCoverUrl ) {
					const cssSafeUrl = safeCoverUrl.replace(
						/["'()\\]/g,
						'\\$&'
					);
					cover.style.backgroundImage = 'url("' + cssSafeUrl + '")';
				}

				// Botón Favorito
				const favBtn = document.createElement( 'button' );
				const setFavState = function ( isFav ) {
					const favIcon = createIcon(
						isFav ? 'star-filled' : 'star-empty'
					);
					if ( favIcon ) {
						favBtn.replaceChildren( favIcon );
					} else {
						favBtn.textContent = isFav ? '★' : '☆';
					}
					favBtn.classList.toggle( 'is-active', isFav );
					favBtn.setAttribute(
						'aria-pressed',
						isFav ? 'true' : 'false'
					);
					favBtn.setAttribute(
						'aria-label',
						( isFav ? i18n.unfavorite : i18n.favorite ) || ''
					);
				};
				favBtn.className = 'radioedu-card-fav-btn';
				favBtn.setAttribute( 'type', 'button' );
				setFavState( favs.includes( centro.id ) );
				favBtn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					setFavState( toggleFavorite( centro.id ) );
				} );
				cover.appendChild( favBtn );

				// Logo de la radio
				const logo = document.createElement( 'img' );
				logo.className = 'radioedu-card-logo';
				logo.alt = ''; // Decorativo: el nombre ya figura en el título de la tarjeta.
				const safeLogoUrl = sanitizeHttpUrl( centro.logo_radio );
				logo.src =
					safeLogoUrl ||
					'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="%2300838f"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-1.66 0-3-1.34-3-3V9c0-1.66 1.34-3 3-3s3 1.34 3 3v5c0 1.66-1.34 3-3 3z"/></svg>';
				cover.appendChild( logo );

				card.appendChild( cover );

				// 2. Body
				const body = document.createElement( 'div' );
				body.className = 'radioedu-card-body';

				const title = document.createElement( 'h3' );
				title.className = 'radioedu-card-title';
				title.textContent = String( centro.nombre || '' );
				body.appendChild( title );

				const location = document.createElement( 'div' );
				location.className = 'radioedu-card-location';

				const flag = document.createElement( 'span' );
				flag.className = 'radioedu-flag-badge';
				flag.textContent = getFlagEmoji( centro.bandera );
				location.appendChild( flag );

				const locText = document.createElement( 'span' );
				locText.textContent =
					centro.ubicacion || i18n.defaultLocation || '';
				location.appendChild( locText );

				body.appendChild( location );

				// Píldoras de Tags
				if ( Array.isArray( centro.tags ) && centro.tags.length > 0 ) {
					const tagsWrapper = document.createElement( 'div' );
					tagsWrapper.className = 'radioedu-card-tags';

					const allowedColors = [ 'mint', 'coral', 'blue' ];
					centro.tags.slice( 0, 5 ).forEach( function ( tag ) {
						const tagSpan = document.createElement( 'span' );
						const colorClass = allowedColors.includes( tag.color )
							? tag.color
							: 'mint';
						tagSpan.className =
							'radioedu-tag radioedu-tag-' + colorClass;
						tagSpan.textContent = String( tag.name || '' );
						tagsWrapper.appendChild( tagSpan );
					} );

					body.appendChild( tagsWrapper );
				}

				// 3. Footer / Botones
				const footer = document.createElement( 'div' );
				footer.className = 'radioedu-card-footer';

				const btnPerfil = document.createElement( 'a' );
				btnPerfil.className = 'radioedu-btn-secondary';
				btnPerfil.href = sanitizeHttpUrl( centro.enlace_perfil ) || '#';
				btnPerfil.textContent = i18n.viewProfile || 'Ver perfil';
				footer.appendChild( btnPerfil );

				const btnMatch = document.createElement( 'button' );
				btnMatch.className = 'radioedu-btn-match';
				btnMatch.setAttribute( 'type', 'button' );

				if ( centro.disponible === false ) {
					btnMatch.disabled = true;
					btnMatch.textContent = i18n.unavailable || 'No disponible';
				} else {
					const planeIcon = createIcon( 'send' );
					if ( planeIcon ) {
						btnMatch.appendChild( planeIcon );
					}

					const matchText = document.createTextNode(
						' ' + ( i18n.proposeMatch || 'Proponer match' )
					);
					btnMatch.appendChild( matchText );

					btnMatch.addEventListener( 'click', function () {
						openMatchDrawer( centro, btnMatch );
					} );
				}

				footer.appendChild( btnMatch );

				body.appendChild( footer );
				card.appendChild( body );

				cardsContainer.appendChild( card );
			} );
		}

		// Gestión del Drawer de Solicitud de Match.
		function openMatchDrawer( centro, triggerEl ) {
			if ( matchReceptorTxt ) {
				matchReceptorTxt.value =
					centro.nombre +
					( centro.ubicacion ? ' (' + centro.ubicacion + ')' : '' );
			}
			if ( matchReceptorId ) {
				matchReceptorId.value = String( centro.id );
			}

			// Sin sesión o sin permisos: el panel se abre con un aviso accesible en lugar de un alert() bloqueante.
			let blockedMessage = '';
			if ( ! config.isLoggedIn ) {
				blockedMessage = i18n.loginRequired || '';
			} else if ( ! config.canPropose ) {
				blockedMessage = i18n.noPermission || '';
			}

			if ( blockedMessage ) {
				showDrawerFeedback( blockedMessage, false );
				const safeLoginUrl = sanitizeHttpUrl( config.loginUrl );
				if ( ! config.isLoggedIn && safeLoginUrl && drawerFeedback ) {
					const loginLink = document.createElement( 'a' );
					loginLink.href = safeLoginUrl;
					loginLink.textContent = i18n.logIn || '';
					drawerFeedback.appendChild(
						document.createTextNode( ' ' )
					);
					drawerFeedback.appendChild( loginLink );
				}
			} else if ( drawerFeedback ) {
				drawerFeedback.style.display = 'none';
				drawerFeedback.textContent = '';
			}
			if ( btnSubmitMatch ) {
				btnSubmitMatch.disabled = Boolean( blockedMessage );
			}

			lastFocusedElement =
				triggerEl || drawerPanel.ownerDocument.activeElement;
			drawerOverlay.classList.add( 'is-open' );
			drawerPanel.style.display = 'flex';
			setTimeout( function () {
				drawerPanel.classList.add( 'is-open' );
				const firstField = drawerPanel.querySelector(
					'select:not([disabled]), textarea, input:not([type="hidden"]):not([disabled]):not([readonly])'
				);
				( firstField || drawerPanel ).focus();
			}, 10 );
			document.addEventListener( 'keydown', onDrawerKeydown );
		}

		function closeMatchDrawer() {
			drawerPanel.classList.remove( 'is-open' );
			drawerOverlay.classList.remove( 'is-open' );
			document.removeEventListener( 'keydown', onDrawerKeydown );
			setTimeout( function () {
				drawerPanel.style.display = 'none';
			}, 350 );
			if (
				lastFocusedElement &&
				typeof lastFocusedElement.focus === 'function'
			) {
				lastFocusedElement.focus();
			}
		}

		/**
		 * Cierra el panel con Escape y mantiene el foco dentro del diálogo modal.
		 *
		 * @param {KeyboardEvent} e Evento de teclado.
		 */
		function onDrawerKeydown( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				closeMatchDrawer();
				return;
			}
			if ( e.key !== 'Tab' ) {
				return;
			}
			const focusable = Array.prototype.filter.call(
				drawerPanel.querySelectorAll(
					'button, select, textarea, input:not([type="hidden"]), a[href]'
				),
				function ( el ) {
					return ! el.disabled && el.offsetParent !== null;
				}
			);
			if ( focusable.length === 0 ) {
				return;
			}
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			const activeEl = drawerPanel.ownerDocument.activeElement;
			if ( e.shiftKey && activeEl === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && activeEl === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		if ( btnCloseDrawer ) {
			btnCloseDrawer.addEventListener( 'click', closeMatchDrawer );
		}
		if ( drawerOverlay ) {
			drawerOverlay.addEventListener( 'click', closeMatchDrawer );
		}

		// Botón "Proponer match" en la página de perfil individual de una emisora.
		const singleMatchBtn = document.getElementById(
			'radioedu-single-btn-match'
		);
		if ( singleMatchBtn ) {
			singleMatchBtn.addEventListener( 'click', function () {
				openMatchDrawer(
					{
						id: Number( singleMatchBtn.dataset.id || 0 ),
						nombre: singleMatchBtn.dataset.nombre || '',
						ubicacion: singleMatchBtn.dataset.ubicacion || '',
					},
					singleMatchBtn
				);
			} );
		}

		/**
		 * Muestra un mensaje de resultado en el panel lateral.
		 *
		 * @param {string}  message Mensaje.
		 * @param {boolean} success Si es un mensaje de éxito.
		 */
		function showDrawerFeedback( message, success ) {
			if ( ! drawerFeedback ) {
				return;
			}
			drawerFeedback.className =
				'radioedu-drawer-feedback ' +
				( success
					? 'radioedu-feedback-success'
					: 'radioedu-feedback-error' );
			drawerFeedback.textContent = String( message || '' );
			drawerFeedback.style.display = 'block';
		}

		/**
		 * Activa o desactiva el estado "enviando" del botón conservando su icono.
		 *
		 * @param {boolean} sending Si la petición está en curso.
		 */
		function setSubmitState( sending ) {
			submitting = sending;
			if ( ! btnSubmitMatch ) {
				return;
			}
			btnSubmitMatch.disabled = sending;
			btnSubmitMatch.setAttribute(
				'aria-busy',
				sending ? 'true' : 'false'
			);
			if ( btnSubmitLabel ) {
				btnSubmitLabel.textContent = sending
					? i18n.sending || ''
					: i18n.submitProposal || btnSubmitLabel.textContent;
			}
		}

		// Envío asíncrono del formulario de Match.
		if ( matchForm ) {
			matchForm.addEventListener( 'input', function ( event ) {
				event.target.removeAttribute( 'aria-invalid' );
				event.target.removeAttribute( 'aria-describedby' );
			} );
			matchForm.addEventListener(
				'invalid',
				function ( event ) {
					event.target.setAttribute( 'aria-invalid', 'true' );
					event.target.setAttribute(
						'aria-describedby',
						'radioedu-drawer-feedback'
					);
					showDrawerFeedback( event.target.validationMessage, false );
				},
				true
			);
			matchForm.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				if (
					submitting ||
					! config.isLoggedIn ||
					! config.canPropose
				) {
					return;
				}

				const emisorId = matchEmisorId
					? matchEmisorId.value
					: config.userCenterId;
				const receptorId = matchReceptorId ? matchReceptorId.value : '';
				const idea = document.getElementById(
					'radioedu-match-idea'
				).value;
				const edades = document.getElementById(
					'radioedu-match-edades'
				).value;
				const idiomas = document.getElementById(
					'radioedu-match-idiomas'
				).value;
				const fechas = document.getElementById(
					'radioedu-match-fechas'
				).value;

				if ( ! emisorId || ! receptorId ) {
					showDrawerFeedback( i18n.selectYourCenter || '', false );
					return;
				}

				setSubmitState( true );

				fetch( restUrl + 'colaboraciones', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': restNonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						centro_emisor_id: Number( emisorId ),
						centro_receptor_id: Number( receptorId ),
						idea_proyecto: idea,
						curso_edades: edades,
						idiomas,
						fechas_aprox: fechas,
					} ),
				} )
					.then( function ( res ) {
						return res.json().then( function ( result ) {
							return { ok: res.ok, result: result || {} };
						} );
					} )
					.then( function ( payload ) {
						setSubmitState( false );

						if ( payload.ok && payload.result.success ) {
							showDrawerFeedback(
								payload.result.message ||
									i18n.sendSuccess ||
									'',
								true
							);
							const receptorLabel = matchReceptorTxt
								? matchReceptorTxt.value
								: '';
							matchForm.reset();
							// reset() vacía también el receptor preseleccionado: se restaura.
							if ( matchReceptorId ) {
								matchReceptorId.value = receptorId;
							}
							if ( matchReceptorTxt ) {
								matchReceptorTxt.value = receptorLabel;
							}
						} else {
							const errors =
								payload.result.data &&
								payload.result.data.params;
							if ( errors ) {
								Object.keys( errors ).forEach(
									function ( name ) {
										const field =
											matchForm.elements.namedItem(
												name
											);
										if (
											field &&
											typeof field.setAttribute ===
												'function'
										) {
											field.setAttribute(
												'aria-invalid',
												'true'
											);
											field.setAttribute(
												'aria-describedby',
												'radioedu-drawer-feedback'
											);
										}
									}
								);
							}
							showDrawerFeedback(
								payload.result.message ||
									i18n.errorOccurred ||
									'',
								false
							);
						}
					} )
					.catch( function () {
						setSubmitState( false );
						showDrawerFeedback( i18n.errorOccurred || '', false );
					} );
			} );
		}

		// Activar mapa tras consentimiento RGPD para carga de teselas externas.
		function activateLeafletMap() {
			if ( mapConsent ) {
				mapConsent.style.display = 'none';
			}
			if ( leafletMapEl ) {
				leafletMapEl.style.display = 'block';
			}
			if ( ! leafletMap && typeof L !== 'undefined' ) {
				initLeafletMap();
			} else if ( leafletMap ) {
				leafletMap.invalidateSize();
			}
			updateMapMarkers( currentCentros );
		}

		if ( btnAcceptMap ) {
			btnAcceptMap.addEventListener( 'click', function () {
				hasMapConsent = true;
				try {
					sessionStorage.setItem( 'radioedu_osm_consent', '1' );
				} catch {
					// Ignorar si sessionStorage está restringido.
				}
				activateLeafletMap();
			} );
		}

		// Toggle Vista de Mapa
		if ( btnToggleMap && mapView ) {
			btnToggleMap.addEventListener( 'click', function () {
				isMapView = ! isMapView;
				btnToggleMap.setAttribute(
					'aria-pressed',
					isMapView ? 'true' : 'false'
				);

				if ( isMapView ) {
					mapView.style.display = 'block';
					mapView.setAttribute( 'aria-hidden', 'false' );
					cardsContainer.style.display = 'none';
					if ( paginationWrap ) {
						paginationWrap.style.display = 'none';
					}
					btnToggleMap.classList.add( 'is-active' );

					if ( hasMapConsent ) {
						activateLeafletMap();
					}
				} else {
					mapView.style.display = 'none';
					mapView.setAttribute( 'aria-hidden', 'true' );
					cardsContainer.style.display = 'grid';
					if ( paginationWrap ) {
						paginationWrap.style.display =
							currentPage < totalPages ? 'block' : 'none';
					}
					btnToggleMap.classList.remove( 'is-active' );
				}
			} );
		}

		// Inicialización del mapa Leaflet
		function initLeafletMap() {
			const mapSettings = config.mapCenter || {
				lat: 39.4699,
				lng: -6.3708,
				zoom: 8,
			};
			leafletMap = L.map( 'radioedu-leaflet-map', {
				attributionControl: false,
			} ).setView(
				[ mapSettings.lat, mapSettings.lng ],
				mapSettings.zoom
			);

			// Build attribution with DOM nodes, including translated text.
			const attribution = L.control( { position: 'bottomright' } );
			attribution.onAdd = function () {
				const container = document.createElement( 'div' );
				container.className = 'leaflet-control-attribution';
				const link = document.createElement( 'a' );
				link.href = 'https://www.openstreetmap.org/copyright';
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = i18n.mapAttribution;
				container.append(
					document.createTextNode( 'Leaflet | © ' ),
					link
				);
				return container;
			};
			attribution.addTo( leafletMap );
			L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 18,
			} ).addTo( leafletMap );
		}

		// Actualización segura de marcadores usando nodos DOM (cero HTML concatenado).
		function updateMapMarkers( centros ) {
			if ( ! leafletMap || typeof L === 'undefined' ) {
				return;
			}

			mapMarkers.forEach( ( m ) => leafletMap.removeLayer( m ) );
			mapMarkers = [];

			centros.forEach( function ( centro ) {
				if ( centro.lat && centro.lng ) {
					const latNum = parseFloat( centro.lat );
					const lngNum = parseFloat( centro.lng );

					if (
						Number.isFinite( latNum ) &&
						Number.isFinite( lngNum ) &&
						Math.abs( latNum ) <= 90 &&
						Math.abs( lngNum ) <= 180
					) {
						const marker = L.marker( [ latNum, lngNum ] ).addTo(
							leafletMap
						);

						const popupNode = document.createElement( 'div' );
						const strongEl = document.createElement( 'strong' );
						strongEl.textContent = String( centro.nombre || '' );
						popupNode.appendChild( strongEl );
						popupNode.appendChild( document.createElement( 'br' ) );

						const locSpan = document.createElement( 'span' );
						locSpan.textContent = String( centro.ubicacion || '' );
						popupNode.appendChild( locSpan );
						popupNode.appendChild( document.createElement( 'br' ) );

						const linkEl = document.createElement( 'a' );
						linkEl.href =
							sanitizeHttpUrl( centro.enlace_perfil ) || '#';
						linkEl.style.color = '#00838f';
						linkEl.style.fontWeight = '600';
						linkEl.textContent = i18n.viewProfile || 'Ver perfil';
						popupNode.appendChild( linkEl );

						marker.bindPopup( popupNode );
						mapMarkers.push( marker );
					}
				}
			} );
		}

		// Eventos de filtro y paginación
		if ( filtersForm ) {
			filtersForm.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				fetchCentros( false );
			} );
		} else if ( btnBuscar ) {
			btnBuscar.addEventListener( 'click', function () {
				fetchCentros( false );
			} );
		}
		if ( sortSelect ) {
			sortSelect.addEventListener( 'change', function () {
				fetchCentros( false );
			} );
		}
		if ( btnLoadMore ) {
			btnLoadMore.addEventListener( 'click', function () {
				if ( currentPage < totalPages ) {
					fetchCentros( true );
				}
			} );
		}
		if ( btnReset ) {
			btnReset.addEventListener( 'click', function () {
				if ( filterUbicacion ) {
					filterUbicacion.value = '';
				}
				if ( filterIdioma ) {
					filterIdioma.value = '';
				}
				if ( filterEtapa ) {
					filterEtapa.value = '';
				}
				if ( filterTematica ) {
					filterTematica.value = '';
				}
				if ( filterFormato ) {
					filterFormato.value = '';
				}
				if ( filterSearch ) {
					filterSearch.value = '';
				}
				fetchCentros( false );
			} );
		}

		// Carga inicial del directorio (solo si estamos en la página con #radioedu-conecta-app)
		if ( appContainer && cardsContainer ) {
			fetchCentros( false );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initRadioeduConecta );
	} else {
		initRadioeduConecta();
	}
} )();
