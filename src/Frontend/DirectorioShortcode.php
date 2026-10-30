<?php
/**
 * Shortcode, vista de perfil individual y renderizado del directorio Traspasando Fronteras.
 *
 * @package RadioEdu\Conecta\Frontend
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Frontend;

use RadioEdu\Conecta\Admin\DemoDataImporter;
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
	 * Controla que window.radioeduConectaData no se inyecte más de una vez por petición.
	 *
	 * @var bool
	 */
	private static bool $inline_data_added = false;

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
		$css_ver    = file_exists( RADIOEDU_CONECTA_DIR . 'assets/css/directorio.css' )
			? (string) filemtime( RADIOEDU_CONECTA_DIR . 'assets/css/directorio.css' )
			: RADIOEDU_CONECTA_VERSION;
		$asset_file = RADIOEDU_CONECTA_DIR . 'build/directorio.asset.php';

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

		// The source uses ES imports, so only the compiled bundle can run. Without a build the
		// script is skipped and the page degrades to static markup instead of a fatal error.
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;

			// Leaflet no es dependencia: directorio.js comprueba su presencia al activar el mapa,
			// así la ficha individual no descarga la librería del mapa.
			wp_register_script(
				'radioedu-directorio-js',
				RADIOEDU_CONECTA_URL . 'build/directorio.js',
				$asset['dependencies'],
				$asset['version'],
				[
					'in_footer' => true,
					'strategy'  => 'defer',
				]
			);
			wp_set_script_translations( 'radioedu-directorio-js', 'radioedu-conecta', RADIOEDU_CONECTA_DIR . 'languages' );
		}

		// Encolar estilos y script del drawer en la página de perfil individual de una emisora.
		if ( is_singular( CentroPostType::POST_TYPE ) ) {
			wp_enqueue_style( 'radioedu-directorio-css' );
			wp_enqueue_script( 'radioedu-directorio-js' );
		}
		// Register directory styles before wp_head prints styles.
		$post = get_post();
		if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, self::SHORTCODE_TAG ) ) {
			wp_enqueue_style( 'radioedu-leaflet-css' );
			wp_enqueue_style( 'radioedu-directorio-css' );
		}
	}

	/**
	 * Obtiene los centros gestionados por el usuario actual y construye el payload para JS.
	 *
	 * @return array{user_center_id: int, user_center_name: string, user_centers_list: array<int, array{id: int, nombre: string}>}
	 */
	private static function build_user_context_and_js_data(): array {
		$current_user_id   = get_current_user_id();
		$user_center_id    = 0;
		$user_center_name  = '';
		$user_centers_list = [];

		if ( $current_user_id > 0 ) {
			$managed_ids = ColaboracionesController::get_user_managed_center_ids( $current_user_id );

			foreach ( $managed_ids as $p_id ) {
				$c_title = get_the_title( $p_id );
				if ( 0 === $user_center_id ) {
					$user_center_id   = $p_id;
					$user_center_name = $c_title;
				}
				$user_centers_list[] = [
					'id'     => $p_id,
					'nombre' => $c_title,
				];
			}
		}

		if ( ! self::$inline_data_added ) {
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

			];

			wp_add_inline_script(
				'radioedu-directorio-js',
				'window.radioeduConectaData = ' . wp_json_encode( $js_data ) . ';',
				'before'
			);
			self::$inline_data_added = true;
		}

		return [
			'user_center_id'    => $user_center_id,
			'user_center_name'  => $user_center_name,
			'user_centers_list' => $user_centers_list,
		];
	}

	/**
	 * Prepara una URL para insertarla dentro de `url('...')` en un atributo style.
	 *
	 * La función esc_url() conserva comillas simples y paréntesis, y el navegador decodifica las
	 * entidades del atributo antes de interpretar el CSS; por eso se codifican aquí para
	 * impedir que una URL manipulada cierre `url()` e inyecte reglas CSS.
	 *
	 * @param string $url URL de la imagen.
	 * @return string URL http(s) segura para contexto CSS o cadena vacía.
	 */
	private static function css_safe_url( string $url ): string {
		$url = esc_url_raw( $url, [ 'http', 'https' ] );

		return str_replace(
			[ '"', "'", '(', ')', '\\', ' ' ],
			[ '%22', '%27', '%28', '%29', '%5C', '%20' ],
			$url
		);
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
		if ( $post_id <= 0 || post_password_required( $post_id ) ) {
			return $content;
		}

		if ( ! wp_style_is( 'radioedu-directorio-css', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'radioedu-directorio-css' );
		wp_enqueue_script( 'radioedu-directorio-js' );

		$user_ctx          = self::build_user_context_and_js_data();
		$user_centers_list = $user_ctx['user_centers_list'];

		$title     = get_the_title( $post_id );
		$logo_id   = (int) get_post_meta( $post_id, '_radioedu_logo_id', true );
		$logo_url  = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : (string) get_post_meta( $post_id, '_radioedu_logo_url', true );
		$cover_url = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		if ( '' === $cover_url ) {
			$cover_url = (string) get_post_meta( $post_id, '_radioedu_cover_url', true );
		}

		// Fallback para centros de ejemplo si no tienen imagen personalizada asignada.
		$post_name = (string) get_post_field( 'post_name', $post_id );
		if ( '' === $cover_url ) {
			$cover_url = DemoDataImporter::get_demo_image_url( $post_name, 'header' );
		}
		if ( '' === $logo_url ) {
			$logo_url = DemoDataImporter::get_demo_image_url( $post_name, 'logo' );
		}
		$cover_url = self::css_safe_url( $cover_url );

		$bandera     = (string) get_post_meta( $post_id, '_radioedu_bandera', true );
		$flag_emoji  = self::get_flag_emoji( '' !== $bandera ? $bandera : 'es-ex' );
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
		$tags           = [];
		$tag_taxonomies = [
			TaxonomiesRegistrar::TAX_FORMATO  => 'mint',
			TaxonomiesRegistrar::TAX_TEMATICA => 'coral',
			TaxonomiesRegistrar::TAX_ETAPA    => 'blue',
		];
		foreach ( $tag_taxonomies as $taxonomy => $color ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$tags[] = [
					'name'  => $term->name,
					'color' => $color,
				];
			}
		}

		// URL de retorno al directorio Traspasando Fronteras.
		$directorio_page = get_page_by_path( 'traspasando-fronteras' );
		$directorio_url  = $directorio_page ? (string) get_permalink( $directorio_page->ID ) : home_url( '/' );

		$raw_description_html = $content;

		// Datos del panel lateral de propuesta (receptor preseleccionado: el centro del perfil).
		$drawer_receptor_id    = $post_id;
		$drawer_receptor_label = $title . ( '' !== $ubicacion ? ' (' . $ubicacion . ')' : '' );

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
	public static function render_shortcode( array|string $atts = [] ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress supplies shortcode attributes.
		if ( ! wp_style_is( 'radioedu-directorio-css', 'registered' ) ) {
			self::register_assets();
		}

		// Encolar los assets condicionalmente sólo en la página donde se use el shortcode.
		wp_enqueue_style( 'radioedu-leaflet-css' );
		wp_enqueue_style( 'radioedu-directorio-css' );
		wp_enqueue_script( 'radioedu-leaflet-js' );
		wp_enqueue_script( 'radioedu-directorio-js' );

		$user_ctx          = self::build_user_context_and_js_data();
		$user_center_id    = $user_ctx['user_center_id'];
		$user_center_name  = $user_ctx['user_center_name'];
		$user_centers_list = $user_ctx['user_centers_list'];

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

		// El receptor del panel lateral se rellena desde JS al pulsar "Proponer match" en una tarjeta.
		$drawer_receptor_id    = 0;
		$drawer_receptor_label = '';

		ob_start();
		include RADIOEDU_CONECTA_DIR . 'templates/directorio.php';
		return (string) ob_get_clean();
	}
}
