/**
 * Lógica interactiva en el frontal de RadioEdu Conecta - Traspasando Fronteras.
 * Cero innerHTML para prevención de XSS. Utiliza createElement y textContent.
 */

/* global radioeduConectaData, L */
( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		const appContainer = document.getElementById( 'radioedu-conecta-app' );
		if ( ! appContainer ) {
			return;
		}

		// Configuración inyectada desde PHP.
		const config = window.radioeduConectaData || {};
		const restUrl = config.restUrl || '/wp-json/radioedu-conecta/v1/';
		const restNonce = config.restNonce || '';
		const i18n = config.i18n || {};

		// Elementos del DOM.
		const cardsContainer   = document.getElementById( 'radioedu-cards-container' );
		const countNumber      = document.getElementById( 'radioedu-count-number' );
		const loadingState     = document.getElementById( 'radioedu-loading' );
		const noResultsState   = document.getElementById( 'radioedu-no-results' );
		const btnBuscar        = document.getElementById( 'radioedu-btn-buscar' );
		const btnReset         = document.getElementById( 'radioedu-btn-reset' );
		const btnToggleMap     = document.getElementById( 'radioedu-btn-toggle-map' );
		const mapView          = document.getElementById( 'radioedu-map-view' );
		const sortSelect       = document.getElementById( 'radioedu-sort-select' );

		// Drawer de Match.
		const drawerOverlay    = document.getElementById( 'radioedu-drawer-overlay' );
		const drawerPanel      = document.getElementById( 'radioedu-match-drawer' );
		const btnCloseDrawer   = document.getElementById( 'radioedu-btn-close-drawer' );
		const matchForm        = document.getElementById( 'radioedu-match-form' );
		const matchReceptorTxt = document.getElementById( 'radioedu-match-receptor-txt' );
		const matchReceptorId  = document.getElementById( 'radioedu-match-receptor' );
		const matchEmisorId    = document.getElementById( 'radioedu-match-emisor' );
		const drawerFeedback   = document.getElementById( 'radioedu-drawer-feedback' );
		const btnSubmitMatch   = document.getElementById( 'radioedu-btn-submit-match' );

		// Filtros.
		const filterUbicacion = document.getElementById( 'radioedu-filter-ubicacion' );
		const filterIdioma    = document.getElementById( 'radioedu-filter-idioma' );
		const filterEtapa     = document.getElementById( 'radioedu-filter-etapa' );
		const filterTematica  = document.getElementById( 'radioedu-filter-tematica' );
		const filterFormato   = document.getElementById( 'radioedu-filter-formato' );
		const filterSearch    = document.getElementById( 'radioedu-filter-search' );

		let isMapView = false;
		let leafletMap = null;
		let mapMarkers = [];
		let currentCentros = [];

		// Favoritos locales en localStorage.
		function getFavorites() {
			try {
				const favs = localStorage.getItem( 'radioedu_favoritos' );
				return favs ? JSON.parse( favs ) : [];
			} catch ( e ) {
				return [];
			}
		}

		function toggleFavorite( id ) {
			try {
				let favs = getFavorites();
				if ( favs.includes( id ) ) {
					favs = favs.filter( fId => fId !== id );
				} else {
					favs.push( id );
				}
				localStorage.setItem( 'radioedu_favoritos', JSON.stringify( favs ) );
				return favs.includes( id );
			} catch ( e ) {
				return false;
			}
		}

		// Consulta a la REST API con los filtros activos.
		function fetchCentros() {
			cardsContainer.style.display = 'none';
			noResultsState.style.display = 'none';
			loadingState.style.display = 'block';

			const params = new URLSearchParams( {
				per_page: '30',
				orden: sortSelect ? sortSelect.value : 'afines'
			} );

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
			if ( restNonce && typeof restNonce === 'string' && restNonce.trim().length > 0 ) {
				fetchHeaders['X-WP-Nonce'] = restNonce.trim();
			}

			fetch( restUrl + 'centros?' + params.toString(), {
				headers: fetchHeaders
			} )
			.then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function( data ) {
				loadingState.style.display = 'none';
				currentCentros = data.centros || [];

				if ( countNumber ) {
					countNumber.textContent = String( data.total !== undefined ? data.total : currentCentros.length );
				}

				if ( currentCentros.length === 0 ) {
					noResultsState.style.display = 'block';
					cardsContainer.style.display = 'none';
				} else {
					renderCards( currentCentros );
					cardsContainer.style.display = 'grid';
				}

				if ( isMapView ) {
					updateMapMarkers( currentCentros );
				}
			} )
			.catch( function( err ) {
				console.error( 'RadioEdu Conecta API error:', err );
				loadingState.style.display = 'none';
				noResultsState.style.display = 'block';
			} );
		}

		// Mapeo seguro de banderas a iconos emoji/insignias representativas.
		function getFlagEmoji( code ) {
			const flags = {
				'es-ex': '🟩⬜⬛', // Extremadura
				'es-ct': '🟨🟥',   // Cataluña
				'es-vc': '🇪🇸',     // Comunitat Valenciana
				'es-ar': '🇪🇸',     // Aragón
				'es-an': '🟩⬜🟩', // Andalucía
				'es-md': '🇪🇸',     // Madrid
				'es-ga': '🇪🇸',     // Galicia
				'es-pv': '🇪🇸',     // País Vasco
				'es': '🇪🇸',
				'pt': '🇵🇹',
				'fr': '🇫🇷',
				'it': '🇮🇹',
				'ie': '🇮🇪',
				'uk': '🇬🇧',
				'de': '🇩🇪',
				'mx': '🇲🇽',
				'ar': '🇦🇷',
				'co': '🇨🇴',
				'world': '🌍'
			};
			return flags[ code ] || '📻';
		}

		// Renderizado seguro en el DOM (sin innerHTML).
		function renderCards( centros ) {
			while ( cardsContainer.firstChild ) {
				cardsContainer.removeChild( cardsContainer.firstChild );
			}

			const favs = getFavorites();

			centros.forEach( function( centro ) {
				const card = document.createElement( 'article' );
				card.className = 'radioedu-card';
				card.dataset.id = centro.id;

				// 1. Cover
				const cover = document.createElement( 'div' );
				cover.className = 'radioedu-card-cover';
				if ( centro.foto_cabecera ) {
					cover.style.backgroundImage = 'url("' + encodeURI( centro.foto_cabecera ) + '")';
				}

				// Botón Favorito
				const favBtn = document.createElement( 'button' );
				favBtn.className = 'radioedu-card-fav-btn' + ( favs.includes( centro.id ) ? ' is-active' : '' );
				favBtn.setAttribute( 'type', 'button' );
				favBtn.setAttribute( 'aria-label', i18n.favorite || 'Añadir a favoritos' );
				favBtn.textContent = favs.includes( centro.id ) ? '❤️' : '♡';
				favBtn.addEventListener( 'click', function( e ) {
					e.stopPropagation();
					const isFav = toggleFavorite( centro.id );
					favBtn.textContent = isFav ? '❤️' : '♡';
					favBtn.classList.toggle( 'is-active', isFav );
				} );
				cover.appendChild( favBtn );

				// Logo de la radio
				const logo = document.createElement( 'img' );
				logo.className = 'radioedu-card-logo';
				logo.alt = centro.nombre;
				logo.src = centro.logo_radio || 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="%2300838f"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-1.66 0-3-1.34-3-3V9c0-1.66 1.34-3 3-3s3 1.34 3 3v5c0 1.66-1.34 3-3 3z"/></svg>';
				cover.appendChild( logo );

				card.appendChild( cover );

				// 2. Body
				const body = document.createElement( 'div' );
				body.className = 'radioedu-card-body';

				const title = document.createElement( 'h3' );
				title.className = 'radioedu-card-title';
				title.textContent = centro.nombre;
				body.appendChild( title );

				const location = document.createElement( 'div' );
				location.className = 'radioedu-card-location';

				const flag = document.createElement( 'span' );
				flag.className = 'radioedu-flag-badge';
				flag.textContent = getFlagEmoji( centro.bandera );
				location.appendChild( flag );

				const locText = document.createElement( 'span' );
				locText.textContent = centro.ubicacion || 'Extremadura';
				location.appendChild( locText );

				body.appendChild( location );

				// Píldoras de Tags
				if ( centro.tags && centro.tags.length > 0 ) {
					const tagsWrapper = document.createElement( 'div' );
					tagsWrapper.className = 'radioedu-card-tags';

					centro.tags.slice( 0, 5 ).forEach( function( tag ) {
						const tagSpan = document.createElement( 'span' );
						tagSpan.className = 'radioedu-tag radioedu-tag-' + ( tag.color || 'mint' );
						tagSpan.textContent = tag.name;
						tagsWrapper.appendChild( tagSpan );
					} );

					body.appendChild( tagsWrapper );
				}

				// 3. Footer / Botones
				const footer = document.createElement( 'div' );
				footer.className = 'radioedu-card-footer';

				const btnPerfil = document.createElement( 'a' );
				btnPerfil.className = 'radioedu-btn-secondary';
				btnPerfil.href = centro.enlace_perfil || '#';
				btnPerfil.textContent = i18n.viewProfile || 'Ver perfil';
				footer.appendChild( btnPerfil );

				const btnMatch = document.createElement( 'button' );
				btnMatch.className = 'radioedu-btn-match';
				btnMatch.setAttribute( 'type', 'button' );

				const planeIcon = document.createElement( 'span' );
				planeIcon.textContent = '✈️';
				btnMatch.appendChild( planeIcon );

				const matchText = document.createTextNode( ' ' + ( i18n.proposeMatch || 'Proponer match' ) );
				btnMatch.appendChild( matchText );

				btnMatch.addEventListener( 'click', function() {
					openMatchDrawer( centro );
				} );
				footer.appendChild( btnMatch );

				body.appendChild( footer );
				card.appendChild( body );

				cardsContainer.appendChild( card );
			} );
		}

		// Gestión del Drawer de Solicitud de Match.
		function openMatchDrawer( centro ) {
			if ( ! config.isLoggedIn ) {
				alert( i18n.loginRequired || 'Debes iniciar sesión para proponer un match de colaboración.' );
				window.location.href = config.loginUrl;
				return;
			}

			if ( matchReceptorTxt ) {
				matchReceptorTxt.value = centro.nombre + ' (' + ( centro.ubicacion || '' ) + ')';
			}
			if ( matchReceptorId ) {
				matchReceptorId.value = String( centro.id );
			}

			if ( drawerFeedback ) {
				drawerFeedback.style.display = 'none';
				drawerFeedback.textContent = '';
			}

			drawerOverlay.classList.add( 'is-open' );
			drawerPanel.style.display = 'flex';
			setTimeout( function() {
				drawerPanel.classList.add( 'is-open' );
			}, 10 );
		}

		function closeMatchDrawer() {
			drawerPanel.classList.remove( 'is-open' );
			drawerOverlay.classList.remove( 'is-open' );
			setTimeout( function() {
				drawerPanel.style.display = 'none';
			}, 350 );
		}

		if ( btnCloseDrawer ) {
			btnCloseDrawer.addEventListener( 'click', closeMatchDrawer );
		}
		if ( drawerOverlay ) {
			drawerOverlay.addEventListener( 'click', closeMatchDrawer );
		}

		// Envío asíncrono del formulario de Match.
		if ( matchForm ) {
			matchForm.addEventListener( 'submit', function( e ) {
				e.preventDefault();

				const emisorId = matchEmisorId ? matchEmisorId.value : config.userCenterId;
				const receptorId = matchReceptorId ? matchReceptorId.value : '';
				const idea = document.getElementById( 'radioedu-match-idea' ).value;
				const edades = document.getElementById( 'radioedu-match-edades' ).value;
				const idiomas = document.getElementById( 'radioedu-match-idiomas' ).value;
				const fechas = document.getElementById( 'radioedu-match-fechas' ).value;

				if ( ! emisorId ) {
					alert( i18n.selectYourCenter || 'Selecciona tu centro emisor.' );
					return;
				}

				btnSubmitMatch.disabled = true;
				btnSubmitMatch.textContent = i18n.sending || 'Enviando...';

				fetch( restUrl + 'colaboraciones', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': restNonce,
						'Content-Type': 'application/json'
					},
					body: JSON.stringify( {
						centro_emisor_id: emisorId,
						centro_receptor_id: receptorId,
						idea_proyecto: idea,
						curso_edades: edades,
						idiomas: idiomas,
						fechas_aprox: fechas
					} )
				} )
				.then( res => res.json() )
				.then( result => {
					btnSubmitMatch.disabled = false;
					btnSubmitMatch.textContent = '✈️ ' + ( i18n.proposeMatch || 'Enviar propuesta' );

					if ( result.success ) {
						drawerFeedback.className = 'radioedu-drawer-feedback radioedu-feedback-success';
						drawerFeedback.textContent = result.message || ( i18n.sendSuccess || '¡Propuesta enviada!' );
						drawerFeedback.style.display = 'block';
						matchForm.reset();
						setTimeout( closeMatchDrawer, 3000 );
					} else {
						drawerFeedback.className = 'radioedu-drawer-feedback radioedu-feedback-error';
						drawerFeedback.textContent = result.message || ( i18n.errorOccurred || 'Error al enviar.' );
						drawerFeedback.style.display = 'block';
					}
				} )
				.catch( () => {
					btnSubmitMatch.disabled = false;
					btnSubmitMatch.textContent = '✈️ ' + ( i18n.proposeMatch || 'Enviar propuesta' );
					drawerFeedback.className = 'radioedu-drawer-feedback radioedu-feedback-error';
					drawerFeedback.textContent = i18n.errorOccurred || 'Error de red al procesar la solicitud.';
					drawerFeedback.style.display = 'block';
				} );
			} );
		}

		// Toggle Vista de Mapa
		if ( btnToggleMap && mapView ) {
			btnToggleMap.addEventListener( 'click', function() {
				isMapView = ! isMapView;
				btnToggleMap.setAttribute( 'aria-pressed', isMapView ? 'true' : 'false' );

				if ( isMapView ) {
					mapView.style.display = 'block';
					cardsContainer.style.display = 'none';
					btnToggleMap.classList.add( 'is-active' );

					if ( ! leafletMap && typeof L !== 'undefined' ) {
						initLeafletMap();
					}
					updateMapMarkers( currentCentros );
				} else {
					mapView.style.display = 'none';
					cardsContainer.style.display = 'grid';
					btnToggleMap.classList.remove( 'is-active' );
				}
			} );
		}

		// Inicialización del mapa Leaflet
		function initLeafletMap() {
			const mapSettings = config.mapCenter || { lat: 39.4699, lng: -6.3708, zoom: 8 };
			leafletMap = L.map( 'radioedu-leaflet-map' ).setView( [ mapSettings.lat, mapSettings.lng ], mapSettings.zoom );

			L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 18,
				attribution: '© OpenStreetMap colaboradores | RadioEdu'
			} ).addTo( leafletMap );
		}

		function updateMapMarkers( centros ) {
			if ( ! leafletMap || typeof L === 'undefined' ) {
				return;
			}

			// Limpiar marcadores anteriores
			mapMarkers.forEach( m => leafletMap.removeLayer( m ) );
			mapMarkers = [];

			centros.forEach( function( centro ) {
				if ( centro.lat && centro.lng ) {
					const latNum = parseFloat( centro.lat );
					const lngNum = parseFloat( centro.lng );

					if ( ! isNaN( latNum ) && ! isNaN( lngNum ) ) {
						const marker = L.marker( [ latNum, lngNum ] ).addTo( leafletMap );
						marker.bindPopup(
							'<strong>' + encodeHTMLEntities( centro.nombre ) + '</strong><br>' +
							encodeHTMLEntities( centro.ubicacion || '' ) + '<br>' +
							'<a href="' + encodeURI( centro.enlace_perfil || '#' ) + '" style="color:#00838f;font-weight:600;">' + ( i18n.viewProfile || 'Ver perfil' ) + '</a>'
						);
						mapMarkers.push( marker );
					}
				}
			} );
		}

		function encodeHTMLEntities( str ) {
			return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
		}

		// Eventos de filtro
		if ( btnBuscar ) {
			btnBuscar.addEventListener( 'click', fetchCentros );
		}
		if ( sortSelect ) {
			sortSelect.addEventListener( 'change', fetchCentros );
		}
		if ( btnReset ) {
			btnReset.addEventListener( 'click', function() {
				if ( filterUbicacion ) { filterUbicacion.value = ''; }
				if ( filterIdioma ) { filterIdioma.value = ''; }
				if ( filterEtapa ) { filterEtapa.value = ''; }
				if ( filterTematica ) { filterTematica.value = ''; }
				if ( filterFormato ) { filterFormato.value = ''; }
				if ( filterSearch ) { filterSearch.value = ''; }
				fetchCentros();
			} );
		}

		// Carga inicial
		fetchCentros();
	} );
} )();
