<?php
/**
 * Store of the Interactivity API for the directory and the proposal panel.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Frontend;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Rest\CentrosController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_HTML_Tag_Processor;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the directory script module and gives it its config and its server state.
 *
 * The server renders the first page of results with the same directives the module runs in the
 * browser, so the directory works without JavaScript: the filters form is sent by GET and read
 * here, and the pages of results are links. The derived state the browser computes from each
 * card is mirrored here as closures, which the Interactivity API evaluates while rendering.
 *
 * @since 1.2
 */
final class DirectoryStore {

	/**
	 * Namespace of the store, shared by the directory and the proposal panel.
	 *
	 * @since 1.2
	 */
	public const NAMESPACE = 'radioedu-conecta/directorio';

	/**
	 * ID of the script module.
	 *
	 * @since 1.2
	 */
	public const MODULE_ID = 'radioedu-conecta/directorio';

	/**
	 * Cards per page, the same as the REST endpoint default.
	 *
	 * @since 1.2
	 */
	public const PER_PAGE = 24;

	/**
	 * Most terms a filter of the directory lists and accepts from the URL.
	 *
	 * A safety ceiling for every render: the largest filter, the places, holds one term per town
	 * of the network, well under it.
	 *
	 * @since 1.2
	 */
	public const MAX_FILTER_TERMS = 1000;

	/**
	 * Sort orders the REST endpoint accepts; the first one is the default.
	 *
	 * @since 1.2
	 */
	public const ORDERS = [ 'afines', 'recientes', 'alfa' ];

	/**
	 * Filter parameter of the page URL and of the REST endpoint => taxonomy.
	 *
	 * @since 1.2
	 */
	public const FILTER_PARAMS = [
		'ubicacion' => TaxonomiesRegistrar::TAX_LOCALIZACION,
		'idioma'    => TaxonomiesRegistrar::TAX_IDIOMA,
		'etapa'     => TaxonomiesRegistrar::TAX_ETAPA,
		'tematica'  => TaxonomiesRegistrar::TAX_TEMATICA,
		'formato'   => TaxonomiesRegistrar::TAX_FORMATO,
	];

	/**
	 * Free text parameter of the page URL. "search" is a public WordPress query var.
	 *
	 * @since 1.2
	 */
	public const SEARCH_PARAM = 'buscar';

	/**
	 * Sort order parameter of the page URL.
	 *
	 * @since 1.2
	 */
	public const ORDER_PARAM = 'orden';

	/**
	 * Page parameter of the page URL. "page" and "paged" are public WordPress query vars.
	 *
	 * @since 1.2
	 */
	public const PAGE_PARAM = 'pagina';

	/**
	 * Tag colours the cards know.
	 *
	 * @since 1.2
	 */
	public const TAG_COLORS = [ 'mint', 'coral', 'blue' ];

	/**
	 * Logo shown when a station has none: a microphone in the accent colour.
	 *
	 * @since 1.2
	 */
	public const DEFAULT_LOGO = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="%2300838f"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-1.66 0-3-1.34-3-3V9c0-1.66 1.34-3 3-3s3 1.34 3 3v5c0 1.66-1.34 3-3 3z"/></svg>';

	/**
	 * Flag code => badge shown on the cards and the profile.
	 *
	 * @since 1.2
	 */
	public const FLAG_ICONS = [
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

	/**
	 * Badge for an unknown flag code.
	 *
	 * @since 1.2
	 */
	public const DEFAULT_FLAG = '📻';

	/**
	 * Whether the config and the derived state were already added in this request.
	 *
	 * @var bool
	 */
	private static bool $prepared = false;

	/**
	 * Hooks the registration of the module.
	 *
	 * @since 1.2
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_module' ] );
	}

	/**
	 * Registers the script module built in build/directorio-view.js.
	 *
	 * Without a build the module is not registered and the directory stays as the server renders
	 * it: the results, the filters and the pages work, the map and the proposals do not.
	 *
	 * @since 1.2
	 */
	public static function register_module(): void {
		$asset_file = RADIOEDU_CONECTA_DIR . 'build/directorio-view.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_register_script_module(
			self::MODULE_ID,
			RADIOEDU_CONECTA_URL . 'build/directorio-view.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? RADIOEDU_CONECTA_VERSION
		);
	}

	/**
	 * Enqueues the module and adds its config and its derived state, once per request.
	 *
	 * @since 1.2
	 */
	public static function enqueue(): void {
		wp_enqueue_script_module( self::MODULE_ID );
		if ( self::$prepared ) {
			return;
		}
		self::$prepared = true;

		wp_interactivity_config( self::NAMESPACE, self::config() );
		wp_interactivity_state( self::NAMESPACE, self::derived_state() );
	}

	/**
	 * Settings and texts the module reads but never changes.
	 *
	 * @since 1.2
	 *
	 * @return array<string, mixed> Config.
	 */
	public static function config(): array {
		global $wp_locale;

		$logged_in = is_user_logged_in();

		return [
			'restUrl'      => esc_url_raw( rest_url( 'radioedu-conecta/v1/' ) ),
			'restNonce'    => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
			'isLoggedIn'   => $logged_in,
			'canPropose'   => current_user_can( 'propose_radioedu_colaboracion' ),
			'perPage'      => self::PER_PAGE,
			'thousandsSep' => isset( $wp_locale->number_format['thousands_sep'] ) ? (string) $wp_locale->number_format['thousands_sep'] : ',',
			'mapCenter'    => [
				'lat'  => (float) Settings::get( 'default_lat', '39.4699' ),
				'lng'  => (float) Settings::get( 'default_lng', '-6.3708' ),
				'zoom' => (int) Settings::get( 'default_zoom', 8 ),
			],
			'flags'        => self::FLAG_ICONS,
			'defaultLogo'  => self::DEFAULT_LOGO,
			'favoritesKey' => self::favorites_key(),
			'i18n'         => [
				/* translators: %s: Number of schools found. */
				'resultsCount'     => __( 'Resultados (%s centros)', 'radioedu-conecta' ),
				'errorOccurred'    => __( 'Ha ocurrido un error al procesar la solicitud.', 'radioedu-conecta' ),
				'viewProfile'      => __( 'Ver perfil', 'radioedu-conecta' ),
				'submitProposal'   => __( 'Enviar propuesta', 'radioedu-conecta' ),
				'sending'          => __( 'Enviando propuesta…', 'radioedu-conecta' ),
				'sendSuccess'      => __( '¡Propuesta enviada con éxito!', 'radioedu-conecta' ),
				'loginRequired'    => __( 'Debes iniciar sesión con tu cuenta de coordinador de centro para enviar propuestas de colaboración.', 'radioedu-conecta' ),
				'noPermission'     => __( 'Tu cuenta actual no dispone de permisos de coordinador para proponer colaboraciones.', 'radioedu-conecta' ),
				'selectYourCenter' => __( 'Por favor, selecciona tu centro escolar.', 'radioedu-conecta' ),
				'favorite'         => __( 'Añadir a favoritos', 'radioedu-conecta' ),
				'unfavorite'       => __( 'Quitar de favoritos', 'radioedu-conecta' ),
				'defaultLocation'  => __( 'Extremadura', 'radioedu-conecta' ),
				'mapAttribution'   => __( 'Colaboradores de OpenStreetMap', 'radioedu-conecta' ),
				/* translators: 1: number of centros on the map, 2: number of results. */
				'mapPartial'       => __( 'El mapa muestra %1$s de %2$s centros. Usa los filtros para acotar la búsqueda.', 'radioedu-conecta' ),
				'mapWithdrawn'     => __( 'Has retirado el consentimiento: el mapa ya no se carga.', 'radioedu-conecta' ),
			],
		];
	}

	/**
	 * Reads the search sent by the filters form without JavaScript, or by a shared link.
	 *
	 * Every value is checked against what the directory offers: a filter only counts when it is
	 * the slug of an existing term, and the order only when the endpoint knows it.
	 *
	 * @since 1.2
	 *
	 * @param array<string, array<int, string>> $term_slugs Filter parameter => slugs of its terms.
	 * @return array{filters: array<string, string>, search: string, orden: string, page: int} Search.
	 */
	public static function requested_search( array $term_slugs ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters of a public listing, sent by a GET form or a link: they change no data.
		$filters = [];
		foreach ( array_keys( self::FILTER_PARAMS ) as $param ) {
			$slug = isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : '';
			if ( '' !== $slug && in_array( $slug, $term_slugs[ $param ] ?? [], true ) ) {
				$filters[ $param ] = $slug;
			}
		}
		$search = isset( $_GET[ self::SEARCH_PARAM ] ) && is_string( $_GET[ self::SEARCH_PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::SEARCH_PARAM ] ) ) : '';
		$orden  = isset( $_GET[ self::ORDER_PARAM ] ) && is_string( $_GET[ self::ORDER_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::ORDER_PARAM ] ) ) : '';
		$page   = isset( $_GET[ self::PAGE_PARAM ] ) && is_string( $_GET[ self::PAGE_PARAM ] ) ? intval( wp_unslash( $_GET[ self::PAGE_PARAM ] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return [
			'filters' => $filters,
			'search'  => mb_substr( $search, 0, 200 ),
			'orden'   => in_array( $orden, self::ORDERS, true ) ? $orden : self::ORDERS[0],
			'page'    => min( max( 1, $page ), 10000 ),
		];
	}

	/**
	 * Loads one page of results through the REST controller, so the first page has the same
	 * query, fields and affinity order as the pages the browser requests.
	 *
	 * @since 1.2
	 *
	 * @param array{filters: array<string, string>, search: string, orden: string, page: int} $search Search.
	 * @return array{centros: array<int, array<string, mixed>>, total: int, total_pages: int} Results.
	 */
	public static function load_results( array $search ): array {
		$request = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' );
		$request->set_query_params(
			array_merge(
				$search['filters'],
				[
					'search'   => $search['search'],
					'orden'    => $search['orden'],
					'page'     => $search['page'],
					'per_page' => self::PER_PAGE,
				]
			)
		);
		$data = ( new CentrosController() )->get_items( $request )->get_data();

		return [
			'centros'     => is_array( $data['centros'] ?? null ) ? array_values( $data['centros'] ) : [],
			'total'       => (int) ( $data['total'] ?? 0 ),
			'total_pages' => (int) ( $data['total_pages'] ?? 0 ),
		];
	}

	/**
	 * Adds the state of the directory shown on this page.
	 *
	 * @since 1.2
	 *
	 * @param array{filters: array<string, string>, search: string, orden: string, page: int} $search   Search shown.
	 * @param array{centros: array<int, array<string, mixed>>, total: int, total_pages: int}  $results  Its results.
	 * @param string                                                                          $base_url URL of the page without the search.
	 */
	public static function add_directory_state( array $search, array $results, string $base_url ): void {
		$page = $search['page'];

		wp_interactivity_state(
			self::NAMESPACE,
			[
				'centros'     => $results['centros'],
				'total'       => $results['total'],
				'totalLabel'  => number_format_i18n( $results['total'] ),
				'totalPages'  => $results['total_pages'],
				'page'        => $page,
				'firstPage'   => $page,
				'applied'     => [
					'filters' => $search['filters'],
					'search'  => $search['search'],
					'orden'   => $search['orden'],
				],
				'nextPageUrl' => self::page_url( $base_url, $search, $page + 1 ),
				'prevPageUrl' => $page > 1 ? self::page_url( $base_url, $search, $page - 1 ) : '',
			]
		);
	}

	/**
	 * Adds the station the proposal panel of a profile proposes to.
	 *
	 * @since 1.2
	 *
	 * @param int    $receptor_id Station ID.
	 * @param string $label       Name and place of the station.
	 */
	public static function add_drawer_state( int $receptor_id, string $label ): void {
		wp_interactivity_state(
			self::NAMESPACE,
			[
				'drawer' => [
					'receptorId'    => $receptor_id,
					'receptorLabel' => $label,
				],
			]
		);
	}

	/**
	 * URL of the page that shows the directory, without the search.
	 *
	 * @since 1.2
	 *
	 * @return string URL.
	 */
	public static function base_url(): string {
		$url = is_singular() ? (string) get_permalink( get_queried_object_id() ) : '';

		return '' !== $url ? remove_query_arg( self::url_params(), $url ) : remove_query_arg( self::url_params() );
	}

	/**
	 * URL of a page of results of a search.
	 *
	 * @since 1.2
	 *
	 * @param string                                                                          $base_url URL of the page without the search.
	 * @param array{filters: array<string, string>, search: string, orden: string, page: int} $search   Search.
	 * @param int                                                                             $page     Page of results.
	 * @return string URL, not escaped.
	 */
	public static function page_url( string $base_url, array $search, int $page ): string {
		$args = $search['filters'];
		if ( '' !== $search['search'] ) {
			$args[ self::SEARCH_PARAM ] = $search['search'];
		}
		if ( self::ORDERS[0] !== $search['orden'] ) {
			$args[ self::ORDER_PARAM ] = $search['orden'];
		}
		if ( $page > 1 ) {
			$args[ self::PAGE_PARAM ] = (string) $page;
		}

		return add_query_arg( array_map( 'rawurlencode', $args ), remove_query_arg( self::url_params(), $base_url ) );
	}

	/**
	 * Query arguments of the page URL that a GET form would drop, such as page_id with plain
	 * permalinks: the form repeats them as hidden fields.
	 *
	 * @since 1.2
	 *
	 * @param string $base_url URL of the page without the search.
	 * @return array<string, string> Name => value.
	 */
	public static function base_query_args( string $base_url ): array {
		$query = (string) wp_parse_url( $base_url, PHP_URL_QUERY );
		$args  = [];
		wp_parse_str( $query, $args );

		$fields = [];
		foreach ( $args as $name => $value ) {
			if ( is_string( $value ) && ! in_array( (string) $name, self::url_params(), true ) ) {
				$fields[ (string) $name ] = $value;
			}
		}

		return $fields;
	}

	/**
	 * Key under which the browser keeps the favourite stations of this site.
	 *
	 * The sites of a network in subdirectories share one origin, and so one localStorage, while
	 * each site numbers its stations on its own: with a shared key, a favourite saved on one site
	 * marked the station with the same ID on another. The main site of the main network, and a
	 * single site, keep the key the favourites were already saved under.
	 *
	 * @since 1.2
	 *
	 * @return string Storage key.
	 */
	public static function favorites_key(): string {
		if ( ! is_multisite() || is_main_site( null, get_main_network_id() ) ) {
			return 'radioedu_favoritos';
		}

		return 'radioedu_conecta_favoritos_' . get_current_blog_id();
	}

	/**
	 * Badge of a flag code.
	 *
	 * @since 1.2
	 *
	 * @param string $code Flag code.
	 * @return string Badge.
	 */
	public static function flag_icon( string $code ): string {
		return self::FLAG_ICONS[ $code ] ?? self::DEFAULT_FLAG;
	}

	/**
	 * Prepares a URL to sit inside `url("...")` in a style attribute.
	 *
	 * The esc_url() function keeps quotes and parentheses, and the browser decodes the attribute
	 * entities before it reads the CSS, so they are percent-encoded here and a crafted URL cannot
	 * close url().
	 *
	 * @since 1.2
	 *
	 * @param string $url Image URL.
	 * @return string Safe http(s) URL for a CSS context, or an empty string.
	 */
	public static function css_safe_url( string $url ): string {
		$url = esc_url_raw( $url, [ 'http', 'https' ] );

		return str_replace(
			[ '"', "'", '(', ')', '\\', ' ' ],
			[ '%22', '%27', '%28', '%29', '%5C', '%20' ],
			$url
		);
	}

	/**
	 * Removes the directives that use one of the plugin's stores from markup it did not write.
	 *
	 * The kses filters keep data-* attributes, so a station description can carry directives, and
	 * the client runtime runs every interactive region of the page, not only the plugin's. Without the
	 * directives that name a store of the plugin, the description cannot open the proposal panel
	 * or act on the directory. Core blocks keep their own directives (core/image, for example).
	 *
	 * @since 1.2
	 *
	 * @param string $html Rendered markup.
	 * @return string The markup without those directives.
	 */
	public static function remove_store_directives( string $html ): string {
		if ( false === stripos( $html, 'data-wp-' ) ) {
			return $html;
		}

		$prefix = 'radioedu-conecta/'; // Every store of the plugin.
		$tags   = new WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			foreach ( $tags->get_attribute_names_with_prefix( 'data-wp-' ) ?? [] as $name ) {
				$value = $tags->get_attribute( $name );
				if ( ! is_string( $value ) ) {
					continue;
				}
				// The namespace of data-wp-interactive may come as JSON, where it can be escaped.
				if ( 'data-wp-interactive' === $name && str_starts_with( ltrim( $value ), '{' ) ) {
					$decoded = json_decode( $value, true );
					$value  .= is_array( $decoded ) && is_string( $decoded['namespace'] ?? null ) ? ' ' . $decoded['namespace'] : '';
				}
				if ( false !== stripos( $value, $prefix ) ) {
					$tags->remove_attribute( $name );
				}
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Names of the search parameters of the page URL.
	 *
	 * @return array<int, string> Names.
	 */
	private static function url_params(): array {
		return array_merge( array_keys( self::FILTER_PARAMS ), [ self::SEARCH_PARAM, self::ORDER_PARAM, self::PAGE_PARAM ] );
	}

	/**
	 * Station of the card being rendered.
	 *
	 * @return array<string, mixed> Station, or an empty array outside a card.
	 */
	private static function card(): array {
		$context = wp_interactivity_get_context( self::NAMESPACE );

		return isset( $context['centro'] ) && is_array( $context['centro'] ) ? $context['centro'] : [];
	}

	/**
	 * A value of the server state.
	 *
	 * @param string $key State key.
	 * @return mixed Value, or null.
	 */
	private static function state_value( string $key ): mixed {
		$state = wp_interactivity_state( self::NAMESPACE );

		return $state[ $key ] ?? null;
	}

	/**
	 * Server copy of the derived state the module computes, for the first render.
	 *
	 * Each closure mirrors the getter of the same name in directorio-view.js. On the server the
	 * map is hidden, nothing is loading, there are no favourites, and the parts that need
	 * JavaScript (proposals, favourites, map) are hidden.
	 *
	 * @return array<string, \Closure> Derived state.
	 */
	private static function derived_state(): array {
		$has_results = static fn(): bool => ! empty( self::state_value( 'centros' ) );
		$has_next    = static fn(): bool => (int) self::state_value( 'page' ) < (int) self::state_value( 'totalPages' );
		$has_prev    = static fn(): bool => (int) self::state_value( 'firstPage' ) > 1;

		return [
			'cardsHidden'      => static fn(): bool => ! $has_results(),
			'noResultsHidden'  => static fn(): bool => $has_results(),
			'loadingHidden'    => static fn(): bool => true,
			'nextHidden'       => static fn(): bool => ! $has_next(),
			'prevHidden'       => static fn(): bool => ! $has_prev(),
			'paginationHidden' => static fn(): bool => ! $has_next() && ! $has_prev(),
			'coverStyle'       => static function (): string {
				$url = self::css_safe_url( (string) ( self::card()['foto_cabecera'] ?? '' ) );
				return '' !== $url ? 'url("' . $url . '")' : '';
			},
			'logoSrc'          => static function (): string {
				$url = esc_url_raw( (string) ( self::card()['logo_radio'] ?? '' ), [ 'http', 'https' ] );
				return '' !== $url ? $url : self::DEFAULT_LOGO;
			},
			'flag'             => static fn(): string => self::flag_icon( (string) ( self::card()['bandera'] ?? '' ) ),
			'locationText'     => static function (): string {
				$place = (string) ( self::card()['ubicacion'] ?? '' );
				return '' !== $place ? $place : __( 'Extremadura', 'radioedu-conecta' );
			},
			'cardTags'         => static function (): array {
				$tags = self::card()['tags'] ?? [];
				return is_array( $tags ) ? array_slice( array_values( $tags ), 0, 5 ) : [];
			},
			'tagClass'         => static function (): string {
				$context = wp_interactivity_get_context( self::NAMESPACE );
				$color   = is_array( $context['tag'] ?? null ) ? (string) ( $context['tag']['color'] ?? '' ) : '';
				return 'radioedu-tag radioedu-tag-' . ( in_array( $color, self::TAG_COLORS, true ) ? $color : 'mint' );
			},
			'profileUrl'       => static function (): string {
				$url = esc_url_raw( (string) ( self::card()['enlace_perfil'] ?? '' ), [ 'http', 'https' ] );
				return '' !== $url ? $url : '#';
			},
			'isFavorite'       => static fn(): bool => false,
			'favoriteLabel'    => static fn(): string => __( 'Añadir a favoritos', 'radioedu-conecta' ),
			'isUnavailable'    => static fn(): bool => false === ( self::card()['disponible'] ?? true ),
			// Hidden without JavaScript, except the "No disponible" notice of an unavailable station.
			'matchHidden'      => static fn(): bool => false !== ( self::card()['disponible'] ?? true ),
			'submitDisabled'   => static fn(): bool => false,
			'submitLabel'      => static fn(): string => __( 'Enviar propuesta', 'radioedu-conecta' ),
		];
	}
}
