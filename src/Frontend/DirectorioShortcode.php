<?php
/**
 * Shortcode, vista de perfil individual y renderizado del directorio Traspasando Fronteras.
 *
 * @package RadioEdu\Conecta\Frontend
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Frontend;

use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_HTML_Tag_Processor;
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
	 * Option with the ID of the page that shows the directory.
	 */
	public const DIRECTORY_PAGE_OPTION = 'radioedu_conecta_directory_page_id';

	/**
	 * Name of the profile page block template (plugin namespace + hierarchy slug).
	 */
	public const PROFILE_BLOCK_TEMPLATE = 'radioedu-conecta//single-radioedu_centro';

	/**
	 * Registra el shortcode, el bloque y el filtro de perfil individual en WordPress.
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE_TAG, [ self::class, 'render_shortcode' ] );
		DirectoryStore::register();
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
	 * Works out the <main> landmark the classic profile template must provide.
	 *
	 * The template replaces the theme's single template, which is where most classic themes open
	 * <main> (Twenty Twenty, for example); a few open it in header.php (Twenty Twenty-One). The
	 * template opens <main> only when the header left none open, and gives it the target of the
	 * theme's skip link when the header does not contain that target.
	 *
	 * @since 1.1
	 *
	 * @param string $header_html Markup printed by the theme's get_header().
	 * @return array{main: bool, id: string} Whether to open <main>, and its id ('' for none).
	 */
	public static function profile_main_landmark( string $header_html ): array {
		$tags        = new WP_HTML_Tag_Processor( $header_html );
		$open_main   = 0;
		$ids         = [];
		$skip_target = '';

		while ( $tags->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			if ( 'MAIN' === $tags->get_tag() ) {
				$open_main += $tags->is_tag_closer() ? -1 : 1;
				continue;
			}
			if ( $tags->is_tag_closer() ) {
				continue;
			}

			$id = $tags->get_attribute( 'id' );
			if ( is_string( $id ) ) {
				$ids[ $id ] = true;
			}

			$href = $tags->get_attribute( 'href' );
			if ( '' === $skip_target && 'A' === $tags->get_tag() && $tags->has_class( 'skip-link' ) && is_string( $href ) && str_starts_with( $href, '#' ) ) {
				$skip_target = substr( $href, 1 );
			}
		}

		if ( $open_main > 0 ) {
			return [
				'main' => false,
				'id'   => '',
			];
		}

		return [
			'main' => true,
			'id'   => '' !== $skip_target && ! isset( $ids[ $skip_target ] ) ? $skip_target : '',
		];
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
	 * Registra el bloque del directorio a partir de blocks/directorio/block.json.
	 *
	 * The metadata (title, description, keywords, supports and style) lives in block.json, and
	 * WordPress translates it with the plugin text domain. There is no editor JavaScript: with
	 * `autoRegister` (WordPress 7.0+) the editor registers the block itself and previews it with
	 * ServerSideRender, so the block and the shortcode share the same rendering. The directory
	 * uses fixed IDs in its markup, which is why block.json allows one instance per post.
	 * block.json also declares the block interactive, so WordPress processes its directives.
	 *
	 * @since 1.1
	 */
	public static function register_block(): void {
		register_block_type(
			RADIOEDU_CONECTA_DIR . 'blocks/directorio',
			[
				'render_callback' => [ self::class, 'render_block' ],
			]
		);
	}

	/**
	 * Renders the directory block with the same markup as the shortcode.
	 *
	 * The block is interactive (block.json), so WordPress processes its directives after this
	 * callback; doing it here as well would process them twice.
	 *
	 * @since 1.1
	 * @since 1.2 Leaves the directives to WordPress.
	 *
	 * @return string Salida HTML del bloque.
	 */
	public static function render_block(): string {
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), self::render_directory() );
	}

	/**
	 * Returns the URL of the directory, for the "Volver al directorio" link of the profiles.
	 *
	 * Uses the recorded directory page while it is published and still shows the directory, then
	 * the page with the demo slug, then the home page.
	 *
	 * @since 1.1
	 *
	 * @return string Directory URL.
	 */
	public static function get_directory_url(): string {
		$page_id = (int) get_option( self::DIRECTORY_PAGE_OPTION, 0 );
		if ( ! self::is_directory_page( $page_id ) ) {
			$fallback = get_page_by_path( 'traspasando-fronteras' );
			$page_id  = $fallback && self::is_directory_page( $fallback->ID ) ? $fallback->ID : 0;
		}
		$url = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );

		/**
		 * Filters the URL of the "Volver al directorio" link on the station profiles.
		 *
		 * @since 1.1
		 *
		 * @param string $url     Directory URL.
		 * @param int    $page_id ID of the directory page, or 0 when none was found.
		 */
		return (string) apply_filters( 'radioedu_conecta_directory_url', $url, $page_id );
	}

	/**
	 * Remembers the page that shows the directory, when no valid one is recorded yet.
	 *
	 * Runs when the shortcode or the block renders, so the profiles link back to wherever the
	 * directory was placed. A recorded page that still shows the directory is kept.
	 *
	 * @since 1.1
	 */
	private static function remember_directory_page(): void {
		$page_id = is_singular() ? (int) get_queried_object_id() : 0;
		if ( $page_id <= 0 || ! self::is_directory_page( $page_id ) || self::is_directory_page( (int) get_option( self::DIRECTORY_PAGE_OPTION, 0 ) ) ) {
			return;
		}

		update_option( self::DIRECTORY_PAGE_OPTION, $page_id, false );
	}

	/**
	 * Tells whether a post is published, public and shows the directory.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True for a published post without password that holds the shortcode or the block.
	 */
	private static function is_directory_page( int $post_id ): bool {
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return false;
		}

		return self::content_shows_directory( $post->post_content, [] );
	}

	/**
	 * Tells whether content holds the directory, directly or inside synced patterns.
	 *
	 * A page may show the directory through a synced pattern (a core/block that references a
	 * wp_block post). Those are opened too, up to five levels, and each one only once.
	 *
	 * @since 1.1
	 *
	 * @param string          $content Post content.
	 * @param array<int, int> $opened  IDs of the synced patterns already opened.
	 * @return bool True when the shortcode or the block is found.
	 */
	private static function content_shows_directory( string $content, array $opened ): bool {
		if ( has_shortcode( $content, self::SHORTCODE_TAG ) || has_block( self::BLOCK_NAME, $content ) ) {
			return true;
		}
		if ( count( $opened ) >= 5 || ! has_block( 'core/block', $content ) ) {
			return false;
		}

		foreach ( self::synced_pattern_ids( parse_blocks( $content ) ) as $pattern_id ) {
			if ( in_array( $pattern_id, $opened, true ) ) {
				continue;
			}
			$pattern = get_post( $pattern_id );
			if ( $pattern instanceof \WP_Post && 'wp_block' === $pattern->post_type && 'publish' === $pattern->post_status
				&& self::content_shows_directory( $pattern->post_content, array_merge( $opened, [ $pattern_id ] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Lists the synced patterns referenced by some blocks, including nested ones.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, int> Referenced wp_block IDs.
	 */
	private static function synced_pattern_ids( array $blocks ): array {
		$ids = [];
		foreach ( $blocks as $block ) {
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			if ( 'core/block' === ( $block['blockName'] ?? '' ) && ! empty( $attrs['ref'] ) ) {
				$ids[] = (int) $attrs['ref'];
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, self::synced_pattern_ids( $block['innerBlocks'] ) );
			}
		}

		return $ids;
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
		}

		$post = get_post();
		if ( $post instanceof \WP_Post && ( has_shortcode( $post->post_content, self::SHORTCODE_TAG ) || has_block( self::BLOCK_NAME, $post ) ) ) {
			wp_enqueue_style( 'radioedu-leaflet-css' );
			wp_enqueue_style( 'radioedu-directorio-css' );
		}
	}

	/**
	 * Registra los estilos de front-end para su posterior encolado condicional.
	 *
	 * The scripts are a script module now, registered by DirectoryStore. Leaflet is part of it and
	 * is downloaded only after the visitor accepts the map; its stylesheet stays here.
	 *
	 * @since 1.2 Registers only the styles.
	 */
	public static function register_assets(): void {
		$css_ver = file_exists( RADIOEDU_CONECTA_DIR . 'assets/css/directorio.css' )
			? (string) filemtime( RADIOEDU_CONECTA_DIR . 'assets/css/directorio.css' )
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
	}

	/**
	 * Lists the stations the current user manages, for the sender selector of the proposal panel.
	 *
	 * @since 1.2 Replaces build_user_context_and_js_data(); the module config holds the rest.
	 *
	 * @return array<int, array{id: int, nombre: string}> Stations.
	 */
	private static function user_centers(): array {
		$current_user_id = get_current_user_id();
		if ( $current_user_id <= 0 ) {
			return [];
		}

		$centers = [];
		foreach ( ColaboracionesController::get_user_managed_center_ids( $current_user_id ) as $center_id ) {
			$centers[] = [
				'id'     => $center_id,
				'nombre' => get_the_title( $center_id ),
			];
		}

		return $centers;
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
		DirectoryStore::enqueue();

		$user_centers_list = self::user_centers();

		$title     = get_the_title( $post_id );
		$logo_id   = (int) get_post_meta( $post_id, '_radioedu_logo_id', true );
		$logo_url  = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : (string) get_post_meta( $post_id, '_radioedu_logo_url', true );
		$cover_url = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		if ( '' === $cover_url ) {
			$cover_url = (string) get_post_meta( $post_id, '_radioedu_cover_url', true );
		}

		// Fallback para centros de ejemplo si no tienen imagen personalizada asignada.
		if ( '' === $cover_url ) {
			$cover_url = DemoDataImporter::get_demo_image_url( $post_id, 'header' );
		}
		if ( '' === $logo_url ) {
			$logo_url = DemoDataImporter::get_demo_image_url( $post_id, 'logo' );
		}
		$cover_url = DirectoryStore::css_safe_url( $cover_url );

		$bandera     = (string) get_post_meta( $post_id, '_radioedu_bandera', true );
		$flag_emoji  = DirectoryStore::flag_icon( '' !== $bandera ? $bandera : 'es-ex' );
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

		$directorio_url = self::get_directory_url();

		// Datos del panel lateral de propuesta (receptor preseleccionado: el centro del perfil).
		$plain_title           = self::plain_text( $title );
		$drawer_receptor_id    = $post_id;
		$drawer_receptor_label = $plain_title . ( '' !== $ubicacion ? ' (' . self::plain_text( $ubicacion ) . ')' : '' );
		DirectoryStore::add_drawer_state( $drawer_receptor_id, $drawer_receptor_label );
		$match_context = [
			'centro' => [
				'id'        => $post_id,
				'nombre'    => $plain_title,
				'ubicacion' => '' !== $ubicacion ? self::plain_text( $ubicacion ) : __( 'Extremadura', 'radioedu-conecta' ),
			],
		];

		// The coordinator writes the description, and kses keeps data-* attributes, so it could
		// carry directives. It goes in after the directives are processed, outside any
		// interactive region of the template.
		$description_slot = '<!-- radioedu-conecta-description-' . wp_generate_uuid4() . ' -->';
		$login_url        = wp_login_url( (string) get_permalink( $post_id ) );

		ob_start();
		include RADIOEDU_CONECTA_DIR . 'templates/single-centro.php';
		$html = wp_interactivity_process_directives( (string) ob_get_clean() );

		return str_replace( $description_slot, $content, $html );
	}

	/**
	 * Decodes the entities of a rendered title or term name, for a text context.
	 *
	 * Rendered titles (get_the_title()) and term names carry HTML entities (&#8217;, &amp;). Text
	 * written by a directive is escaped again, so it needs the plain characters.
	 *
	 * @since 1.2
	 *
	 * @param string $text Rendered text.
	 * @return string Plain text.
	 */
	public static function plain_text( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Renderiza el directorio completo.
	 *
	 * @since 1.2 Processes the directives of the markup, which the block leaves to WordPress.
	 *
	 * @param array<string, mixed>|string $atts Atributos del shortcode.
	 * @return string Salida HTML del directorio.
	 */
	public static function render_shortcode( array|string $atts = [] ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress supplies shortcode attributes.
		return wp_interactivity_process_directives( self::render_directory() );
	}

	/**
	 * Renders the directory markup, with its directives, for the shortcode and the block.
	 *
	 * The first page of results of the search in the URL is rendered here, so the directory
	 * shows its stations and its filters work without JavaScript.
	 *
	 * @since 1.2
	 *
	 * @return string Markup, before the directives are processed.
	 */
	private static function render_directory(): string {
		self::remember_directory_page();

		if ( ! wp_style_is( 'radioedu-directorio-css', 'registered' ) ) {
			self::register_assets();
		}

		// Encolar los assets condicionalmente sólo en la página donde se use el directorio.
		wp_enqueue_style( 'radioedu-leaflet-css' );
		wp_enqueue_style( 'radioedu-directorio-css' );
		DirectoryStore::enqueue();

		$user_centers_list = self::user_centers();

		// Terms of each filter, for its selector and to check the filters in the URL.
		$filter_terms = [];
		foreach ( DirectoryStore::FILTER_PARAMS as $param => $taxonomy ) {
			$found                  = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			$filter_terms[ $param ] = is_array( $found ) ? array_values( $found ) : [];
		}

		$search   = DirectoryStore::requested_search( array_map( static fn( array $terms ): array => wp_list_pluck( $terms, 'slug' ), $filter_terms ) );
		$results  = DirectoryStore::load_results( $search );
		$base_url = DirectoryStore::base_url();
		DirectoryStore::add_directory_state( $search, $results, $base_url );

		// A GET form drops the query of its action, so the page arguments go as hidden fields.
		$form_action = (string) strtok( $base_url, '?' );
		$form_fields = DirectoryStore::base_query_args( $base_url );
		$next_url    = DirectoryStore::page_url( $base_url, $search, $search['page'] + 1 );
		$prev_url    = $search['page'] > 1 ? DirectoryStore::page_url( $base_url, $search, $search['page'] - 1 ) : '';
		$total_label = number_format_i18n( $results['total'] );
		$login_url   = wp_login_url( $base_url );

		// El receptor del panel lateral se rellena al pulsar "Proponer match" en una tarjeta.
		$drawer_receptor_id    = 0;
		$drawer_receptor_label = '';

		ob_start();
		include RADIOEDU_CONECTA_DIR . 'templates/directorio.php';
		return (string) ob_get_clean();
	}
}
