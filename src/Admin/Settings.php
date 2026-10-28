<?php
/**
 * Página de ajustes de RadioEdu Conecta en el panel de administración.
 *
 * @package RadioEdu\Conecta\Admin
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\PostTypes\CentroPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona los ajustes globales del plugin mediante la Settings API.
 */
class Settings {

	public const OPTION_KEY = 'radioedu_conecta_settings';
	public const SETTINGS_GROUP = 'radioedu_conecta_settings_group';

	/**
	 * Registra los hooks del menú y settings.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_settings_page' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	/**
	 * Registra el submenú de ajustes dentro de 'RadioEdu Centros'.
	 */
	public static function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . CentroPostType::POST_TYPE,
			__( 'Ajustes de RadioEdu Conecta', 'radioedu-conecta' ),
			__( 'Ajustes', 'radioedu-conecta' ),
			'manage_radioedu',
			'radioedu-conecta-settings',
			[ self::class, 'render_settings_page' ]
		);
	}

	/**
	 * Configura las secciones y campos con la Settings API de WordPress.
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize_settings' ],
				'default'           => self::get_default_settings(),
			]
		);

		// Sección: Configuración del Mapa.
		add_settings_section(
			'radioedu_map_section',
			__( 'Configuración del Mapa Interactivo', 'radioedu-conecta' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Define el centro geográfico y nivel de zoom inicial al abrir la vista de mapa en el directorio.', 'radioedu-conecta' ) . '</p>';
			},
			'radioedu-conecta-settings'
		);

		add_settings_field(
			'map_center',
			__( 'Centro del mapa (Latitud / Longitud)', 'radioedu-conecta' ),
			[ self::class, 'render_map_center_field' ],
			'radioedu-conecta-settings',
			'radioedu_map_section'
		);

		// Sección: Notificaciones y Correo.
		add_settings_section(
			'radioedu_email_section',
			__( 'Notificaciones por Correo Electrónico', 'radioedu-conecta' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Gestiona las notificaciones automáticas enviadas a los docentes coordinadores de las emisoras.', 'radioedu-conecta' ) . '</p>';
			},
			'radioedu-conecta-settings'
		);

		add_settings_field(
			'email_notifications',
			__( 'Avisos automáticos', 'radioedu-conecta' ),
			[ self::class, 'render_email_notifications_field' ],
			'radioedu-conecta-settings',
			'radioedu_email_section'
		);
	}

	/**
	 * Ajustes por defecto.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return [
			'default_lat'       => '39.4699', // Extremadura (Cáceres).
			'default_lng'       => '-6.3708',
			'default_zoom'      => '8',
			'notify_on_proposal'=> '1',
			'notify_on_status'  => '1',
		];
	}

	/**
	 * Obtiene un ajuste específico o todos.
	 *
	 * @param string|null $key Clave del ajuste.
	 * @param mixed       $default Valor por defecto si no existe.
	 * @return mixed
	 */
	public static function get( ?string $key = null, mixed $default = null ): mixed {
		$options = get_option( self::OPTION_KEY, self::get_default_settings() );
		if ( ! is_array( $options ) ) {
			$options = self::get_default_settings();
		}

		if ( null === $key ) {
			return $options;
		}

		return $options[ $key ] ?? $default;
	}

	/**
	 * Sanitiza las opciones antes de guardarlas en wp_options.
	 *
	 * @param mixed $input Datos entrantes del formulario.
	 * @return array<string, mixed> Datos sanitizados.
	 */
	public static function sanitize_settings( mixed $input ): array {
		$sanitized = self::get_default_settings();

		if ( ! is_array( $input ) ) {
			return $sanitized;
		}

		if ( isset( $input['default_lat'] ) ) {
			$lat_raw = sanitize_text_field( wp_unslash( $input['default_lat'] ) );
			$sanitized['default_lat'] = '' !== $lat_raw ? (string) (float) $lat_raw : '39.4699';
		}

		if ( isset( $input['default_lng'] ) ) {
			$lng_raw = sanitize_text_field( wp_unslash( $input['default_lng'] ) );
			$sanitized['default_lng'] = '' !== $lng_raw ? (string) (float) $lng_raw : '-6.3708';
		}

		if ( isset( $input['default_zoom'] ) ) {
			$zoom = absint( $input['default_zoom'] );
			$sanitized['default_zoom'] = (string) ( $zoom >= 1 && $zoom <= 18 ? $zoom : 8 );
		}

		$sanitized['notify_on_proposal'] = ! empty( $input['notify_on_proposal'] ) ? '1' : '0';
		$sanitized['notify_on_status']   = ! empty( $input['notify_on_status'] ) ? '1' : '0';

		return $sanitized;
	}

	/**
	 * Renderiza los campos de coordenadas del mapa.
	 */
	public static function render_map_center_field(): void {
		$lat  = (string) self::get( 'default_lat', '39.4699' );
		$lng  = (string) self::get( 'default_lng', '-6.3708' );
		$zoom = (string) self::get( 'default_zoom', '8' );
		?>
		<label for="radioedu_default_lat"><?php esc_html_e( 'Lat:', 'radioedu-conecta' ); ?></label>
		<input type="text" id="radioedu_default_lat" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_lat]" value="<?php echo esc_attr( $lat ); ?>" style="width: 120px;" />

		<label for="radioedu_default_lng" style="margin-left: 12px;"><?php esc_html_e( 'Lng:', 'radioedu-conecta' ); ?></label>
		<input type="text" id="radioedu_default_lng" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_lng]" value="<?php echo esc_attr( $lng ); ?>" style="width: 120px;" />

		<label for="radioedu_default_zoom" style="margin-left: 12px;"><?php esc_html_e( 'Zoom inicial (1-18):', 'radioedu-conecta' ); ?></label>
		<input type="number" id="radioedu_default_zoom" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_zoom]" value="<?php echo esc_attr( $zoom ); ?>" min="1" max="18" style="width: 70px;" />

		<p class="description"><?php esc_html_e( 'Por defecto centrado en Extremadura (39.4699, -6.3708, zoom 8).', 'radioedu-conecta' ); ?></p>
		<?php
	}

	/**
	 * Renderiza los checkboxes de notificaciones por email.
	 */
	public static function render_email_notifications_field(): void {
		$notify_proposal = self::get( 'notify_on_proposal', '1' );
		$notify_status   = self::get( 'notify_on_status', '1' );
		?>
		<fieldset>
			<label for="radioedu_notify_on_proposal">
				<input type="checkbox" id="radioedu_notify_on_proposal" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notify_on_proposal]" value="1" <?php checked( $notify_proposal, '1' ); ?> />
				<?php esc_html_e( 'Notificar por correo al coordinador del centro receptor cuando reciba una nueva propuesta de colaboración.', 'radioedu-conecta' ); ?>
			</label>
			<br />
			<label for="radioedu_notify_on_status" style="margin-top: 6px; display: inline-block;">
				<input type="checkbox" id="radioedu_notify_on_status" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notify_on_status]" value="1" <?php checked( $notify_status, '1' ); ?> />
				<?php esc_html_e( 'Notificar por correo al coordinador emisor cuando su propuesta sea aceptada o rechazada.', 'radioedu-conecta' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Renderiza la vista de la página de ajustes.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_radioedu' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'radioedu-conecta' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configuración global del directorio de radios escolares Traspasando Fronteras.', 'radioedu-conecta' ); ?>
			</p>

			<?php if ( isset( $_GET['demo_imported'] ) ) : ?>
				<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
					<p>
						<?php
						printf(
							/* translators: %d: number of imported centers */
							esc_html__( '¡Se han cargado o sincronizado con éxito %d emisoras de ejemplo con sus imágenes, taxonomías y coordenadas en el mapa!', 'radioedu-conecta' ),
							absint( $_GET['demo_imported'] )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( 'radioedu-conecta-settings' );
				submit_button();
				?>
			</form>

			<hr style="margin: 35px 0 25px 0;" />

			<div class="card" style="max-width: 800px; padding: 20px;">
				<h2><?php esc_html_e( 'Emisoras de Demostración (Traspasando Fronteras)', 'radioedu-conecta' ); ?></h2>
				<p>
					<?php esc_html_e( 'El instalador incluye 6 emisoras completas de ejemplo con fotografías de cabecera, logotipos, edades, formatos, idiomas y coordenadas geográficas. Puedes volver a generarlas o sincronizarlas en cualquier momento con este botón:', 'radioedu-conecta' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="radioedu_import_demo_data" />
					<?php wp_nonce_field( DemoDataImporter::NONCE_ACTION, DemoDataImporter::NONCE_NAME ); ?>
					<?php submit_button( __( 'Sincronizar / Cargar las 6 emisoras de ejemplo', 'radioedu-conecta' ), 'secondary', 'submit_demo', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
