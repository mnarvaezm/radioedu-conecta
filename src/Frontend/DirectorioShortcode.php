<?php
/**
 * Shortcode y renderizado del directorio Traspasando Fronteras.
 *
 * @package RadioEdu\Conecta\Frontend
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Frontend;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona el registro del shortcode [radioedu_directorio] y la carga de sus assets.
 */
class DirectorioShortcode {

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE_TAG = 'radioedu_directorio';

	/**
	 * Registra el shortcode en WordPress.
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE_TAG, [ self::class, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
	}

	/**
	 * Registra los assets de front-end para su posterior encolado condicional.
	 */
	public static function register_assets(): void {
		wp_register_style(
			'radioedu-directorio-css',
			RADIOEDU_CONECTA_URL . 'assets/css/directorio.css',
			[],
			RADIOEDU_CONECTA_VERSION
		);

		// Librería Leaflet para mapa (embebida localmente sin CDNs de terceros para cumplimiento RGPD).
		wp_register_style(
			'radioedu-leaflet-css',
			RADIOEDU_CONECTA_URL . 'assets/css/leaflet.css',
			[],
			'1.9.4'
		);

		wp_register_script(
			'radioedu-leaflet-js',
			RADIOEDU_CONECTA_URL . 'assets/js/leaflet.js',
			[],
			'1.9.4',
			true
		);

		wp_register_script(
			'radioedu-directorio-js',
			RADIOEDU_CONECTA_URL . 'assets/js/directorio.js',
			[ 'radioedu-leaflet-js' ],
			RADIOEDU_CONECTA_VERSION,
			true
		);
	}

	/**
	 * Renderiza el directorio completo.
	 *
	 * @param array<string, mixed>|string $atts Atributos del shortcode.
	 * @return string Salida HTML del directorio.
	 */
	public static function render_shortcode( $atts = [] ): string {
		// Encolar los assets condicionalmente sólo en la página donde se use el shortcode.
		wp_enqueue_style( 'radioedu-leaflet-css' );
		wp_enqueue_style( 'radioedu-directorio-css' );
		wp_enqueue_script( 'radioedu-leaflet-js' );
		wp_enqueue_script( 'radioedu-directorio-js' );

		// Determinar si el usuario conectado gestiona algún centro.
		$current_user_id   = get_current_user_id();
		$user_center_id    = 0;
		$user_center_name  = '';
		$user_centers_list = [];

		if ( $current_user_id > 0 ) {
			$user_centers_query = new WP_Query(
				[
					'post_type'      => CentroPostType::POST_TYPE,
					'post_status'    => 'publish',
					'author'         => $current_user_id,
					'posts_per_page' => 20,
					'fields'         => 'ids',
				]
			);

			if ( ! empty( $user_centers_query->posts ) ) {
				foreach ( $user_centers_query->posts as $p_id ) {
					$title = get_the_title( $p_id );
					if ( 0 === $user_center_id ) {
						$user_center_id   = $p_id;
						$user_center_name = $title;
					}
					$user_centers_list[] = [
						'id'     => $p_id,
						'nombre' => $title,
					];
				}
			}
		}

		// Localizar datos y textos para JavaScript de manera segura.
		$js_data = [
			'restUrl'          => esc_url_raw( rest_url( 'radioedu-conecta/v1/' ) ),
			'restNonce'        => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'       => is_user_logged_in(),
			'loginUrl'         => esc_url( wp_login_url( get_permalink() ) ),
			'canPropose'       => current_user_can( 'propose_radioedu_colaboracion' ),
			'userCenterId'     => $user_center_id,
			'userCenterName'   => $user_center_name,
			'userCenters'      => $user_centers_list,
			'mapCenter'        => [
				'lat'  => (float) Settings::get( 'default_lat', '39.4699' ),
				'lng'  => (float) Settings::get( 'default_lng', '-6.3708' ),
				'zoom' => (int) Settings::get( 'default_zoom', 8 ),
			],
			'i18n'             => [
				'resultsCount'     => __( 'Resultados (%s centros)', 'radioedu-conecta' ),
				'noResults'        => __( 'No se encontraron centros con los filtros seleccionados.', 'radioedu-conecta' ),
				'loading'          => __( 'Cargando centros...', 'radioedu-conecta' ),
				'viewProfile'      => __( 'Ver perfil', 'radioedu-conecta' ),
				'proposeMatch'     => __( 'Proponer match', 'radioedu-conecta' ),
				'favorite'         => __( 'Añadir a favoritos', 'radioedu-conecta' ),
				'sending'          => __( 'Enviando propuesta...', 'radioedu-conecta' ),
				'sendSuccess'      => __( '¡Propuesta enviada con éxito!', 'radioedu-conecta' ),
				'loginRequired'    => __( 'Debes iniciar sesión con tu cuenta de coordinador de centro para enviar propuestas de colaboración.', 'radioedu-conecta' ),
				'selectYourCenter' => __( 'Por favor, selecciona tu centro escolar.', 'radioedu-conecta' ),
				'errorOccurred'    => __( 'Ha ocurrido un error al procesar la solicitud.', 'radioedu-conecta' ),
			],
		];

		wp_localize_script( 'radioedu-directorio-js', 'radioeduConectaData', $js_data );

		// Obtener términos de taxonomías para los selectores de filtro.
		$etapas        = get_terms( [ 'taxonomy' => TaxonomiesRegistrar::TAX_ETAPA, 'hide_empty' => false ] );
		$idiomas       = get_terms( [ 'taxonomy' => TaxonomiesRegistrar::TAX_IDIOMA, 'hide_empty' => false ] );
		$tematicas     = get_terms( [ 'taxonomy' => TaxonomiesRegistrar::TAX_TEMATICA, 'hide_empty' => false ] );
		$formatos      = get_terms( [ 'taxonomy' => TaxonomiesRegistrar::TAX_FORMATO, 'hide_empty' => false ] );
		$ubicaciones   = get_terms( [ 'taxonomy' => TaxonomiesRegistrar::TAX_LOCALIZACION, 'hide_empty' => false ] );

		ob_start();
		include RADIOEDU_CONECTA_DIR . 'templates/directorio.php';
		return (string) ob_get_clean();
	}
}
