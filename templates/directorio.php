<?php
/**
 * Plantilla del directorio Traspasando Fronteras.
 *
 * The server renders the first page of results with these directives (store
 * radioedu-conecta/directorio). Without JavaScript the filters form is sent by GET and the
 * pages are links; the parts that need JavaScript (map, favourites, proposals) start hidden.
 *
 * @package RadioEdu\Conecta
 *
 * @var array<string, array<int, WP_Term>>             $filter_terms          Filter parameter => its terms.
 * @var array{filters: array<string, string>, search: string, orden: string, page: int} $search Search shown.
 * @var string                                         $base_url              URL of this page without the search.
 * @var string                                         $form_action           URL the filters form is sent to.
 * @var array<string, string>                          $form_fields           Hidden fields that keep the page URL.
 * @var string                                         $next_url              URL of the next page of results.
 * @var string                                         $prev_url              URL of the previous page, or ''.
 * @var string                                         $total_label           Number of results, formatted.
 * @var string                                         $login_url             Login URL that comes back here.
 * @var array<int, array{id: int, nombre: string}>     $user_centers_list     Stations of the current user.
 * @var int                                            $drawer_receptor_id    Preselected receiver (0 here).
 * @var string                                         $drawer_receptor_label Its label ('' here).
 */

declare( strict_types=1 );

use RadioEdu\Conecta\Frontend\DirectoryStore;
use RadioEdu\Conecta\Icons;

defined( 'ABSPATH' ) || exit;

$radioedu_conecta_filters = [
	'ubicacion' => [
		'icon'  => 'core/map-marker',
		'label' => __( 'Ubicación', 'radioedu-conecta' ),
		'any'   => __( 'Cualquier lugar', 'radioedu-conecta' ),
	],
	'idioma'    => [
		'icon'  => 'core/language',
		'label' => __( 'Idioma', 'radioedu-conecta' ),
		'any'   => __( 'Cualquier idioma', 'radioedu-conecta' ),
	],
	'etapa'     => [
		'icon'  => 'radioedu-conecta/graduation',
		'label' => __( 'Etapa educativa', 'radioedu-conecta' ),
		'any'   => __( 'Cualquiera', 'radioedu-conecta' ),
	],
	'tematica'  => [
		'icon'  => 'core/tag',
		'label' => __( 'Temas', 'radioedu-conecta' ),
		'any'   => __( 'Cualquier tema', 'radioedu-conecta' ),
	],
	'formato'   => [
		'icon'  => 'radioedu-conecta/microphone',
		'label' => __( 'Formato', 'radioedu-conecta' ),
		'any'   => __( 'Cualquier formato', 'radioedu-conecta' ),
	],
];
$radioedu_conecta_orders  = [
	'afines'    => __( 'Más afines', 'radioedu-conecta' ),
	'recientes' => __( 'Más recientes', 'radioedu-conecta' ),
	'alfa'      => __( 'Alfabético (A-Z)', 'radioedu-conecta' ),
];
?>

<div id="radioedu-conecta-app" class="radioedu-app-container" data-wp-interactive="<?php echo esc_attr( DirectoryStore::NAMESPACE ); ?>" data-wp-init="callbacks.init">

	<!-- 1. HERO / BANNER PRINCIPAL (a div, not <header>: outside the theme's <main> a header would become a second banner landmark) -->
	<div class="radioedu-hero-banner">
		<div class="radioedu-hero-content">
			<h2 class="radioedu-hero-title"><?php esc_html_e( 'Traspasando Fronteras', 'radioedu-conecta' ); ?></h2>
			<p class="radioedu-hero-subtitle">
				<?php esc_html_e( 'Conectamos radios escolares de Extremadura con otras emisoras de España y del mundo.', 'radioedu-conecta' ); ?>
			</p>
		</div>
		<div class="radioedu-hero-badge" aria-hidden="true">
			<div class="radioedu-sticky-note">
				<span class="radioedu-note-quote"><?php esc_html_e( 'Diferentes voces, un mismo mundo', 'radioedu-conecta' ); ?></span>
				<span class="radioedu-note-caption"><?php esc_html_e( 'RADIOS ESCOLARES QUE HACEN UN MUNDO MÁS GRANDE 🌍', 'radioedu-conecta' ); ?></span>
			</div>
		</div>
	</div>

	<!-- 2. BARRA HORIZONTAL DE FILTROS FACETADOS (sent by GET without JavaScript) -->
	<section class="radioedu-filters-bar" aria-label="<?php esc_attr_e( 'Filtros del directorio', 'radioedu-conecta' ); ?>">
		<form id="radioedu-filters-form" class="radioedu-filters-form" method="get" action="<?php echo esc_url( $form_action ); ?>" data-wp-on--submit="actions.search">
			<?php foreach ( $form_fields as $radioedu_conecta_name => $radioedu_conecta_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $radioedu_conecta_name ); ?>" value="<?php echo esc_attr( $radioedu_conecta_value ); ?>" />
			<?php endforeach; ?>

			<?php foreach ( $radioedu_conecta_filters as $radioedu_conecta_param => $radioedu_conecta_filter ) : ?>
				<div class="radioedu-filter-group">
					<label for="radioedu-filter-<?php echo esc_attr( $radioedu_conecta_param ); ?>" class="radioedu-filter-label">
						<?php Icons::render( $radioedu_conecta_filter['icon'] ); ?> <?php echo esc_html( $radioedu_conecta_filter['label'] ); ?>
					</label>
					<select id="radioedu-filter-<?php echo esc_attr( $radioedu_conecta_param ); ?>" name="<?php echo esc_attr( $radioedu_conecta_param ); ?>" class="radioedu-filter-select">
						<option value=""><?php echo esc_html( $radioedu_conecta_filter['any'] ); ?></option>
						<?php foreach ( $filter_terms[ $radioedu_conecta_param ] ?? [] as $radioedu_conecta_term ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_term->slug ); ?>" <?php selected( $search['filters'][ $radioedu_conecta_param ] ?? '', $radioedu_conecta_term->slug ); ?>>
								<?php echo esc_html( $radioedu_conecta_term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>

			<!-- Búsqueda libre: Busca colaboración con... -->
			<div class="radioedu-filter-group radioedu-search-group">
				<label for="radioedu-filter-search" class="radioedu-filter-label">
					<?php Icons::render( 'core/people' ); ?> <?php esc_html_e( 'Busca colaboración con...', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-filter-search" name="<?php echo esc_attr( DirectoryStore::SEARCH_PARAM ); ?>" class="radioedu-filter-input" maxlength="200" value="<?php echo esc_attr( $search['search'] ); ?>" placeholder="<?php esc_attr_e( 'Cualquier centro o temática...', 'radioedu-conecta' ); ?>" />
			</div>

			<!-- Botón de acción principal -->
			<div class="radioedu-filter-action">
				<button type="submit" id="radioedu-btn-buscar" class="radioedu-btn-primary">
					<?php Icons::render( 'core/search' ); ?> <?php esc_html_e( 'Buscar centros', 'radioedu-conecta' ); ?>
				</button>
			</div>

		</form>
	</section>

	<!-- 3. RESULTADOS: region named by its heading. The theme already wraps the page content in its <main>. -->
	<section class="radioedu-results" aria-labelledby="radioedu-results-heading">
		<div class="radioedu-results-controls-bar">
			<div class="radioedu-results-count">
				<h2 id="radioedu-results-heading" class="radioedu-heading-count">
					<?php
					printf(
						/* translators: %s: Number of schools found. */
						esc_html__( 'Resultados (%s centros)', 'radioedu-conecta' ),
						'<span id="radioedu-count-number" data-wp-text="state.totalLabel">' . esc_html( $total_label ) . '</span>'
					);
					?>
				</h2>
			</div>

			<div class="radioedu-controls-right">
				<!-- Toggle Vista de Mapa -->
				<button type="button" id="radioedu-btn-toggle-map" class="radioedu-btn-toggle" aria-pressed="false" hidden data-wp-bind--hidden="!state.hasJs" data-wp-bind--aria-pressed="state.isMapView" data-wp-class--is-active="state.isMapView" data-wp-on--click="actions.toggleMap">
					<?php Icons::render( 'radioedu-conecta/map' ); ?>
					<span id="radioedu-toggle-map-text"><?php esc_html_e( 'Vista de mapa', 'radioedu-conecta' ); ?></span>
				</button>

				<!-- Selector Ordenar por (part of the filters form) -->
				<div class="radioedu-sort-wrapper">
					<label for="radioedu-sort-select" class="radioedu-sort-label"><?php esc_html_e( 'Ordenar por', 'radioedu-conecta' ); ?></label>
					<select id="radioedu-sort-select" class="radioedu-sort-select" name="<?php echo esc_attr( DirectoryStore::ORDER_PARAM ); ?>" form="radioedu-filters-form" data-wp-on--change="actions.search">
						<?php foreach ( $radioedu_conecta_orders as $radioedu_conecta_order => $radioedu_conecta_label ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_order ); ?>" <?php selected( $search['orden'], $radioedu_conecta_order ); ?>><?php echo esc_html( $radioedu_conecta_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>

		<!-- 4. CONTENIDO PRINCIPAL: CUADRÍCULA DE TARJETAS Y MAPA -->
		<div class="radioedu-main-layout">
			<p id="radioedu-directory-error" role="alert" hidden data-wp-bind--hidden="!state.hasError"><?php esc_html_e( 'No se han podido cargar los centros. Vuelve a pulsar Buscar centros para reintentar.', 'radioedu-conecta' ); ?></p>
			<noscript><p id="radioedu-js-fallback" class="radioedu-js-fallback"><?php esc_html_e( 'El mapa, los favoritos y las propuestas de colaboración necesitan JavaScript.', 'radioedu-conecta' ); ?></p></noscript>

			<!-- Vista Mapa Interactivo (oculta por defecto, con aviso RGPD previo a cargar teselas de OpenStreetMap) -->
			<div id="radioedu-map-view" class="radioedu-map-view" hidden data-wp-bind--hidden="!state.isMapView" data-wp-watch="callbacks.syncMap">
				<div id="radioedu-map-consent" class="radioedu-no-results-state" style="padding: 32px; text-align: center;" data-wp-bind--hidden="state.hasMapConsent">
					<div class="radioedu-no-results-icon"><?php Icons::render( 'radioedu-conecta/map' ); ?></div>
					<h3><?php esc_html_e( 'Mapa geográfico interactivo (OpenStreetMap)', 'radioedu-conecta' ); ?></h3>
					<p><?php esc_html_e( 'Al cargar el mapa cartográfico se establecerá conexión con los servidores externos de OpenStreetMap Foundation para descargar las imágenes del mapa.', 'radioedu-conecta' ); ?></p>
					<p><?php esc_html_e( 'La aceptación dura hasta que cierres el navegador y puedes retirarla en cualquier momento.', 'radioedu-conecta' ); ?></p>
					<button type="button" id="radioedu-btn-accept-map" class="radioedu-btn-primary" data-wp-on--click="actions.acceptMap">
						<?php esc_html_e( 'Aceptar y mostrar el mapa interactivo', 'radioedu-conecta' ); ?>
					</button>
				</div>
				<div id="radioedu-leaflet-map" style="height: 520px; width: 100%; border-radius: 16px;" hidden data-wp-bind--hidden="!state.hasMapConsent"></div>
				<div id="radioedu-map-consent-status" class="radioedu-map-consent-status" hidden data-wp-bind--hidden="!state.hasMapConsent">
					<p><?php esc_html_e( 'Has aceptado cargar el mapa de OpenStreetMap hasta que cierres el navegador.', 'radioedu-conecta' ); ?></p>
					<button type="button" id="radioedu-btn-withdraw-map" class="radioedu-btn-secondary" data-wp-on--click="actions.withdrawMap">
						<?php esc_html_e( 'Retirar el consentimiento del mapa', 'radioedu-conecta' ); ?>
					</button>
				</div>
				<p id="radioedu-map-note" class="radioedu-map-note" role="status" hidden data-wp-bind--hidden="!state.mapNoteText" data-wp-text="state.mapNoteText"></p>
			</div>

			<!-- Cuadrícula de Tarjetas -->
			<div id="radioedu-cards-container" class="radioedu-cards-grid" data-wp-bind--hidden="state.cardsHidden">
				<template data-wp-each--centro="state.centros" data-wp-each-key="context.centro.id">
					<article class="radioedu-card" data-wp-bind--data-id="context.centro.id">
						<div class="radioedu-card-cover" data-wp-style--background-image="state.coverStyle">
							<button type="button" class="radioedu-card-fav-btn" hidden data-wp-bind--hidden="!state.hasJs" data-wp-class--is-active="state.isFavorite" data-wp-bind--aria-pressed="state.isFavorite" data-wp-bind--aria-label="state.favoriteLabel" data-wp-on--click="actions.toggleFavorite">
								<span data-wp-bind--hidden="state.isFavorite"><?php Icons::render( 'core/star-empty' ); ?></span>
								<span data-wp-bind--hidden="!state.isFavorite"><?php Icons::render( 'core/star-filled' ); ?></span>
							</button>
							<!-- Decorativo: el nombre ya figura en el título de la tarjeta. -->
							<img class="radioedu-card-logo" alt="" loading="lazy" data-wp-bind--src="state.logoSrc" />
						</div>
						<div class="radioedu-card-body">
							<h3 class="radioedu-card-title" data-wp-text="context.centro.nombre"></h3>
							<div class="radioedu-card-location">
								<span class="radioedu-flag-badge" data-wp-text="state.flag"></span>
								<span data-wp-text="state.locationText"></span>
							</div>
							<div class="radioedu-card-tags" data-wp-bind--hidden="!state.cardTags.length">
								<template data-wp-each--tag="state.cardTags">
									<span data-wp-bind--class="state.tagClass" data-wp-text="context.tag.name"></span>
								</template>
							</div>
							<div class="radioedu-card-footer">
								<a class="radioedu-btn-secondary" data-wp-bind--href="state.profileUrl"><?php esc_html_e( 'Ver perfil', 'radioedu-conecta' ); ?></a>
								<button type="button" class="radioedu-btn-match" data-wp-bind--hidden="state.matchHidden" data-wp-bind--disabled="state.isUnavailable" data-wp-on--click="actions.openDrawer">
									<span data-wp-bind--hidden="state.isUnavailable"><?php Icons::render( 'radioedu-conecta/send' ); ?> <?php esc_html_e( 'Proponer match', 'radioedu-conecta' ); ?></span>
									<span data-wp-bind--hidden="!state.isUnavailable"><?php esc_html_e( 'No disponible', 'radioedu-conecta' ); ?></span>
								</button>
							</div>
						</div>
					</article>
				</template>
			</div>

			<!-- Paginación: links without JavaScript, more cards in place with it -->
			<nav id="radioedu-pagination-wrap" class="radioedu-pagination" aria-label="<?php esc_attr_e( 'Páginas de resultados', 'radioedu-conecta' ); ?>" data-wp-bind--hidden="state.paginationHidden">
				<a id="radioedu-btn-load-prev" class="radioedu-btn-secondary" href="<?php echo esc_url( $prev_url ); ?>" data-wp-bind--href="state.prevPageUrl" data-wp-bind--hidden="state.prevHidden"><?php esc_html_e( 'Centros anteriores', 'radioedu-conecta' ); ?></a>
				<a id="radioedu-btn-load-more" class="radioedu-btn-secondary" href="<?php echo esc_url( $next_url ); ?>" data-wp-bind--href="state.nextPageUrl" data-wp-bind--hidden="state.nextHidden" data-wp-bind--aria-disabled="state.isAppending" data-wp-on--click="actions.loadMore"><?php esc_html_e( 'Cargar más centros', 'radioedu-conecta' ); ?></a>
			</nav>

			<!-- Estado de Carga (Spinner) -->
			<div id="radioedu-loading" class="radioedu-loading-state" role="status" data-wp-bind--hidden="state.loadingHidden">
				<div class="radioedu-spinner" aria-hidden="true"></div>
				<p><?php esc_html_e( 'Buscando centros afines en la red...', 'radioedu-conecta' ); ?></p>
			</div>

			<!-- Estado Sin Resultados -->
			<div id="radioedu-no-results" class="radioedu-no-results-state" data-wp-bind--hidden="state.noResultsHidden">
				<div class="radioedu-no-results-icon"><?php Icons::render( 'core/rss' ); ?></div>
				<h3><?php esc_html_e( 'No se han encontrado emisoras con estos filtros', 'radioedu-conecta' ); ?></h3>
				<p><?php esc_html_e( 'Prueba a seleccionar otros temas, idiomas o restablecer la búsqueda.', 'radioedu-conecta' ); ?></p>
				<a id="radioedu-btn-reset" class="radioedu-btn-secondary" href="<?php echo esc_url( $base_url ); ?>" data-wp-on--click="actions.resetFilters">
					<?php esc_html_e( 'Limpiar todos los filtros', 'radioedu-conecta' ); ?>
				</a>
			</div>

		</div>
	</section>

	<!-- 5. PANEL LATERAL DESPLEGABLE: "SOLICITUD DE MATCH" (OFFCANVAS / DRAWER) -->
	<?php require __DIR__ . '/match-drawer.php'; ?>

</div>
