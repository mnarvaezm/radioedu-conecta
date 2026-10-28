<?php
/**
 * Plantilla de ficha de perfil individual de una emisora escolar (radioedu_centro).
 *
 * @package RadioEdu\Conecta
 *
 * @var int                      $post_id
 * @var string                   $title
 * @var string                   $cover_url
 * @var string                   $logo_url
 * @var string                   $flag_emoji
 * @var string                   $ubicacion
 * @var string                   $edades
 * @var string                   $idiomas_texto
 * @var string                   $podcast_url
 * @var string                   $web_centro
 * @var bool                     $disponible
 * @var array<int, array>        $tags
 * @var string                   $directorio_url
 * @var string                   $raw_description_html
 * @var array<int, array>        $user_centers_list
 */

defined( 'ABSPATH' ) || exit;
?>

<article class="radioedu-single-profile">

	<!-- Barra superior de retorno al directorio -->
	<div class="radioedu-profile-topbar">
		<a href="<?php echo esc_url( $directorio_url ); ?>" class="radioedu-profile-back-link">
			← <?php esc_html_e( 'Volver al directorio Traspasando Fronteras', 'radioedu-conecta' ); ?>
		</a>
	</div>

	<div class="radioedu-profile-card">

		<!-- 1. IMAGEN DE CABECERA ARRIBA -->
		<div class="radioedu-profile-cover" <?php echo '' !== $cover_url ? 'style="background-image: url(\'' . esc_url( $cover_url ) . '\');"' : ''; ?>>
			<div class="radioedu-profile-cover-overlay"></div>
			<span class="radioedu-profile-status-badge <?php echo $disponible ? 'is-available' : 'is-unavailable'; ?>">
				<?php
				echo $disponible
					? '🟢 ' . esc_html__( 'Disponible para colaborar', 'radioedu-conecta' )
					: '⚪ ' . esc_html__( 'No disponible actualmente', 'radioedu-conecta' );
				?>
			</span>
		</div>

		<!-- 2. DEBAJO DE LA CABECERA, A LA IZQUIERDA: NOMBRE Y DATOS DE LA TARJETA -->
		<div class="radioedu-profile-header-info">

			<div class="radioedu-profile-identity">
				<div class="radioedu-profile-logo-wrap">
					<?php if ( '' !== $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="radioedu-profile-logo" />
					<?php else : ?>
						<div class="radioedu-profile-logo radioedu-profile-logo-placeholder" aria-hidden="true">🎙️</div>
					<?php endif; ?>
				</div>

				<div class="radioedu-profile-title-block">
					<h1 class="radioedu-profile-title"><?php echo esc_html( $title ); ?></h1>

					<div class="radioedu-profile-location">
						<span class="radioedu-flag-badge" aria-hidden="true"><?php echo esc_html( $flag_emoji ); ?></span>
						<span><?php echo esc_html( '' !== $ubicacion ? $ubicacion : __( 'Extremadura', 'radioedu-conecta' ) ); ?></span>
					</div>
				</div>
			</div>

			<!-- Píldoras de Categorías / Tags (Formatos, Temáticas, Etapas) -->
			<?php if ( ! empty( $tags ) ) : ?>
				<div class="radioedu-profile-tags">
					<?php foreach ( $tags as $tag ) : ?>
						<span class="radioedu-tag radioedu-tag-<?php echo esc_attr( $tag['color'] ); ?>">
							<?php echo esc_html( $tag['name'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Metadatos adicionales (Edades, Idiomas, Enlaces) alineados a la izquierda -->
			<div class="radioedu-profile-meta-grid">
				<?php if ( '' !== $edades ) : ?>
					<div class="radioedu-profile-meta-item">
						<span class="radioedu-profile-meta-icon" aria-hidden="true">👥</span>
						<div>
							<span class="radioedu-profile-meta-label"><?php esc_html_e( 'Cursos / Edades', 'radioedu-conecta' ); ?></span>
							<strong class="radioedu-profile-meta-value"><?php echo esc_html( $edades ); ?></strong>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $idiomas_texto ) : ?>
					<div class="radioedu-profile-meta-item">
						<span class="radioedu-profile-meta-icon" aria-hidden="true">💬</span>
						<div>
							<span class="radioedu-profile-meta-label"><?php esc_html_e( 'Idiomas de trabajo', 'radioedu-conecta' ); ?></span>
							<strong class="radioedu-profile-meta-value"><?php echo esc_html( $idiomas_texto ); ?></strong>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Botones de acción (Proponer match directo / Podcast / Web del centro) -->
			<div class="radioedu-profile-actions">
				<?php if ( $disponible ) : ?>
					<button
						type="button"
						id="radioedu-single-btn-match"
						class="radioedu-btn-match radioedu-profile-btn-inline"
						data-id="<?php echo esc_attr( (string) $post_id ); ?>"
						data-nombre="<?php echo esc_attr( $title ); ?>"
						data-ubicacion="<?php echo esc_attr( '' !== $ubicacion ? $ubicacion : __( 'Extremadura', 'radioedu-conecta' ) ); ?>"
					>
						<span>✈️</span> <?php esc_html_e( 'Proponer match', 'radioedu-conecta' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="radioedu-btn-match radioedu-profile-btn-inline" disabled>
						<?php esc_html_e( 'No disponible', 'radioedu-conecta' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( '' !== $podcast_url ) : ?>
					<a href="<?php echo esc_url( $podcast_url ); ?>" target="_blank" rel="noopener noreferrer" class="radioedu-btn-primary">
						🎧 <?php esc_html_e( 'Escuchar Podcast / Emisora', 'radioedu-conecta' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( '' !== $web_centro ) : ?>
					<a href="<?php echo esc_url( $web_centro ); ?>" target="_blank" rel="noopener noreferrer" class="radioedu-btn-secondary radioedu-profile-btn-inline">
						🌐 <?php esc_html_e( 'Web del centro', 'radioedu-conecta' ); ?>
					</a>
				<?php endif; ?>
			</div>

		</div>

		<!-- 3. DEBAJO: TEXTO DESCRIPTIVO -->
		<div class="radioedu-profile-description-section">
			<h2 class="radioedu-profile-section-heading">
				<?php esc_html_e( 'Sobre nuestra emisora escolar', 'radioedu-conecta' ); ?>
			</h2>
			<div class="radioedu-profile-description-content">
				<?php echo wp_kses_post( $raw_description_html ); ?>
			</div>
		</div>

	</div>

	<!-- 4. PANEL LATERAL DESPLEGABLE: "SOLICITUD DE MATCH" (OFFCANVAS / DRAWER) -->
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

			<!-- Centro con el que colaborar (Preseleccionado al centro del perfil) -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-receptor-txt" class="radioedu-form-label">
					<span class="radioedu-icon">🏛️</span> <?php esc_html_e( 'Centro con el que quieres colaborar', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-match-receptor-txt" class="radioedu-form-input" value="<?php echo esc_attr( $title . ( '' !== $ubicacion ? ' (' . $ubicacion . ')' : '' ) ); ?>" readonly />
				<input type="hidden" id="radioedu-match-receptor" name="centro_receptor_id" value="<?php echo esc_attr( (string) $post_id ); ?>" required />
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

</article>
