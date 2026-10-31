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
use WP_Post;

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
	 * Name of the directory block, an editor-friendly equivalent of the shortcode.
	 */
	public const BLOCK_NAME = 'radioedu-conecta/directorio';

	/**
	 * Name of the profile page block template (plugin namespace + hierarchy slug).
	 */
	public const PROFILE_BLOCK_TEMPLATE = 'radioedu-conecta//single-radioedu_centro';

	/**
	 * Controla que window.radioeduConectaData no se inyecte más de una vez por petición.
	 *
	 * @var bool
	 */
	private static bool $inline_data_added = false;

	/**
	 * Registra el shortcode, el bloque y el filtro de perfil individual en WordPress.
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE_TAG, [ self::class, 'render_shortcode' ] );
		// Assets are registered on init so the block can declare its stylesheet for the editor.
		add_action( 'init', [ self::class, 'register_assets' ] );
		add_action( 'init', [ self::class, 'register_block' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_filter( 'the_content', [ self::class, 'render_single_centro_profile' ], 20 );
		// The profile page shows only the theme header and footer around the profile, without the
		// theme's post title, author/date meta and featured image, which the profile already has.
		add_action( 'init', [ self::class, 'register_profile_block_template' ] );
		add_filter( 'template_include', [ self::class, 'use_profile_page_template' ], 20 );
		// The profile uses the featured image as its cover, so the theme must not print it again.
		add_filter( 'has_post_thumbnail', [ self::class, 'hide_theme_featured_image' ], 10, 2 );
		add_filter( 'post_thumbnail_html', [ self::class, 'hide_theme_featured_image_html' ], 10, 2 );
	}

	/**
	 * Registers the profile page template for block themes.
	 *
	 * It is the most specific template of the hierarchy for centros (single-radioedu_centro),
	 * so it replaces the theme's single template. A theme template with the same slug, or a
	 * version edited in the Site Editor, takes precedence.
	 *
	 * @since 1.1
	 */
	public static function register_profile_block_template(): void {
		if ( ! wp_is_block_theme() ) {
			return;
		}

		register_block_template(
			self::PROFILE_BLOCK_TEMPLATE,
			[
				'title'       => __( 'Ficha de centro de radio', 'radioedu-conecta' ),
				'description' => __( 'Perfil público de un centro: cabecera y pie del tema alrededor de la ficha, sin repetir su título, autor, fecha ni imagen de cabecera.', 'radioedu-conecta' ),
				'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
					. '<!-- wp:group {"tagName":"main","layout":{"type":"constrained","contentSize":"980px"}} -->'
					. '<main class="wp-block-group"><!-- wp:post-content /--></main>'
					. '<!-- /wp:group -->'
					. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
			]
		);
	}

	/**
	 * Uses the plugin's profile page template with classic themes.
	 *
	 * Only replaces the generic templates (single.php, singular.php, index.php): a theme template
	 * made for centros or a page builder template is kept. Themes can override the plugin file
	 * with radioedu-conecta/single-radioedu_centro.php.
	 *
	 * @since 1.1
	 *
	 * @param string $template Template file chosen by WordPress.
	 * @return string Template file to load.
	 */
	public static function use_profile_page_template( string $template ): string {
		if ( wp_is_block_theme() || is_embed() || ! is_singular( CentroPostType::POST_TYPE )
			|| ! in_array( basename( $template ), [ 'single.php', 'singular.php', 'index.php' ], true ) ) {
			return $template;
		}

		$theme_override = locate_template( 'radioedu-conecta/single-' . CentroPostType::POST_TYPE . '.php' );

		return '' !== $theme_override ? $theme_override : RADIOEDU_CONECTA_DIR . 'templates/theme-single-centro.php';
	}

	/**
	 * Tells the theme that the centro shown on its profile page has no featured image.
	 *
	 * The profile template reads the cover with get_the_post_thumbnail_url(), which these filters
	 * do not affect.
	 *
	 * @since 1.1
	 *
	 * @param bool  $has_thumbnail Whether the post has a featured image.
	 * @param mixed $post          Post ID, object or null for the current post.
	 * @return bool False for the centro of the current profile page.
	 */
	public static function hide_theme_featured_image( bool $has_thumbnail, mixed $post = null ): bool {
		return $has_thumbnail && ! self::is_profile_centro( $post );
	}

	/**
	 * Removes the theme's featured image markup on the centro profile page.
	 *
	 * Covers themes that print the image without checking has_post_thumbnail() first, and the
	 * Post Featured Image block of block themes.
	 *
	 * @since 1.1
	 *
	 * @param string $html    Featured image markup.
	 * @param mixed  $post_id Post ID.
	 * @return string Empty for the centro of the current profile page.
	 */
	public static function hide_theme_featured_image_html( string $html, mixed $post_id = null ): string {
		return self::is_profile_centro( $post_id ) ? '' : $html;
	}

	/**
	 * Checks whether a post is the centro of the profile page being rendered.
	 *
	 * The page <head> is excluded, so Open Graph and SEO plugins keep the image.
	 *
	 * @param mixed $post Post ID, object or null for the current post.
	 * @return bool Whether the post is the queried centro of a front-end profile page.
	 */
	private static function is_profile_centro( mixed $post ): bool {
		if ( is_admin() || doing_action( 'wp_head' ) || ! is_singular( CentroPostType::POST_TYPE ) ) {
			return false;
		}

		$post = get_post( $post instanceof WP_Post ? $post : ( is_numeric( $post ) ? (int) $post : null ) );

		return $post instanceof WP_Post && get_queried_object_id() === $post->ID;
	}

	/**
	 * Registra el bloque del directorio solo con PHP (sin JavaScript de editor propio).
	 *
	 * Con `autoRegister` (WordPress 7.0+) el editor lo registra por su cuenta y lo previsualiza
	 * con ServerSideRender, así que el bloque y el shortcode comparten el mismo renderizado.
	 * El directorio usa IDs fijos en su marcado, por eso solo se permite una instancia por entrada.
	 *
	 * @since 1.1
	 */
	public static function register_block(): void {
		register_block_type(
			self::BLOCK_NAME,
			[
				'title'           => __( 'Directorio Traspasando Fronteras', 'radioedu-conecta' ),
				'description'     => __( 'Directorio interactivo de radios escolares con filtros, mapa y propuestas de colaboración.', 'radioedu-conecta' ),
				'category'        => 'widgets',
				'icon'            => 'microphone',
				'keywords'        => [
					__( 'radio escolar', 'radioedu-conecta' ),
					__( 'directorio', 'radioedu-conecta' ),
					__( 'colaboración', 'radioedu-conecta' ),
					'podcast',
				],
				'attributes'      => [],
				'supports'        => [
					'autoRegister' => true,
					'html'         => false,
					'multiple'     => false,
					'align'        => [ 'wide', 'full' ],
				],
				'style_handles'   => [ 'radioedu-directorio-css' ],
				'render_callback' => [ self::class, 'render_block' ],
			]
		);
	}

	/**
	 * Renderiza el bloque del directorio reutilizando la salida del shortcode.
	 *
	 * @since 1.1
	 *
	 * @return string Salida HTML del bloque.
	 */
	public static function render_block(): string {
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), self::render_shortcode() );
	}

	/**
	 * Encola en el <head> los estilos de las páginas que muestran el directorio o una ficha.
	 *
	 * Los renderizadores también encolan sus assets, pero hacerlo aquí evita que la página se
	 * pinte sin estilos en los temas clásicos, que procesan el contenido después de wp_head.
	 *
	 * @since 1.1
	 */
	public static function enqueue_assets(): void {
		if ( is_singular( CentroPostType::POST_TYPE ) ) {
			wp_enqueue_style( 'radioedu-directorio-css' );
			wp_enqueue_script( 'radioedu-directorio-js' );
		}

		$post = get_post();
		if ( $post instanceof \WP_Post && ( has_shortcode( $post->post_content, self::SHORTCODE_TAG ) || has_block( self::BLOCK_NAME, $post ) ) ) {
			wp_enqueue_style( 'radioedu-leaflet-css' );
			wp_enqueue_style( 'radioedu-directorio-css' );
		}
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
