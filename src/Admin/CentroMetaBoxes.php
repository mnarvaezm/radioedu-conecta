<?php
/**
 * Meta boxes para la ficha del centro escolar y su emisora de radio.
 *
 * @package RadioEdu\Conecta\Admin
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona los campos personalizados en la pantalla de edición de radioedu_centro.
 */
class CentroMetaBoxes {

	/**
	 * Nonce action name.
	 */
	public const NONCE_ACTION = 'radioedu_centro_meta_nonce_action';

	/**
	 * Nonce field name.
	 */
	public const NONCE_FIELD = 'radioedu_centro_meta_nonce';

	/**
	 * Registra los hooks de los meta boxes.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post_' . CentroPostType::POST_TYPE, [ self::class, 'save_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
	}

	/**
	 * Encola scripts necesarios para el selector de medios en la pantalla de edición del centro.
	 *
	 * @param string $hook_suffix Pantalla actual del panel de administración.
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		global $post_type;

		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) || CentroPostType::POST_TYPE !== $post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'radioedu-conecta-admin-css',
			RADIOEDU_CONECTA_URL . 'assets/css/admin.css',
			[],
			RADIOEDU_CONECTA_VERSION
		);
		wp_enqueue_script(
			'radioedu-conecta-admin-js',
			RADIOEDU_CONECTA_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			RADIOEDU_CONECTA_VERSION,
			true
		);
		wp_localize_script(
			'radioedu-conecta-admin-js',
			'radioeduAdminI18n',
			[
				'selectLogo' => esc_html__( 'Seleccionar logo de la radio', 'radioedu-conecta' ),
				'useLogo'    => esc_html__( 'Usar este logo', 'radioedu-conecta' ),
			]
		);
	}

	/**
	 * Registra el metabox de datos complementarios del centro.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'radioedu_centro_detalles',
			__( 'Datos de la Emisora y Coordinación (Traspasando Fronteras)', 'radioedu-conecta' ),
			[ self::class, 'render_detalles_box' ],
			CentroPostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renderiza el formulario del metabox con late-escaping estricto.
	 *
	 * @param WP_Post $post Objeto del post actual.
	 */
	public static function render_detalles_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$logo_id      = (int) get_post_meta( $post->ID, '_radioedu_logo_id', true );
		$logo_url     = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
		$podcast_url  = (string) get_post_meta( $post->ID, '_radioedu_podcast_url', true );
		$web_centro   = (string) get_post_meta( $post->ID, '_radioedu_web_centro', true );
		$bandera      = (string) get_post_meta( $post->ID, '_radioedu_bandera', true );
		$lat          = (string) get_post_meta( $post->ID, '_radioedu_lat', true );
		$lng          = (string) get_post_meta( $post->ID, '_radioedu_lng', true );
		$edades       = (string) get_post_meta( $post->ID, '_radioedu_edades', true );
		$coordinador  = (string) get_post_meta( $post->ID, '_radioedu_coordinador_nombre', true );
		$email_coord  = (string) get_post_meta( $post->ID, '_radioedu_coordinador_email', true );
		$disponible   = get_post_meta( $post->ID, '_radioedu_disponible_colaborar', true );
		if ( '' === $disponible ) {
			$disponible = '1'; // Por defecto disponible para colaborar.
		}

		$banderas_disponibles = [
			'es-ex' => __( 'Extremadura (España)', 'radioedu-conecta' ),
			'es-an' => __( 'Andalucía (España)', 'radioedu-conecta' ),
			'es-ar' => __( 'Aragón (España)', 'radioedu-conecta' ),
			'es-as' => __( 'Asturias (España)', 'radioedu-conecta' ),
			'es-cb' => __( 'Cantabria (España)', 'radioedu-conecta' ),
			'es-cl' => __( 'Castilla y León (España)', 'radioedu-conecta' ),
			'es-cm' => __( 'Castilla-La Mancha (España)', 'radioedu-conecta' ),
			'es-ct' => __( 'Cataluña (España)', 'radioedu-conecta' ),
			'es-vc' => __( 'Comunidad Valenciana (España)', 'radioedu-conecta' ),
			'es-ga' => __( 'Galicia (España)', 'radioedu-conecta' ),
			'es-ib' => __( 'Islas Baleares (España)', 'radioedu-conecta' ),
			'es-cn' => __( 'Islas Canarias (España)', 'radioedu-conecta' ),
			'es-ri' => __( 'La Rioja (España)', 'radioedu-conecta' ),
			'es-md' => __( 'Madrid (España)', 'radioedu-conecta' ),
			'es-mc' => __( 'Murcia (España)', 'radioedu-conecta' ),
			'es-nc' => __( 'Navarra (España)', 'radioedu-conecta' ),
			'es-pv' => __( 'País Vasco (España)', 'radioedu-conecta' ),
			'es'    => __( 'España (General)', 'radioedu-conecta' ),
			'pt'    => __( 'Portugal', 'radioedu-conecta' ),
			'fr'    => __( 'Francia', 'radioedu-conecta' ),
			'it'    => __( 'Italia', 'radioedu-conecta' ),
			'ie'    => __( 'Irlanda', 'radioedu-conecta' ),
			'uk'    => __( 'Reino Unido', 'radioedu-conecta' ),
			'de'    => __( 'Alemania', 'radioedu-conecta' ),
			'eu'    => __( 'Unión Europea / Internacional', 'radioedu-conecta' ),
			'mx'    => __( 'México', 'radioedu-conecta' ),
			'ar'    => __( 'Argentina', 'radioedu-conecta' ),
			'co'    => __( 'Colombia', 'radioedu-conecta' ),
			'cl'    => __( 'Chile', 'radioedu-conecta' ),
			'world' => __( 'Otro / Internacional', 'radioedu-conecta' ),
		];
		?>
		<div class="radioedu-metabox-wrapper">
			<table class="form-table" role="presentation">
				<tbody>
					<!-- 1. Logo de la radio escolar -->
					<tr>
						<th scope="row">
							<label for="radioedu_logo_button"><?php esc_html_e( 'Logo de la Radio Escolar', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<input type="hidden" id="radioedu_logo_id" name="radioedu_logo_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
							<div id="radioedu-logo-preview" class="radioedu-logo-preview-box">
								<?php if ( $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo de la radio', 'radioedu-conecta' ); ?>" style="max-width: 120px; height: auto; border-radius: 50%; border: 2px solid #00838f;" />
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'No hay logo asignado. Se mostrará un icono por defecto.', 'radioedu-conecta' ); ?></span>
								<?php endif; ?>
							</div>
							<p style="margin-top: 8px;">
								<button type="button" class="button" id="radioedu_logo_button"><?php esc_html_e( 'Subir / Elegir Logo', 'radioedu-conecta' ); ?></button>
								<button type="button" class="button button-link-delete" id="radioedu_logo_remove" style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Eliminar logo', 'radioedu-conecta' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'Imagen circular distintiva de la emisora escolar (recomendado 300x300 px formato PNG o JPG).', 'radioedu-conecta' ); ?></p>
						</td>
					</tr>

					<!-- 2. Bandera / Región representativa -->
					<tr>
						<th scope="row">
							<label for="radioedu_bandera"><?php esc_html_e( 'Bandera / Región', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<select name="radioedu_bandera" id="radioedu_bandera" class="regular-text">
								<option value=""><?php esc_html_e( '-- Selecciona región o país --', 'radioedu-conecta' ); ?></option>
								<?php foreach ( $banderas_disponibles as $codigo => $nombre ) : ?>
									<option value="<?php echo esc_attr( $codigo ); ?>" <?php selected( $bandera, $codigo ); ?>>
										<?php echo esc_html( $nombre ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Determina la bandera mostrada en la tarjeta de resultados y filtros geográficos.', 'radioedu-conecta' ); ?></p>
						</td>
					</tr>

					<!-- 3. Coordenadas geográficas para el mapa -->
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Coordenadas para el Mapa', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<label for="radioedu_lat" style="margin-right: 5px;"><?php esc_html_e( 'Latitud:', 'radioedu-conecta' ); ?></label>
							<input type="text" id="radioedu_lat" name="radioedu_lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="39.4699" style="width: 140px;" />

							<label for="radioedu_lng" style="margin-left: 15px; margin-right: 5px;"><?php esc_html_e( 'Longitud:', 'radioedu-conecta' ); ?></label>
							<input type="text" id="radioedu_lng" name="radioedu_lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="-6.3708" style="width: 140px;" />

							<p class="description"><?php esc_html_e( 'Coordenadas decimales para ubicar el centro en el mapa interactivo (ej. Cáceres: 39.4699, -6.3708).', 'radioedu-conecta' ); ?></p>
						</td>
					</tr>

					<!-- 4. Enlace al podcast / Emisión -->
					<tr>
						<th scope="row">
							<label for="radioedu_podcast_url"><?php esc_html_e( 'URL de Podcast / Streaming', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<input type="url" id="radioedu_podcast_url" name="radioedu_podcast_url" value="<?php echo esc_url( $podcast_url ); ?>" class="large-text" placeholder="https://radioedu.educarex.es/miprograma o https://ivoox.com/..." />
							<p class="description"><?php esc_html_e( 'Enlace donde se pueden escuchar las grabaciones o emisiones del centro escolar.', 'radioedu-conecta' ); ?></p>
						</td>
					</tr>

					<!-- 5. Web del centro educativo -->
					<tr>
						<th scope="row">
							<label for="radioedu_web_centro"><?php esc_html_e( 'Web Oficial del Centro', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<input type="url" id="radioedu_web_centro" name="radioedu_web_centro" value="<?php echo esc_url( $web_centro ); ?>" class="large-text" placeholder="https://cp...educarex.es" />
						</td>
					</tr>

					<!-- 6. Rango de edades o cursos del alumnado -->
					<tr>
						<th scope="row">
							<label for="radioedu_edades"><?php esc_html_e( 'Cursos / Edades del Alumnado', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<input type="text" id="radioedu_edades" name="radioedu_edades" value="<?php echo esc_attr( $edades ); ?>" class="regular-text" placeholder="Ej: 10-14 años (5º Primaria a 2º ESO)" />
							<p class="description"><?php esc_html_e( 'Informa a otros centros sobre el grupo de edad de los alumnos que participan en la radio.', 'radioedu-conecta' ); ?></p>
						</td>
					</tr>

					<!-- 7. Coordinador/a responsable (contacto interno) -->
					<tr>
						<th scope="row">
							<label for="radioedu_coordinador_nombre"><?php esc_html_e( 'Coordinador/a Responsable', 'radioedu-conecta' ); ?></label>
						</th>
						<td>
							<input type="text" id="radioedu_coordinador_nombre" name="radioedu_coordinador_nombre" value="<?php echo esc_attr( $coordinador ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Nombre del docente coordinador', 'radioedu-conecta' ); ?>" />

							<label for="radioedu_coordinador_email" style="margin-left: 15px; margin-right: 5px;"><?php esc_html_e( 'Email:', 'radioedu-conecta' ); ?></label>
							<input type="email" id="radioedu_coordinador_email" name="radioedu_coordinador_email" value="<?php echo esc_attr( $email_coord ); ?>" class="regular-text" placeholder="coordinador@educarex.es" />

							<p class="description">
								<strong><?php esc_html_e( 'Aviso de Privacidad (RGPD):', 'radioedu-conecta' ); ?></strong>
								<?php esc_html_e( 'El correo NO se publicará en el directorio público. Se emplea exclusivamente para avisos internos del sistema cuando otro centro envíe una propuesta de colaboración.', 'radioedu-conecta' ); ?>
							</p>
						</td>
					</tr>

					<!-- 8. Disponibilidad para colaboraciones -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Estado de Colaboración', 'radioedu-conecta' ); ?></th>
						<td>
							<fieldset>
								<label for="radioedu_disponible_colaborar">
									<input type="checkbox" id="radioedu_disponible_colaborar" name="radioedu_disponible_colaborar" value="1" <?php checked( $disponible, '1' ); ?> />
									<?php esc_html_e( 'Disponible para recibir propuestas de match y proyectos conjuntos', 'radioedu-conecta' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Guarda de manera segura los datos del metabox.
	 *
	 * @param int     $post_id ID del post guardado.
	 * @param WP_Post $post    Objeto del post.
	 */
	public static function save_meta_boxes( int $post_id, WP_Post $post ): void {
		// 1. Comprobaciones de seguridad de WordPress.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// 2. Sanitización y guardado de cada campo.
		// Logo ID.
		if ( isset( $_POST['radioedu_logo_id'] ) ) {
			$logo_id = absint( $_POST['radioedu_logo_id'] );
			update_post_meta( $post_id, '_radioedu_logo_id', $logo_id );
		}

		// Bandera / Región.
		if ( isset( $_POST['radioedu_bandera'] ) ) {
			$bandera = sanitize_key( wp_unslash( $_POST['radioedu_bandera'] ) );
			update_post_meta( $post_id, '_radioedu_bandera', $bandera );
		}

		// Coordenadas.
		if ( isset( $_POST['radioedu_lat'] ) ) {
			$lat_raw = sanitize_text_field( wp_unslash( $_POST['radioedu_lat'] ) );
			$lat     = '' !== $lat_raw ? (string) (float) $lat_raw : '';
			update_post_meta( $post_id, '_radioedu_lat', $lat );
		}

		if ( isset( $_POST['radioedu_lng'] ) ) {
			$lng_raw = sanitize_text_field( wp_unslash( $_POST['radioedu_lng'] ) );
			$lng     = '' !== $lng_raw ? (string) (float) $lng_raw : '';
			update_post_meta( $post_id, '_radioedu_lng', $lng );
		}

		// Podcast URL.
		if ( isset( $_POST['radioedu_podcast_url'] ) ) {
			$podcast_url = esc_url_raw( wp_unslash( $_POST['radioedu_podcast_url'] ) );
			update_post_meta( $post_id, '_radioedu_podcast_url', $podcast_url );
		}

		// Web del Centro.
		if ( isset( $_POST['radioedu_web_centro'] ) ) {
			$web_centro = esc_url_raw( wp_unslash( $_POST['radioedu_web_centro'] ) );
			update_post_meta( $post_id, '_radioedu_web_centro', $web_centro );
		}

		// Rango de edades.
		if ( isset( $_POST['radioedu_edades'] ) ) {
			$edades = sanitize_text_field( wp_unslash( $_POST['radioedu_edades'] ) );
			update_post_meta( $post_id, '_radioedu_edades', $edades );
		}

		// Nombre de coordinador.
		if ( isset( $_POST['radioedu_coordinador_nombre'] ) ) {
			$coordinador = sanitize_text_field( wp_unslash( $_POST['radioedu_coordinador_nombre'] ) );
			update_post_meta( $post_id, '_radioedu_coordinador_nombre', $coordinador );
		}

		// Email de coordinador.
		if ( isset( $_POST['radioedu_coordinador_email'] ) ) {
			$email_coord = sanitize_email( wp_unslash( $_POST['radioedu_coordinador_email'] ) );
			update_post_meta( $post_id, '_radioedu_coordinador_email', $email_coord );
		}

		// Disponibilidad para colaborar (checkbox: 1 o 0).
		$disponible = isset( $_POST['radioedu_disponible_colaborar'] ) ? '1' : '0';
		update_post_meta( $post_id, '_radioedu_disponible_colaborar', $disponible );
	}
}
