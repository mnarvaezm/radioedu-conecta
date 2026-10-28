<?php
/**
 * Shortcode, vista de perfil individual y renderizado del directorio Traspasando Fronteras.
 *
 * @package RadioEdu\Conecta\Frontend
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Frontend;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona el registro del shortcode [radioedu_directorio], la ficha single de centro y sus assets.
 */
class DirectorioShortcode {

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE_TAG = 'radioedu_directorio';

	/**
	 * Registra el shortcode y el filtro de perfil individual en WordPress.
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE_TAG, [ self::class, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
		add_filter( 'the_content', [ self::class, 'render_single_centro_profile' ], 20 );
	}

	/**
	 * Registra los assets de front-end para su posterior encolado condicional.
	 */
	public static function register_assets(): void {
		$css_ver = file_exists( RADIOEDU_CONECTA_DIR . 'assets/css/directorio.css' )
			? (string) filemtime( RADIOEDU_CONECTA_DIR . 'assets/css/directorio.css' )
			: RADIOEDU_CONECTA_VERSION;
		$js_ver  = file_exists( RADIOEDU_CONECTA_DIR . 'assets/js/directorio.js' )
			? (string) filemtime( RADIOEDU_CONECTA_DIR . 'assets/js/directorio.js' )
			: RADIOEDU_CONECTA_VERSION;

		wp_register_style(
			'radioedu-directorio-css',
			RADIOEDU_CONECTA_URL . 'assets/css/directorio.css',
			[],
			$css_ver
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
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		wp_register_script(
			'radioedu-directorio-js',
			RADIOEDU_CONECTA_URL . 'assets/js/directorio.js',
			[ 'radioedu-leaflet-js' ],
			$js_ver,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		// Encolar estilos automáticamente en la página de perfil individual de una emisora.
		if ( is_singular( CentroPostType::POST_TYPE ) ) {
			wp_enqueue_style( 'radioedu-directorio-css' );
		}
	}

	/**
	 * Renderiza la ficha completa del perfil de la radio escolar en su vista individual.
	 *
	 * @param string $content Contenido original del post.
	 * @return string HTML enriquecido del perfil del centro.
	 */
	public static function render_single_centro_profile( string $content ): string {
		if ( ! is_singular( CentroPostType::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return $content;
		}

		$title     = get_the_title( $post_id );
		$logo_id   = (int) get_post_meta( $post_id, '_radioedu_logo_id', true );
		$logo_url  = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : (string) get_post_meta( $post_id, '_radioedu_logo_url', true );
		$cover_url = get_the_post_thumbnail_url( $post_id, 'large' ) ?: (string) get_post_meta( $post_id, '_radioedu_cover_url', true );

		// Fallback para centros de ejemplo si no tienen imagen personalizada asignada.
		$post_name = (string) get_post_field( 'post_name', $post_id );
		if ( empty( $cover_url ) ) {
			$demo_covers = [
				'sierra-de-gata' => 'header-sierra-gata.jpg',
				'montjuic'       => 'header-montjuic.jpg',
				'la-mar'         => 'header-la-mar.jpg',
				'san-jose'       => 'header-san-jose.jpg',
				'valle-de-tena'  => 'header-valle-tena.jpg',
				'san-patricio'   => 'header-san-patricio.jpg',
			];
			foreach ( $demo_covers as $slug_part => $img_file ) {
				if ( false !== strpos( $post_name, $slug_part ) && file_exists( RADIOEDU_CONECTA_DIR . 'assets/images/demo/' . $img_file ) ) {
					$cover_url = RADIOEDU_CONECTA_URL . 'assets/images/demo/' . $img_file;
					break;
				}
			}
		}

		if ( empty( $logo_url ) ) {
			$demo_logos = [
				'sierra-de-gata' => 'logo-sierra-gata.jpg',
				'montjuic'       => 'logo-montjuic.jpg',
				'la-mar'         => 'logo-la-mar.jpg',
				'san-jose'       => 'logo-san-jose.jpg',
				'valle-de-tena'  => 'logo-valle-tena.jpg',
				'san-patricio'   => 'logo-san-patricio.jpg',
			];
			foreach ( $demo_logos as $slug_part => $img_file ) {
				if ( false !== strpos( $post_name, $slug_part ) && file_exists( RADIOEDU_CONECTA_DIR . 'assets/images/demo/' . $img_file ) ) {
					$logo_url = RADIOEDU_CONECTA_URL . 'assets/images/demo/' . $img_file;
					break;
				}
			}
		}

		$bandera     = (string) get_post_meta( $post_id, '_radioedu_bandera', true ) ?: 'es-ex';
		$flag_emoji  = self::get_flag_emoji( $bandera );
		$podcast_url = (string) get_post_meta( $post_id, '_radioedu_podcast_url', true );
		$web_centro  = (string) get_post_meta( $post_id, '_radioedu_web_centro', true );
		$edades      = (string) get_post_meta( $post_id, '_radioedu_edades', true );
		$disponible  = '0' !== get_post_meta( $post_id, '_radioedu_disponible_colaborar', true );

		// Ubicación.
		$ubicacion      = '';
		$localizaciones = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_LOCALIZACION );
		if ( ! empty( $localizaciones ) && ! is_wp_error( $localizaciones ) ) {
			$loc_names = wp_list_pluck( $localizaciones, 'name' );
			$ubicacion = implode( ', ', array_reverse( $loc_names ) );
		}

		// Idiomas.
		$idiomas_texto = '';
		$idiomas_terms = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_IDIOMA );
		if ( ! empty( $idiomas_terms ) && ! is_wp_error( $idiomas_terms ) ) {
			$idiomas_texto = implode( ', ', wp_list_pluck( $idiomas_terms, 'name' ) );
		}

		// Etiquetas (Formatos, Temáticas, Etapas) idénticas a la tarjeta.
		$tags = [];

		$formatos = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_FORMATO );
		if ( ! empty( $formatos ) && ! is_wp_error( $formatos ) ) {
			foreach ( $formatos as $term ) {
				$tags[] = [
					'name'  => $term->name,
					'color' => 'mint',
				];
			}
		}

		$tematicas = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_TEMATICA );
		if ( ! empty( $tematicas ) && ! is_wp_error( $tematicas ) ) {
			foreach ( $tematicas as $term ) {
				$tags[] = [
					'name'  => $term->name,
					'color' => 'coral',
				];
			}
		}

		$etapas = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_ETAPA );
		if ( ! empty( $etapas ) && ! is_wp_error( $etapas ) ) {
			foreach ( $etapas as $term ) {
				$tags[] = [
					'name'  => $term->name,
					'color' => 'blue',
				];
			}
		}

		// URL de retorno al directorio Traspasando Fronteras.
		$directorio_page = get_page_by_path( 'traspasando-fronteras' );
		$directorio_url  = $directorio_page ? (string) get_permalink( $directorio_page->ID ) : home_url( '/' );

		$raw_description_html = $content;

		ob_start();
		include RADIOEDU_CONECTA_DIR . 'templates/single-centro.php';
		return (string) ob_get_clean();
	}

	/**
	 * Devuelve el icono representativo de la bandera/región.
	 *
	 * @param string $code Código de bandera.
	 * @return string Emoji o insignia.
	 */
	private static function get_flag_emoji( string $code ): string {
		$flags = [
			'es-ex' => '🟩⬜⬛',
			'es-ct' => '🟨🟥',
			'es-vc' => '🇪🇸',
			'es-ar' => '🇪🇸',
			'es-an' => '🟩⬜🟩',
			'es-md' => '🇪🇸',
			'es-ga' => '🇪🇸',
			'es-pv' => '🇪🇸',
			'es'    => '🇪🇸',
			'pt'    => '🇵🇹',
			'fr'    => '🇫🇷',
			'it'    => '🇮🇹',
			'ie'    => '🇮🇪',
			'uk'    => '🇬🇧',
			'de'    => '🇩🇪',
			'mx'    => '🇲🇽',
			'ar'    => '🇦🇷',
			'co'    => '🇨🇴',
			'cl'    => '🇨🇱',
			'eu'    => '🇪🇺',
			'world' => '🌍',
		];

		return $flags[ $code ] ?? '📻';
	}

	/**
	 * Renderiza el directorio completo.
	 *
	 * @param array<string, mixed>|string $atts Atributos del shortcode.
	 * @return string Salida HTML del directorio.
	 */
	public static function render_shortcode( $atts = [] ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! wp_style_is( 'radioedu-directorio-css', 'registered' ) ) {
			self::register_assets();
		}

		// Encolar los assets condicionalmente sólo en la página donde se use el shortcode.
		wp_enqueue_style( 'radioedu-leaflet-css' );
		wp_enqueue_style( 'radioedu-directorio-css' );
		wp_enqueue_script( 'radioedu-leaflet-js' );
		wp_enqueue_script( 'radioedu-directorio-js' );

		// Determinar los centros que el usuario conectado coordina o puede gestionar.
		$current_user_id   = get_current_user_id();
		$user_center_id    = 0;
		$user_center_name  = '';
		$user_centers_list = [];

		if ( $current_user_id > 0 ) {
			$managed_ids = ColaboracionesController::get_user_managed_center_ids( $current_user_id );

			foreach ( $managed_ids as $p_id ) {
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

		// Preparar datos y textos para JavaScript de manera segura.
		// El nonce solo se genera para usuarios autenticados para evitar fallos 403 con cachés de página.
		$js_data = [
			'restUrl'        => esc_url_raw( rest_url( 'radioedu-conecta/v1/' ) ),
			'restNonce'      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'isLoggedIn'     => is_user_logged_in(),
			'loginUrl'       => esc_url_raw( wp_login_url( (string) get_permalink() ) ),
			'canPropose'     => current_user_can( 'propose_radioedu_colaboracion' ),
			'userCenterId'   => $user_center_id,
			'userCenterName' => $user_center_name,
			'userCenters'    => $user_centers_list,
			'mapCenter'      => [
				'lat'  => (float) Settings::get( 'default_lat', '39.4699' ),
				'lng'  => (float) Settings::get( 'default_lng', '-6.3708' ),
				'zoom' => (int) Settings::get( 'default_zoom', 8 ),
			],
			'i18n'           => [
				'resultsCount'     => __( 'Resultados (%s centros)', 'radioedu-conecta' ),
				'noResults'        => __( 'No se encontraron centros con los filtros seleccionados.', 'radioedu-conecta' ),
				'loading'          => __( 'Cargando centros...', 'radioedu-conecta' ),
				'viewProfile'      => __( 'Ver perfil', 'radioedu-conecta' ),
				'proposeMatch'     => __( 'Proponer match', 'radioedu-conecta' ),
				'unavailable'      => __( 'No disponible', 'radioedu-conecta' ),
				'favorite'         => __( 'Añadir a favoritos', 'radioedu-conecta' ),
				'sending'          => __( 'Enviando propuesta...', 'radioedu-conecta' ),
				'sendSuccess'      => __( '¡Propuesta enviada con éxito!', 'radioedu-conecta' ),
				'loginRequired'    => __( 'Debes iniciar sesión con tu cuenta de coordinador de centro para enviar propuestas de colaboración.', 'radioedu-conecta' ),
				'noPermission'     => __( 'Tu cuenta actual no dispone de permisos de coordinador para proponer colaboraciones.', 'radioedu-conecta' ),
				'selectYourCenter' => __( 'Por favor, selecciona tu centro escolar.', 'radioedu-conecta' ),
				'errorOccurred'    => __( 'Ha ocurrido un error al procesar la solicitud.', 'radioedu-conecta' ),
			],
		];

		wp_add_inline_script(
			'radioedu-directorio-js',
			'window.radioeduConectaData = ' . wp_json_encode( $js_data ) . ';',
			'before'
		);

		// Obtener términos de taxonomías para los selectores de filtro.
		$etapas      = get_terms(
			[
				'taxonomy'   => TaxonomiesRegistrar::TAX_ETAPA,
				'hide_empty' => false,
			]
		);
		$idiomas     = get_terms(
			[
				'taxonomy'   => TaxonomiesRegistrar::TAX_IDIOMA,
				'hide_empty' => false,
			]
		);
		$tematicas   = get_terms(
			[
				'taxonomy'   => TaxonomiesRegistrar::TAX_TEMATICA,
				'hide_empty' => false,
			]
		);
		$formatos    = get_terms(
			[
				'taxonomy'   => TaxonomiesRegistrar::TAX_FORMATO,
				'hide_empty' => false,
			]
		);
		$ubicaciones = get_terms(
			[
				'taxonomy'   => TaxonomiesRegistrar::TAX_LOCALIZACION,
				'hide_empty' => false,
			]
		);

		ob_start();
		include RADIOEDU_CONECTA_DIR . 'templates/directorio.php';
		return (string) ob_get_clean();
	}
}
