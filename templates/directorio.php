<?php
/**
 * Plantilla del directorio Traspasando Fronteras.
 *
 * @package RadioEdu\Conecta
 *
 * @var array<WP_Term> $etapas
 * @var array<WP_Term> $idiomas
 * @var array<WP_Term> $tematicas
 * @var array<WP_Term> $formatos
 * @var array<WP_Term> $ubicaciones
 * @var int            $user_center_id
 * @var string         $user_center_name
 * @var array          $user_centers_list
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="radioedu-conecta-app" class="radioedu-app-container">

	<!-- 1. HERO / BANNER PRINCIPAL -->
	<header class="radioedu-hero-banner">
		<div class="radioedu-hero-content">
			<h1 class="radioedu-hero-title"><?php esc_html_e( 'Traspasando Fronteras', 'radioedu-conecta' ); ?></h1>
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
		<form id="radioedu-filters-form" class="radioedu-filters-form" onsubmit="return false;">

			<!-- Filtro Ubicación -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-ubicacion" class="radioedu-filter-label">
					<span class="radioedu-icon">📍</span> <?php esc_html_e( 'Ubicación', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-ubicacion" name="ubicacion" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier lugar', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $ubicaciones ) && ! is_wp_error( $ubicaciones ) ) : ?>
						<?php foreach ( $ubicaciones as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Idioma -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-idioma" class="radioedu-filter-label">
					<span class="radioedu-icon">💬</span> <?php esc_html_e( 'Idioma', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-idioma" name="idioma" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier idioma', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $idiomas ) && ! is_wp_error( $idiomas ) ) : ?>
						<?php foreach ( $idiomas as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Etapa Educativa -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-etapa" class="radioedu-filter-label">
					<span class="radioedu-icon">🎓</span> <?php esc_html_e( 'Etapa educativa', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-etapa" name="etapa" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquiera', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $etapas ) && ! is_wp_error( $etapas ) ) : ?>
						<?php foreach ( $etapas as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Temas -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-tematica" class="radioedu-filter-label">
					<span class="radioedu-icon">🏷️</span> <?php esc_html_e( 'Temas', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-tematica" name="tematica" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier tema', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $tematicas ) && ! is_wp_error( $tematicas ) ) : ?>
						<?php foreach ( $tematicas as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Filtro Formato -->
			<div class="radioedu-filter-group">
				<label for="radioedu-filter-formato" class="radioedu-filter-label">
					<span class="radioedu-icon">🎙️</span> <?php esc_html_e( 'Formato', 'radioedu-conecta' ); ?>
				</label>
				<select id="radioedu-filter-formato" name="formato" class="radioedu-filter-select">
					<option value=""><?php esc_html_e( 'Cualquier formato', 'radioedu-conecta' ); ?></option>
					<?php if ( ! empty( $formatos ) && ! is_wp_error( $formatos ) ) : ?>
						<?php foreach ( $formatos as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<!-- Búsqueda libre: Busca colaboración con... -->
			<div class="radioedu-filter-group radioedu-search-group">
				<label for="radioedu-filter-search" class="radioedu-filter-label">
					<span class="radioedu-icon">👥</span> <?php esc_html_e( 'Busca colaboración con...', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-filter-search" name="search" class="radioedu-filter-input" placeholder="<?php esc_attr_e( 'Cualquier centro o temática...', 'radioedu-conecta' ); ?>" />
			</div>

			<!-- Botón de acción principal -->
			<div class="radioedu-filter-action">
				<button type="button" id="radioedu-btn-buscar" class="radioedu-btn-primary">
					<span class="radioedu-btn-icon">🔍</span> <?php esc_html_e( 'Buscar centros', 'radioedu-conecta' ); ?>
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
					/* translators: %s: Número de centros */
					esc_html__( 'Resultados (%s centros)', 'radioedu-conecta' ),
					'<span id="radioedu-count-number">0</span>'
				);
				?>
			</h2>
		</div>

		<div class="radioedu-controls-right">
			<!-- Toggle Vista de Mapa -->
			<button type="button" id="radioedu-btn-toggle-map" class="radioedu-btn-toggle" aria-pressed="false">
				<span class="radioedu-icon">🗺️</span>
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
				<div class="radioedu-no-results-icon">🗺️</div>
				<h3><?php esc_html_e( 'Mapa geográfico interactivo (OpenStreetMap)', 'radioedu-conecta' ); ?></h3>
				<p><?php esc_html_e( 'Al cargar el mapa cartográfico se establecerá conexión con los servidores externos de OpenStreetMap Foundation para descargar las imágenes del mapa.', 'radioedu-conecta' ); ?></p>
				<button type="button" id="radioedu-btn-accept-map" class="radioedu-btn-primary">
					<?php esc_html_e( 'Aceptar y mostrar el mapa interactivo', 'radioedu-conecta' ); ?>
				</button>
			</div>
			<div id="radioedu-leaflet-map" style="display: none; height: 520px; width: 100%; border-radius: 16px;"></div>
		</div>

		<!-- Cuadrícula de Tarjetas -->
		<div id="radioedu-cards-container" class="radioedu-cards-grid" aria-live="polite">
			<!-- Cargado dinámicamente vía REST API en directorio.js -->
		</div>

		<!-- Paginación: Cargar más -->
		<div id="radioedu-pagination-wrap" style="display: none; text-align: center; margin-top: 28px;">
			<button type="button" id="radioedu-btn-load-more" class="radioedu-btn-secondary">
				<?php esc_html_e( 'Cargar más centros', 'radioedu-conecta' ); ?>
			</button>
		</div>

		<!-- Estado de Carga (Spinner) -->
		<div id="radioedu-loading" class="radioedu-loading-state" style="display: none;">
			<div class="radioedu-spinner" aria-label="<?php esc_attr_e( 'Cargando...', 'radioedu-conecta' ); ?>"></div>
			<p><?php esc_html_e( 'Buscando centros afines en la red...', 'radioedu-conecta' ); ?></p>
		</div>

		<!-- Estado Sin Resultados -->
		<div id="radioedu-no-results" class="radioedu-no-results-state" style="display: none;">
			<div class="radioedu-no-results-icon">📡</div>
			<h3><?php esc_html_e( 'No se han encontrado emisoras con estos filtros', 'radioedu-conecta' ); ?></h3>
			<p><?php esc_html_e( 'Prueba a seleccionar otros temas, idiomas o restablecer la búsqueda.', 'radioedu-conecta' ); ?></p>
			<button type="button" id="radioedu-btn-reset" class="radioedu-btn-secondary">
				<?php esc_html_e( 'Limpiar todos los filtros', 'radioedu-conecta' ); ?>
			</button>
		</div>

	</main>

	<!-- 5. PANEL LATERAL DESPLEGABLE: "SOLICITUD DE MATCH" (OFFCANVAS / DRAWER) -->
	<div id="radioedu-drawer-overlay" class="radioedu-drawer-overlay" aria-hidden="true"></div>

	<aside id="radioedu-match-drawer" class="radioedu-match-drawer" aria-labelledby="radioedu-drawer-title" aria-modal="true" role="dialog" style="display: none;">
		<div class="radioedu-drawer-header">
			<div>
				<h3 id="radioedu-drawer-title" class="radioedu-drawer-title"><?php esc_html_e( 'Solicitud de match', 'radioedu-conecta' ); ?></h3>
				<p class="radioedu-drawer-subtitle"><?php esc_html_e( '¡Cuéntanos tu idea y empezad a crear juntos!', 'radioedu-conecta' ); ?></p>
			</div>
			<button type="button" id="radioedu-btn-close-drawer" class="radioedu-btn-close" aria-label="<?php esc_attr_e( 'Cerrar panel', 'radioedu-conecta' ); ?>">✕</button>
		</div>

		<form id="radioedu-match-form" class="radioedu-drawer-form">

			<!-- Tu Centro -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-emisor" class="radioedu-form-label">
					<span class="radioedu-icon">🏫</span> <?php esc_html_e( 'Tu centro', 'radioedu-conecta' ); ?>
				</label>
				<?php if ( ! empty( $user_centers_list ) ) : ?>
					<select id="radioedu-match-emisor" name="centro_emisor_id" class="radioedu-form-select" required>
						<?php foreach ( $user_centers_list as $c_item ) : ?>
							<option value="<?php echo esc_attr( (string) $c_item['id'] ); ?>">
								<?php echo esc_html( $c_item['nombre'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" id="radioedu-match-emisor-txt" class="radioedu-form-input" value="<?php echo esc_attr( is_user_logged_in() ? __( 'Sin centro asignado como coordinador', 'radioedu-conecta' ) : __( 'Debes iniciar sesión para enviar propuestas', 'radioedu-conecta' ) ); ?>" disabled />
					<input type="hidden" id="radioedu-match-emisor" name="centro_emisor_id" value="" />
				<?php endif; ?>
			</div>

			<!-- Centro con el que colaborar (Preseleccionado) -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-receptor-txt" class="radioedu-form-label">
					<span class="radioedu-icon">🏛️</span> <?php esc_html_e( 'Centro con el que quieres colaborar', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-match-receptor-txt" class="radioedu-form-input" value="" readonly />
				<input type="hidden" id="radioedu-match-receptor" name="centro_receptor_id" value="" required />
			</div>

			<!-- Idea de Proyecto -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-idea" class="radioedu-form-label">
					<span class="radioedu-icon">💡</span> <?php esc_html_e( 'Idea de proyecto', 'radioedu-conecta' ); ?>
				</label>
				<textarea id="radioedu-match-idea" name="idea_proyecto" class="radioedu-form-textarea" rows="4" placeholder="<?php esc_attr_e( 'Ejemplo: Queremos crear un podcast conjunto sobre la vida en entornos rurales, con entrevistas a estudiantes de ambos centros...', 'radioedu-conecta' ); ?>" required></textarea>
			</div>

			<!-- Curso / Edades e Idioma en 2 columnas -->
			<div class="radioedu-form-row">
				<div class="radioedu-form-field radioedu-col-6">
					<label for="radioedu-match-edades" class="radioedu-form-label">
						<span class="radioedu-icon">👥</span> <?php esc_html_e( 'Curso o edades', 'radioedu-conecta' ); ?>
					</label>
					<input type="text" id="radioedu-match-edades" name="curso_edades" class="radioedu-form-input" placeholder="<?php esc_attr_e( 'Ej: 12-14 años (1º-2º ESO)', 'radioedu-conecta' ); ?>" />
				</div>
				<div class="radioedu-form-field radioedu-col-6">
					<label for="radioedu-match-idiomas" class="radioedu-form-label">
						<span class="radioedu-icon">💬</span> <?php esc_html_e( 'Idioma(s)', 'radioedu-conecta' ); ?>
					</label>
					<input type="text" id="radioedu-match-idiomas" name="idiomas" class="radioedu-form-input" placeholder="<?php esc_attr_e( 'Ej: Español / Inglés', 'radioedu-conecta' ); ?>" />
				</div>
			</div>

			<!-- Fechas aproximadas -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-fechas" class="radioedu-form-label">
					<span class="radioedu-icon">📅</span> <?php esc_html_e( 'Fechas aproximadas', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-match-fechas" name="fechas_aprox" class="radioedu-form-input" placeholder="<?php esc_attr_e( 'Ej: 2º Trimestre (Enero - Marzo)', 'radioedu-conecta' ); ?>" />
			</div>

			<!-- Feedback de mensaje -->
			<div id="radioedu-drawer-feedback" class="radioedu-drawer-feedback" style="display: none;" role="alert"></div>

			<!-- Botón de envío -->
			<div class="radioedu-form-action">
				<button type="submit" id="radioedu-btn-submit-match" class="radioedu-btn-submit">
					<span class="radioedu-btn-icon">✈️</span> <?php esc_html_e( 'Enviar propuesta', 'radioedu-conecta' ); ?>
				</button>
			</div>

		</form>

		<!-- Guía en 3 pasos al pie del panel -->
		<footer class="radioedu-drawer-guide">
			<div class="radioedu-guide-step">
				<span class="radioedu-guide-icon">🔍</span>
				<strong><?php esc_html_e( '1. Encuentra', 'radioedu-conecta' ); ?></strong>
				<p><?php esc_html_e( 'Busca centros afines a tus intereses', 'radioedu-conecta' ); ?></p>
			</div>
			<div class="radioedu-guide-arrow">➔</div>
			<div class="radioedu-guide-step">
				<span class="radioedu-guide-icon">👥</span>
				<strong><?php esc_html_e( '2. Conecta', 'radioedu-conecta' ); ?></strong>
				<p><?php esc_html_e( 'Envía tu propuesta de match', 'radioedu-conecta' ); ?></p>
			</div>
			<div class="radioedu-guide-arrow">➔</div>
			<div class="radioedu-guide-step">
				<span class="radioedu-guide-icon">🎙️</span>
				<strong><?php esc_html_e( '3. Crea', 'radioedu-conecta' ); ?></strong>
				<p><?php esc_html_e( '¡Desarrollad juntos vuestro proyecto!', 'radioedu-conecta' ); ?></p>
			</div>
		</footer>

	</aside>

</div>
