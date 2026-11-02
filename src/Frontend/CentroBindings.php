<?php
/**
 * Block Bindings source with the public data of a station.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Frontend;

use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_Block;
use WP_HTML_Tag_Processor;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Lets blocks show the public data of a station, and adds a pattern that lists stations.
 *
 * The station meta is protected (its keys start with an underscore), so the core post meta source
 * does not read it. This source gives the same public data as the directory cards, never the
 * coordinator's name or email, and only for stations the visitor can see. A bound block whose
 * value is empty is not printed, so a station without a podcast shows no podcast button, and no
 * empty row of buttons either.
 *
 * In the editor, bound blocks show the source label instead of the value: WordPress only sends
 * the editor the label of a source registered in PHP.
 *
 * @since 1.2
 */
final class CentroBindings {

	/**
	 * Name of the source.
	 *
	 * @since 1.2
	 *
	 * @var string
	 */
	public const SOURCE = 'radioedu-conecta/centro';

	/**
	 * Name of the pattern that lists stations.
	 *
	 * @since 1.2
	 *
	 * @var string
	 */
	public const PATTERN = 'radioedu-conecta/listado-radios';

	/**
	 * Keys a binding can ask for, in its `key` argument.
	 *
	 * @since 1.2
	 *
	 * @var array<int, string>
	 */
	public const KEYS = [
		'name',
		'location',
		'flag',
		'ages',
		'languages',
		'tags',
		'availability',
		'podcast_url',
		'website_url',
		'logo_url',
		'cover_url',
		'profile_url',
	];

	/**
	 * Keys whose value is a URL.
	 *
	 * @since 1.2
	 *
	 * @var array<int, string>
	 */
	private const URL_KEYS = [ 'podcast_url', 'website_url', 'logo_url', 'cover_url', 'profile_url' ];

	/**
	 * Block attributes the source fills: the text, URL, alternative text and title of the core
	 * blocks that support bindings. The ID of an image, a link target or a date are left alone.
	 *
	 * @since 1.2
	 *
	 * @var array<int, string>
	 */
	private const ATTRIBUTES = [ 'content', 'text', 'caption', 'url', 'alt', 'title' ];

	/**
	 * Attributes without which a bound block has nothing to show.
	 *
	 * @since 1.2
	 *
	 * @var array<int, string>
	 */
	private const REQUIRED_ATTRIBUTES = [ 'content', 'text', 'url' ];

	/**
	 * Hooks the source, the pattern and the removal of empty bound blocks.
	 *
	 * @since 1.2
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_source' ] );
		add_action( 'init', [ self::class, 'register_pattern' ] );
		add_filter( 'render_block', [ self::class, 'hide_empty_block' ], 10, 3 );
	}

	/**
	 * Registers the source.
	 *
	 * @since 1.2
	 */
	public static function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			[
				'label'              => __( 'Datos de la radio', 'radioedu-conecta' ),
				'get_value_callback' => [ self::class, 'get_value' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);
	}

	/**
	 * Value of a binding, escaped for the attribute it fills.
	 *
	 * Core passes rich text values through wp_kses_post(), so text is escaped here and a name with
	 * "<" shows as written. Values set as HTML attributes are escaped by core and go as plain text.
	 * URLs keep only the http and https protocols, whichever attribute they fill.
	 *
	 * @since 1.2
	 *
	 * @param array<string, mixed> $source_args    Binding arguments: `key`, one of self::KEYS.
	 * @param WP_Block             $block          Bound block.
	 * @param string               $attribute_name Bound attribute.
	 * @return string|null The value, '' when the station has none or cannot be shown, or null when
	 *                     the binding is not one this source fills, which leaves the attribute as saved.
	 */
	public static function get_value( array $source_args, WP_Block $block, string $attribute_name ): ?string {
		$key = is_string( $source_args['key'] ?? null ) ? $source_args['key'] : '';
		if ( ! in_array( $key, self::KEYS, true ) || ! in_array( $attribute_name, self::ATTRIBUTES, true ) ) {
			return null;
		}

		$is_url = in_array( $key, self::URL_KEYS, true );
		if ( 'url' === $attribute_name && ! $is_url ) {
			return null;
		}

		$value = self::value( (int) ( $block->context['postId'] ?? 0 ), $key );
		if ( $is_url ) {
			$value = esc_url_raw( $value, [ 'http', 'https' ] );
		}

		$source = $block->block_type->attributes[ $attribute_name ]['source'] ?? '';

		return in_array( $source, [ 'rich-text', 'html' ], true ) ? esc_html( $value ) : $value;
	}

	/**
	 * Removes a bound block whose text or URL comes out empty.
	 *
	 * @since 1.2
	 *
	 * @param string               $block_content Rendered block.
	 * @param array<string, mixed> $block         Parsed block.
	 * @param WP_Block|null        $instance      Block instance; some code applies the filter without it.
	 * @return string The block, or '' when it has nothing to show.
	 */
	public static function hide_empty_block( string $block_content, array $block, ?WP_Block $instance = null ): string {
		if ( null === $instance ) {
			return $block_content;
		}

		// A Buttons block whose bound buttons were all removed would leave an empty row.
		if ( 'core/buttons' === $instance->name && self::binds_inner_block( $block ) ) {
			$tags = new WP_HTML_Tag_Processor( $block_content );
			return $tags->next_tag( [ 'class_name' => 'wp-block-button' ] ) ? $block_content : '';
		}

		$bindings = $block['attrs']['metadata']['bindings'] ?? null;
		if ( ! is_array( $bindings ) ) {
			return $block_content;
		}

		// Only the bindings core applies, to the attributes it supports for that block.
		$supported = get_block_bindings_supported_attributes( $instance->name );
		foreach ( array_intersect( self::REQUIRED_ATTRIBUTES, $supported ) as $attribute_name ) {
			$binding = $bindings[ $attribute_name ] ?? null;
			if ( ! is_array( $binding ) || self::SOURCE !== ( $binding['source'] ?? '' ) ) {
				continue;
			}
			$args = is_array( $binding['args'] ?? null ) ? $binding['args'] : [];
			if ( '' === self::get_value( $args, $instance, $attribute_name ) ) {
				return '';
			}
		}

		return $block_content;
	}

	/**
	 * Public value of a station, as plain text or an unescaped URL.
	 *
	 * @since 1.2
	 *
	 * @param int    $post_id Station ID.
	 * @param string $key     One of self::KEYS.
	 * @return string The value, or '' when there is none or the visitor cannot see the station.
	 */
	public static function value( int $post_id, string $key ): string {
		$post = get_post( $post_id );
		if ( ! $post || CentroPostType::POST_TYPE !== $post->post_type || post_password_required( $post )
			|| ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) ) {
			return '';
		}

		switch ( $key ) {
			case 'name':
				return DirectorioShortcode::plain_text( get_the_title( $post ) );
			case 'location':
				return implode( ', ', array_reverse( self::term_names( $post_id, TaxonomiesRegistrar::TAX_LOCALIZACION ) ) );
			case 'flag':
				$flag = (string) get_post_meta( $post_id, '_radioedu_bandera', true );
				return DirectoryStore::flag_icon( '' !== $flag ? $flag : 'es-ex' );
			case 'ages':
				return (string) get_post_meta( $post_id, '_radioedu_edades', true );
			case 'languages':
				return implode( ', ', self::term_names( $post_id, TaxonomiesRegistrar::TAX_IDIOMA ) );
			case 'tags':
				return implode(
					', ',
					array_merge(
						self::term_names( $post_id, TaxonomiesRegistrar::TAX_FORMATO ),
						self::term_names( $post_id, TaxonomiesRegistrar::TAX_TEMATICA ),
						self::term_names( $post_id, TaxonomiesRegistrar::TAX_ETAPA )
					)
				);
			case 'availability':
				return '0' !== get_post_meta( $post_id, '_radioedu_disponible_colaborar', true )
					? __( 'Disponible para colaborar', 'radioedu-conecta' )
					: __( 'No disponible actualmente', 'radioedu-conecta' );
			case 'podcast_url':
				return (string) get_post_meta( $post_id, '_radioedu_podcast_url', true );
			case 'website_url':
				return (string) get_post_meta( $post_id, '_radioedu_web_centro', true );
			case 'logo_url':
				$logo_id = (int) get_post_meta( $post_id, '_radioedu_logo_id', true );
				$logo    = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : (string) get_post_meta( $post_id, '_radioedu_logo_url', true );
				return '' !== $logo ? $logo : DemoDataImporter::get_demo_image_url( $post_id, 'logo' );
			case 'cover_url':
				$cover = (string) get_the_post_thumbnail_url( $post, 'medium_large' );
				if ( '' === $cover ) {
					$cover = (string) get_post_meta( $post_id, '_radioedu_cover_url', true );
				}
				return '' !== $cover ? $cover : DemoDataImporter::get_demo_image_url( $post_id, 'header' );
			case 'profile_url':
				return (string) get_permalink( $post );
		}

		return '';
	}

	/**
	 * Registers the pattern "Listado de radios", a query loop of stations with bound blocks.
	 *
	 * @since 1.2
	 */
	public static function register_pattern(): void {
		$bound = static function ( array $attributes ): string {
			$bindings = [];
			foreach ( $attributes as $attribute => $key ) {
				$bindings[ $attribute ] = [
					'source' => self::SOURCE,
					'args'   => [ 'key' => $key ],
				];
			}
			return (string) wp_json_encode( [ 'metadata' => [ 'bindings' => $bindings ] ], JSON_UNESCAPED_SLASHES );
		};

		$query = [
			'queryId' => 0,
			'query'   => [
				'perPage'  => 12,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => CentroPostType::POST_TYPE,
				'order'    => 'asc',
				'orderBy'  => 'title',
				'inherit'  => false,
			],
			'align'   => 'wide',
		];

		register_block_pattern(
			self::PATTERN,
			[
				'title'       => __( 'Listado de radios', 'radioedu-conecta' ),
				'description' => __( 'Radios escolares en cuadrícula, con su imagen, ubicación, edades y podcast.', 'radioedu-conecta' ),
				'categories'  => [ 'query' ],
				'blockTypes'  => [ 'core/query' ],
				'content'     => '<!-- wp:query ' . wp_json_encode( $query, JSON_UNESCAPED_SLASHES ) . ' -->'
					. '<div class="wp-block-query alignwide">'
					. '<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->'
					. '<!-- wp:image ' . $bound(
						[
							'url' => 'cover_url',
							'alt' => 'name',
						]
					) . ' --><figure class="wp-block-image"><img alt=""/></figure><!-- /wp:image -->'
					. '<!-- wp:post-title {"isLink":true,"level":3} /-->'
					. '<!-- wp:paragraph ' . $bound( [ 'content' => 'location' ] ) . ' --><p></p><!-- /wp:paragraph -->'
					. '<!-- wp:paragraph ' . $bound( [ 'content' => 'ages' ] ) . ' --><p></p><!-- /wp:paragraph -->'
					. '<!-- wp:buttons --><div class="wp-block-buttons">'
					. '<!-- wp:button ' . $bound( [ 'url' => 'podcast_url' ] ) . ' --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">'
					. esc_html__( 'Escuchar Podcast / Emisora', 'radioedu-conecta' )
					. '</a></div><!-- /wp:button -->'
					. '</div><!-- /wp:buttons -->'
					. '<!-- /wp:post-template -->'
					. '<!-- wp:query-pagination --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination -->'
					. '<!-- wp:query-no-results --><!-- wp:paragraph --><p>'
					. esc_html__( 'Todavía no hay radios publicadas.', 'radioedu-conecta' )
					. '</p><!-- /wp:paragraph --><!-- /wp:query-no-results -->'
					. '</div><!-- /wp:query -->',
			]
		);
	}

	/**
	 * Whether an inner block of a parsed block is bound to the source.
	 *
	 * @since 1.2
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return bool Whether one is.
	 */
	private static function binds_inner_block( array $block ): bool {
		foreach ( (array) ( $block['innerBlocks'] ?? [] ) as $inner ) {
			foreach ( (array) ( $inner['attrs']['metadata']['bindings'] ?? [] ) as $binding ) {
				if ( self::SOURCE === ( $binding['source'] ?? '' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Names of the terms of a station in a taxonomy, as plain text.
	 *
	 * @since 1.2
	 *
	 * @param int    $post_id  Station ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<int, string> Names.
	 */
	private static function term_names( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		return array_values( array_map( static fn( WP_Term $term ): string => DirectorioShortcode::plain_text( $term->name ), $terms ) );
	}
}
