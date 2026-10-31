<?php
/**
 * Creates and updates centro fichas from the sites of the network.
 *
 * @package RadioEdu\Conecta\Network
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Network;

use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Support\BlockMarkup;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Network sync: one ficha on the main site (the directory) for every other site of the network.
 *
 * The site name, URL and icon refresh the ficha on every sync. The tagline (description), admin
 * email (coordinator email) and site administrator (ficha author) are only used when the ficha
 * is created, so what coordinators edit afterwards is never overwritten. A ficha created by
 * hand with the same website is reused instead of duplicated. Fichas whose site is gone are
 * kept and reported. When the "give the Author role" option is on, ficha authors who lack the
 * Author role (or a higher one) on the main site get it, so they can edit their ficha.
 *
 * Every method must run on the main site, as the REST endpoint guarantees.
 */
final class NetworkSync {

	/**
	 * Meta key with the ID of the source site of a ficha.
	 */
	public const SOURCE_META = '_radioedu_source_blog_id';

	/**
	 * Sites processed per request.
	 */
	public const DEFAULT_BATCH_SIZE = 20;

	/**
	 * Network option with the last "give the Author role" choice ('1' or '0').
	 */
	public const GRANT_AUTHOR_OPTION = 'radioedu_conecta_sync_grant_author';

	/**
	 * Most fichas checked for a missing source site.
	 */
	private const MAX_ORPHAN_CHECK = 1000;

	/**
	 * Returns the number of sites processed per request.
	 *
	 * @since 1.1
	 *
	 * @return int Batch size, between 1 and 100.
	 */
	public static function get_batch_size(): int {
		/**
		 * Filters the number of sites the network sync processes per request.
		 *
		 * @since 1.1
		 *
		 * @param int $size Sites per request. Clamped to 1–100.
		 */
		$size = (int) apply_filters( 'radioedu_conecta_network_sync_batch_size', self::DEFAULT_BATCH_SIZE );

		return max( 1, min( 100, $size ) );
	}

	/**
	 * Counts the sites the sync reads: every site of the network but the main one.
	 *
	 * Archived, spam and deleted sites are skipped.
	 *
	 * @since 1.1
	 *
	 * @return int Number of source sites.
	 */
	public static function count_source_sites(): int {
		return (int) get_sites( array_merge( self::get_source_site_query(), [ 'count' => true ] ) );
	}

	/**
	 * Tells whether the "give the Author role" checkbox starts checked (the last choice, or yes).
	 *
	 * @since 1.1
	 *
	 * @return bool True to give site administrators the Author role on the main site.
	 */
	public static function get_grant_author_role(): bool {
		return '0' !== (string) get_site_option( self::GRANT_AUTHOR_OPTION, '1' );
	}

	/**
	 * Remembers the "give the Author role" choice for the next sync.
	 *
	 * @since 1.1
	 *
	 * @param bool $grant Whether to give the Author role.
	 */
	public static function set_grant_author_role( bool $grant ): void {
		update_site_option( self::GRANT_AUTHOR_OPTION, $grant ? '1' : '0' );
	}

	/**
	 * Syncs one batch of sites.
	 *
	 * @since 1.1
	 *
	 * @param int  $offset            Number of source sites already processed.
	 * @param bool $grant_author_role Whether ficha authors get the Author role on the main site.
	 * @return array{processed: int, total: int, next_offset: int, done: bool, created: int, updated: int, reused: int, skipped: int, authors_added: int, synced: list<array{id: int, title: string, blog_id: int, site_url: string, outcome: string, author_added: bool, edit_url: string}>, errors: list<array{blog_id: int, message: string}>, missing: list<array{id: int, title: string, blog_id: int, edit_url: string}>} Batch result. `synced` lists the fichas of this batch; `missing` is only filled in the last batch.
	 */
	public static function sync_batch( int $offset, bool $grant_author_role = true ): array {
		$size     = self::get_batch_size();
		$site_ids = get_sites(
			array_merge(
				self::get_source_site_query(),
				[
					'fields'  => 'ids',
					'number'  => $size,
					'offset'  => max( 0, $offset ),
					'orderby' => 'id',
					'order'   => 'ASC',
				]
			)
		);

		$counts        = [
			'created' => 0,
			'updated' => 0,
			'reused'  => 0,
			'skipped' => 0,
		];
		$authors_added = 0;
		$synced        = [];
		$errors        = [];
		foreach ( $site_ids as $blog_id ) {
			$result = self::sync_site( (int) $blog_id, $grant_author_role );
			if ( $result instanceof WP_Error ) {
				$errors[] = [
					'blog_id' => (int) $blog_id,
					'message' => $result->get_error_message(),
				];
				continue;
			}
			++$counts[ $result['outcome'] ];
			$authors_added += $result['author_added'] ? 1 : 0;
			$synced[]       = [
				'id'           => $result['post_id'],
				'title'        => html_entity_decode( get_the_title( $result['post_id'] ), ENT_QUOTES, 'UTF-8' ),
				'blog_id'      => (int) $blog_id,
				'site_url'     => $result['site_url'],
				'outcome'      => $result['outcome'],
				'author_added' => $result['author_added'],
				'edit_url'     => (string) get_edit_post_link( $result['post_id'], 'raw' ),
			];
		}

		$total       = self::count_source_sites();
		$next_offset = max( 0, $offset ) + count( $site_ids );
		$done        = count( $site_ids ) < $size || $next_offset >= $total;

		return [
			'processed'     => count( $site_ids ),
			'total'         => $total,
			'next_offset'   => $next_offset,
			'done'          => $done,
			'created'       => $counts['created'],
			'updated'       => $counts['updated'],
			'reused'        => $counts['reused'],
			'skipped'       => $counts['skipped'],
			'authors_added' => $authors_added,
			'synced'        => $synced,
			'errors'        => $errors,
			'missing'       => $done ? self::find_orphaned_fichas() : [],
		];
	}

	/**
	 * Creates, updates or reuses the ficha of one site.
	 *
	 * @since 1.1
	 *
	 * @param int  $blog_id           Source site ID.
	 * @param bool $grant_author_role Whether the ficha author gets the Author role on the main site.
	 * @return array{post_id: int, site_url: string, outcome: 'created'|'updated'|'reused'|'skipped', author_added: bool}|WP_Error The ficha, the site URL, what happened to the ficha, and whether its author got the Author role.
	 */
	public static function sync_site( int $blog_id, bool $grant_author_role = true ): array|WP_Error {
		$data = self::read_site( $blog_id );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		[ $post_id, $outcome ] = self::find_ficha( $blog_id, $data['url'] );

		// A ficha an administrator sent to the trash is neither touched nor created again.
		if ( 'skipped' === $outcome ) {
			return [
				'post_id'      => $post_id,
				'site_url'     => $data['url'],
				'outcome'      => $outcome,
				'author_added' => false,
			];
		}

		if ( 0 === $post_id ) {
			$post_id = wp_insert_post(
				[
					'post_type'    => CentroPostType::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $data['name'],
					'post_excerpt' => $data['tagline'],
					'post_content' => '' !== $data['tagline'] ? BlockMarkup::paragraphs( $data['tagline'] ) : '',
					'post_author'  => $data['admin_id'] > 0 ? $data['admin_id'] : get_current_user_id(),
				],
				true
			);
			if ( $post_id instanceof WP_Error ) {
				return $post_id;
			}
			if ( is_email( $data['admin_email'] ) ) {
				update_post_meta( $post_id, '_radioedu_coordinador_email', $data['admin_email'] );
			}
			$outcome = 'created';
		} else {
			$updated = wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $data['name'],
				],
				true
			);
			if ( $updated instanceof WP_Error ) {
				return $updated;
			}
		}

		update_post_meta( $post_id, self::SOURCE_META, $blog_id );
		update_post_meta( $post_id, '_radioedu_web_centro', $data['url'] );
		update_post_meta( $post_id, '_radioedu_logo_url', $data['icon'] );

		return [
			'post_id'      => $post_id,
			'site_url'     => $data['url'],
			'outcome'      => $outcome,
			'author_added' => $grant_author_role && self::grant_author_access( (int) get_post_field( 'post_author', $post_id ) ),
		];
	}

	/**
	 * Lists fichas whose source site no longer exists or is archived, spam or deleted.
	 *
	 * They are kept: an administrator decides what to do with them.
	 *
	 * @since 1.1
	 *
	 * @return list<array{id: int, title: string, blog_id: int, edit_url: string}> Orphaned fichas.
	 */
	public static function find_orphaned_fichas(): array {
		$ids = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => self::MAX_ORPHAN_CHECK,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded admin-only lookup of synced fichas.
				'meta_key'       => self::SOURCE_META,
			]
		);

		$orphans = [];
		foreach ( $ids as $post_id ) {
			$blog_id = (int) get_post_meta( (int) $post_id, self::SOURCE_META, true );
			$site    = $blog_id > 0 ? get_site( $blog_id ) : null;
			if ( null !== $site && '1' !== (string) $site->archived && '1' !== (string) $site->spam && '1' !== (string) $site->deleted ) {
				continue;
			}
			$orphans[] = [
				'id'       => (int) $post_id,
				'title'    => html_entity_decode( get_the_title( (int) $post_id ), ENT_QUOTES, 'UTF-8' ),
				'blog_id'  => $blog_id,
				'edit_url' => (string) get_edit_post_link( (int) $post_id, 'raw' ),
			];
		}

		return $orphans;
	}

	/**
	 * Query arguments of the source sites.
	 *
	 * @return array<string, mixed> get_sites() arguments.
	 */
	private static function get_source_site_query(): array {
		return [
			'network_id'   => get_current_network_id(),
			'site__not_in' => [ get_main_site_id() ],
			'archived'     => 0,
			'spam'         => 0,
			'deleted'      => 0,
		];
	}

	/**
	 * Reads the data the ficha takes from a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return array{name: string, tagline: string, url: string, icon: string, admin_email: string, admin_id: int}|WP_Error Site data.
	 */
	private static function read_site( int $blog_id ): array|WP_Error {
		if ( null === get_site( $blog_id ) ) {
			return new WP_Error( 'radioedu_conecta_site_not_found', __( 'El sitio no existe.', 'radioedu-conecta' ) );
		}

		switch_to_blog( $blog_id );
		try {
			$data = [
				'name'        => (string) get_option( 'blogname' ),
				'tagline'     => (string) get_option( 'blogdescription' ),
				'url'         => home_url( '/' ),
				'icon'        => (string) get_site_icon_url( 512 ),
				'admin_email' => sanitize_email( (string) get_option( 'admin_email' ) ),
			];
		} finally {
			restore_current_blog();
		}

		if ( '' === trim( $data['name'] ) ) {
			$data['name'] = $data['url'];
		}
		$data['admin_id'] = self::find_site_admin( $blog_id, $data['admin_email'] );

		return $data;
	}

	/**
	 * Finds the administrator of a site: the one with the site's admin email, or the first one.
	 *
	 * The role identifies who runs the site; it is not used to grant anything.
	 *
	 * @param int    $blog_id     Site ID.
	 * @param string $admin_email Admin email of the site.
	 * @return int User ID, or 0 when the site has no administrator.
	 */
	private static function find_site_admin( int $blog_id, string $admin_email ): int {
		$admins = get_users(
			[
				'blog_id' => $blog_id,
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 50,
			]
		);

		foreach ( $admins as $admin ) {
			if ( '' !== $admin_email && strtolower( $admin->user_email ) === strtolower( $admin_email ) ) {
				return (int) $admin->ID;
			}
		}

		return isset( $admins[0] ) ? (int) $admins[0]->ID : 0;
	}

	/**
	 * Finds the ficha of a site: the one synced from it, or a hand-made one with its website.
	 *
	 * @param int    $blog_id Site ID.
	 * @param string $url     Site URL.
	 * @return array{0: int, 1: 'created'|'updated'|'reused'|'skipped'} Ficha ID (0 when there is none) and what the sync does.
	 */
	private static function find_ficha( int $blog_id, string $url ): array {
		$base = [
			'post_type'      => CentroPostType::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		$synced = get_posts(
			[
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Single-row admin-only lookup.
				'meta_query'  => [
					[
						'key'   => self::SOURCE_META,
						'value' => $blog_id,
						'type'  => 'NUMERIC',
					],
				],
			] + $base
		);
		if ( ! empty( $synced ) ) {
			$post_id    = (int) $synced[0];
			$is_trashed = 'trash' === get_post_status( $post_id );

			return [ $post_id, $is_trashed ? 'skipped' : 'updated' ];
		}

		$manual = get_posts(
			$base + [
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Single-row admin-only lookup.
				'meta_query' => [
					[
						'key'     => '_radioedu_web_centro',
						'value'   => self::get_url_variants( $url ),
						'compare' => 'IN',
					],
					[
						'key'     => self::SOURCE_META,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);
		if ( ! empty( $manual ) ) {
			return [ (int) $manual[0], 'reused' ];
		}

		return [ 0, 'created' ];
	}

	/**
	 * Returns the spellings of a URL a hand-made ficha may use (http or https, trailing slash).
	 *
	 * @param string $url Site URL.
	 * @return list<string> URL variants.
	 */
	private static function get_url_variants( string $url ): array {
		$without_scheme = (string) preg_replace( '#^https?://#i', '', untrailingslashit( $url ) );
		$variants       = [];
		foreach ( [ 'https://', 'http://' ] as $scheme ) {
			$variants[] = $scheme . $without_scheme;
			$variants[] = $scheme . $without_scheme . '/';
		}

		return $variants;
	}

	/**
	 * Lets the ficha author edit it on the main site.
	 *
	 * Adds them to the main site as Author when they are not a member, and adds the Author role
	 * when they are a member without it or a higher role (no permission to edit centros). Users
	 * who are already Author, Editor or Administrator are left as they are.
	 *
	 * @param int $user_id User ID (0 for none).
	 * @return bool True when the user got the Author role.
	 */
	private static function grant_author_access( int $user_id ): bool {
		if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
			return false;
		}

		$main_site = get_main_site_id();
		if ( ! is_user_member_of_blog( $user_id, $main_site ) ) {
			return true === add_user_to_blog( $main_site, $user_id, 'author' );
		}

		$user = new WP_User( $user_id );
		if ( $user->has_cap( 'edit_radioedu_centros' ) ) {
			return false;
		}
		$user->add_role( 'author' );

		return true;
	}
}
