<?php
/**
 * Plantilla del directorio Traspasando Fronteras.
 *
 * @package RadioEdu\Conecta
 *
 * @var array<int, WP_Term>|WP_Error $etapas
 * @var array<int, WP_Term>|WP_Error $idiomas
 * @var array<int, WP_Term>|WP_Error $tematicas
 * @var array<int, WP_Term>|WP_Error $formatos
 * @var array<int, WP_Term>|WP_Error $ubicaciones
 * @var int            $user_center_id
 * @var string         $user_center_name
 * @var array<int, array{id: int, nombre: string}> $user_centers_list
 * @var int            $drawer_receptor_id
 * @var string         $drawer_receptor_label
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="radioedu-conecta-app" class="radioedu-app-container">

	<!-- 1. HERO / BANNER PRINCIPAL -->
	<header class="radioedu-hero-banner">
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
	</header>

	<!-- 2. BARRA HORIZONTAL DE FILTROS FACETADOS -->
	<section class="radioedu-filters-bar" aria-label="<?php esc_attr_e( 'Filtros del directorio', 'radioedu-conecta' ); ?>">
		<form id="radioedu-filters-form" class="radioedu-filters-form">

			<!-- Filtro Ubicación -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-ubicacion" class="radioedu-filter-label">
					<span class="radioedu-icon" aria-hidden="true">📍</span> <?php esc_html_e( 'Ubicación', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-ubicacion" name="ubicacion" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier lugar', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $ubicaciones ) && ! is_wp_error( $ubicaciones ) ) : ?>
						<?php foreach ( $ubicaciones as $radioedu_conecta_term ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_term->slug ); ?>">
								<?php echo esc_html( $radioedu_conecta_term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Idioma -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-idioma" class="radioedu-filter-label">
					<span class="radioedu-icon" aria-hidden="true">💬</span> <?php esc_html_e( 'Idioma', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-idioma" name="idioma" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier idioma', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $idiomas ) && ! is_wp_error( $idiomas ) ) : ?>
						<?php foreach ( $idiomas as $radioedu_conecta_term ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_term->slug ); ?>">
								<?php echo esc_html( $radioedu_conecta_term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Etapa Educativa -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-etapa" class="radioedu-filter-label">
					<span class="radioedu-icon" aria-hidden="true">🎓</span> <?php esc_html_e( 'Etapa educativa', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-etapa" name="etapa" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquiera', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $etapas ) && ! is_wp_error( $etapas ) ) : ?>
						<?php foreach ( $etapas as $radioedu_conecta_term ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_term->slug ); ?>">
								<?php echo esc_html( $radioedu_conecta_term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Temas -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-tematica" class="radioedu-filter-label">
					<span class="radioedu-icon" aria-hidden="true">🏷️</span> <?php esc_html_e( 'Temas', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-tematica" name="tematica" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier tema', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $tematicas ) && ! is_wp_error( $tematicas ) ) : ?>
						<?php foreach ( $tematicas as $radioedu_conecta_term ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_term->slug ); ?>">
								<?php echo esc_html( $radioedu_conecta_term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Formato -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-formato" class="radioedu-filter-label">
					<span class="radioedu-icon" aria-hidden="true">🎙️</span> <?php esc_html_e( 'Formato', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-formato" name="formato" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier formato', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $formatos ) && ! is_wp_error( $formatos ) ) : ?>
						<?php foreach ( $formatos as $radioedu_conecta_term ) : ?>
							<option value="<?php echo esc_attr( $radioedu_conecta_term->slug ); ?>">
								<?php echo esc_html( $radioedu_conecta_term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Búsqueda libre: Busca colaboración con... -->
			<div class="radioedu-filter-group radioedu-search-group">
				<label for="radioedu-filter-search" class="radioedu-filter-label">
					<span class="radioedu-icon" aria-hidden="true">👥</span> <?php esc_html_e( 'Busca colaboración con...', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-filter-search" name="search" class="radioedu-filter-input" maxlength="200" placeholder="<?php esc_attr_e( 'Cualquier centro o temática...', 'radioedu-conecta' ); ?>" />
			</div>

			<!-- Botón de acción principal -->
			<div class="radioedu-filter-action">
				<button type="submit" id="radioedu-btn-buscar" class="radioedu-btn-primary">
					<span class="radioedu-btn-icon" aria-hidden="true">🔍</span> <?php esc_html_e( 'Buscar centros', 'radioedu-conecta' ); ?>
				</button>
			</div>

		</form>
	</section>

	<!-- 3. BARRA DE RESULTADOS Y CONTROLES -->
	<div class="radioedu-results-controls-bar">
		<div class="radioedu-results-count">
			<h2 id="radioedu-results-heading" class="radioedu-heading-count">
				<?php
				printf(
					/* translators: %s: Number of schools found. */
					esc_html__( 'Resultados (%s centros)', 'radioedu-conecta' ),
					'<span id="radioedu-count-number">0</span>'
				);
				?>
			</h2>
		</div>

		<div class="radioedu-controls-right">
			<!-- Toggle Vista de Mapa -->
			<button type="button" id="radioedu-btn-toggle-map" class="radioedu-btn-toggle" aria-pressed="false">
				<span class="radioedu-icon" aria-hidden="true">🗺️</span>
				<span id="radioedu-toggle-map-text"><?php esc_html_e( 'Vista de mapa', 'radioedu-conecta' ); ?></span>
			</button>

			<!-- Selector Ordenar por -->
			<div class="radioedu-sort-wrapper">
				<label for="radioedu-sort-select" class="radioedu-sort-label"><?php esc_html_e( 'Ordenar por', 'radioedu-conecta' ); ?></label>
				<select id="radioedu-sort-select" class="radioedu-sort-select">
					<option value="afines"><?php esc_html_e( 'Más afines', 'radioedu-conecta' ); ?></option>
					<option value="recientes"><?php esc_html_e( 'Más recientes', 'radioedu-conecta' ); ?></option>
					<option value="alfa"><?php esc_html_e( 'Alfabético (A-Z)', 'radioedu-conecta' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<!-- 4. CONTENIDO PRINCIPAL: CUADRÍCULA DE TARJETAS Y MAPA -->
	<main class="radioedu-main-layout">

		<!-- Vista Mapa Interactivo (oculta por defecto, con aviso RGPD previo a cargar teselas de OpenStreetMap) -->
		<div id="radioedu-map-view" class="radioedu-map-view" style="display: none;" aria-hidden="true">
			<div id="radioedu-map-consent" class="radioedu-no-results-state" style="padding: 32px; text-align: center;">
				<div class="radioedu-no-results-icon" aria-hidden="true">🗺️</div>
				<h3><?php esc_html_e( 'Mapa geográfico interactivo (OpenStreetMap)', 'radioedu-conecta' ); ?></h3>
				<p><?php esc_html_e( 'Al cargar el mapa cartográfico se establecerá conexión con los servidores externos de OpenStreetMap Foundation para descargar las imágenes del mapa.', 'radioedu-conecta' ); ?></p>
				<button type="button" id="radioedu-btn-accept-map" class="radioedu-btn-primary">
					<?php esc_html_e( 'Aceptar y mostrar el mapa interactivo', 'radioedu-conecta' ); ?>
				</button>
			</div>
			<div id="radioedu-leaflet-map" style="display: none; height: 520px; width: 100%; border-radius: 16px;"></div>
		</div>

		<!-- Cuadrícula de Tarjetas -->
		<div id="radioedu-cards-container" class="radioedu-cards-grid">
			<!-- Cargado dinámicamente vía REST API en directorio.js -->
		</div>

		<!-- Paginación: Cargar más -->
		<div id="radioedu-pagination-wrap" style="display: none; text-align: center; margin-top: 28px;">
			<button type="button" id="radioedu-btn-load-more" class="radioedu-btn-secondary">
				<?php esc_html_e( 'Cargar más centros', 'radioedu-conecta' ); ?>
			</button>
		</div>

		<!-- Estado de Carga (Spinner) -->
		<div id="radioedu-loading" class="radioedu-loading-state" style="display: none;" role="status">
			<div class="radioedu-spinner" aria-hidden="true"></div>
			<p><?php esc_html_e( 'Buscando centros afines en la red...', 'radioedu-conecta' ); ?></p>
		</div>

		<!-- Estado Sin Resultados -->
		<div id="radioedu-no-results" class="radioedu-no-results-state" style="display: none;">
			<div class="radioedu-no-results-icon" aria-hidden="true">📡</div>
			<h3><?php esc_html_e( 'No se han encontrado emisoras con estos filtros', 'radioedu-conecta' ); ?></h3>
			<p><?php esc_html_e( 'Prueba a seleccionar otros temas, idiomas o restablecer la búsqueda.', 'radioedu-conecta' ); ?></p>
			<button type="button" id="radioedu-btn-reset" class="radioedu-btn-secondary">
				<?php esc_html_e( 'Limpiar todos los filtros', 'radioedu-conecta' ); ?>
			</button>
		</div>

	</main>

	<!-- 5. PANEL LATERAL DESPLEGABLE: "SOLICITUD DE MATCH" (OFFCANVAS / DRAWER) -->
	<?php require __DIR__ . '/match-drawer.php'; ?>

</div>
