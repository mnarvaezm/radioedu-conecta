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
 * @var array<int, array{name: string, color: string}> $tags
 * @var string                   $directorio_url
 * @var string                   $raw_description_html
 * @var array<int, array{id: int, nombre: string}> $user_centers_list
 * @var int                      $drawer_receptor_id
 * @var string                   $drawer_receptor_label
 */

declare( strict_types=1 );

use RadioEdu\Conecta\Icons;

defined( 'ABSPATH' ) || exit;
?>

<article class="radioedu-single-profile">

	<!-- Barra superior de retorno al directorio -->
	<div class="radioedu-profile-topbar">
		<a href="<?php echo esc_url( $directorio_url ); ?>" class="radioedu-profile-back-link">
			<?php Icons::render( 'core/arrow-left' ); ?> <?php esc_html_e( 'Volver al directorio Traspasando Fronteras', 'radioedu-conecta' ); ?>
		</a>
	</div>

	<div class="radioedu-profile-card">

		<!-- 1. IMAGEN DE CABECERA ARRIBA -->
		<?php // $cover_url ya está codificada para contexto CSS en DirectorioShortcode::css_safe_url(). ?>
		<div class="radioedu-profile-cover"<?php echo '' !== $cover_url ? ' style="' . esc_attr( "background-image: url('" . esc_url( $cover_url ) . "');" ) . '"' : ''; ?>>
			<div class="radioedu-profile-cover-overlay"></div>
			<span class="radioedu-profile-status-badge <?php echo $disponible ? 'is-available' : 'is-unavailable'; ?>">
				<?php if ( $disponible ) : ?>
					<span class="radioedu-status-dot" aria-hidden="true"></span> <?php esc_html_e( 'Disponible para colaborar', 'radioedu-conecta' ); ?>
				<?php else : ?>
					<span class="radioedu-status-dot" aria-hidden="true"></span> <?php esc_html_e( 'No disponible actualmente', 'radioedu-conecta' ); ?>
				<?php endif; ?>
			</span>
		</div>

		<!-- 2. DEBAJO DE LA CABECERA, A LA IZQUIERDA: NOMBRE Y DATOS DE LA TARJETA -->
		<div class="radioedu-profile-header-info">

			<div class="radioedu-profile-identity">
				<div class="radioedu-profile-logo-wrap">
					<?php if ( '' !== $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="radioedu-profile-logo" />
					<?php else : ?>
						<div class="radioedu-profile-logo radioedu-profile-logo-placeholder"><?php Icons::render( 'radioedu-conecta/microphone' ); ?></div>
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
					<?php foreach ( $tags as $radioedu_conecta_tag ) : ?>
						<span class="radioedu-tag radioedu-tag-<?php echo esc_attr( $radioedu_conecta_tag['color'] ); ?>">
							<?php echo esc_html( $radioedu_conecta_tag['name'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Metadatos adicionales (Edades, Idiomas, Enlaces) alineados a la izquierda -->
			<div class="radioedu-profile-meta-grid">
				<?php if ( '' !== $edades ) : ?>
					<div class="radioedu-profile-meta-item">
						<span class="radioedu-profile-meta-icon"><?php Icons::render( 'core/people' ); ?></span>
						<div>
							<span class="radioedu-profile-meta-label"><?php esc_html_e( 'Cursos / Edades', 'radioedu-conecta' ); ?></span>
							<strong class="radioedu-profile-meta-value"><?php echo esc_html( $edades ); ?></strong>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $idiomas_texto ) : ?>
					<div class="radioedu-profile-meta-item">
						<span class="radioedu-profile-meta-icon"><?php Icons::render( 'core/language' ); ?></span>
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
						<?php Icons::render( 'radioedu-conecta/send' ); ?> <?php esc_html_e( 'Proponer match', 'radioedu-conecta' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="radioedu-btn-match radioedu-profile-btn-inline" disabled>
						<?php esc_html_e( 'No disponible', 'radioedu-conecta' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( '' !== $podcast_url ) : ?>
					<a href="<?php echo esc_url( $podcast_url ); ?>" target="_blank" rel="noopener noreferrer" class="radioedu-btn-primary">
						<?php Icons::render( 'radioedu-conecta/headphones' ); ?> <?php esc_html_e( 'Escuchar Podcast / Emisora', 'radioedu-conecta' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( '' !== $web_centro ) : ?>
					<a href="<?php echo esc_url( $web_centro ); ?>" target="_blank" rel="noopener noreferrer" class="radioedu-btn-secondary radioedu-profile-btn-inline">
						<?php Icons::render( 'radioedu-conecta/globe' ); ?> <?php esc_html_e( 'Web del centro', 'radioedu-conecta' ); ?>
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
	<?php require __DIR__ . '/match-drawer.php'; ?>

</article>
