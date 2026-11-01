<?php
/**
 * Limit on the number of centros each coordinator can create.
 *
 * @package RadioEdu\Conecta\Capabilities
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Capabilities;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use stdClass;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Grants the "create centro" capability only while the user is under the configured limit.
 *
 * The radioedu_centro post type maps `create_posts` to CREATE_CAP, a capability no role stores:
 * it is granted on the fly to users who can edit centros. Users who manage every centro
 * (`edit_others_radioedu_centros` or `manage_radioedu`) have no limit. Because the check is a
 * capability, it covers the admin menu, post-new.php, the REST API and XML-RPC alike.
 */
final class CentroLimit {

	/**
	 * Capability that `create_posts` of the post type maps to.
	 */
	public const CREATE_CAP = 'create_radioedu_centros';

	/**
	 * Settings key of the limit: '' for no limit, or a whole number (0 or more).
	 */
	public const SETTING = 'max_centros_per_user';

	/**
	 * Statuses that count towards the limit (trash and auto-drafts do not).
	 */
	private const COUNTED_STATUSES = [ 'publish', 'future', 'draft', 'pending', 'private' ];

	/**
	 * Per-request counts, keyed by "blog:user:limit": in Multisite each site has its own centros.
	 *
	 * @var array<string, int>
	 */
	private static array $counts = [];

	/**
	 * Seconds a save waits for another request that holds the same author's quota lock.
	 */
	private const QUOTA_LOCK_WAIT = 10;

	/**
	 * Places reserved in this request, by author: IDs of the centros about to count (0 for a new one).
	 *
	 * @var array<int, array<int, int>>
	 */
	private static array $reservations = [];

	/**
	 * Authors whose quota lock this request holds.
	 *
	 * @var array<int, true>
	 */
	private static array $quota_locks = [];

	/**
	 * Registers the hooks.
	 *
	 * @since 1.1
	 */
	public static function register(): void {
		add_filter( 'user_has_cap', [ self::class, 'grant_create_cap' ], 10, 4 );
		add_filter( 'rest_pre_insert_' . CentroPostType::POST_TYPE, [ self::class, 'check_rest_status_change' ], 100, 2 );
		add_filter( 'pre_untrash_post', [ self::class, 'check_untrash' ], 10, 2 );
		add_filter( 'wp_insert_post_data', [ self::class, 'keep_uncounted_status' ], 10, 2 );
		add_action( 'wp_insert_post', [ self::class, 'release_reservation' ], 10, 3 );
		add_action( 'shutdown', [ self::class, 'release_all_quota_locks' ] );
		add_action( 'clean_post_cache', [ self::class, 'flush_count_cache_for_post' ], 10, 2 );
		add_action( 'admin_notices', [ self::class, 'render_limit_notice' ] );
	}

	/**
	 * Applies the limit when a REST save makes a centro count: restoring it from the trash or
	 * saving an auto-draft opened before the limit was reached.
	 *
	 * The capability only guards creation, so without this a coordinator could trash a centro,
	 * create another one and restore the first.
	 *
	 * @since 1.1
	 *
	 * @param stdClass|WP_Error $prepared_post Post about to be saved.
	 * @param WP_REST_Request   $request       REST request (unused).
	 * @return stdClass|WP_Error The post, or an error when the change would exceed the limit.
	 */
	public static function check_rest_status_change( stdClass|WP_Error $prepared_post, WP_REST_Request $request ): stdClass|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature of the rest_pre_insert_{$post_type} filter.
		if ( is_wp_error( $prepared_post ) || empty( $prepared_post->post_status ) ) {
			return $prepared_post;
		}

		$post_id    = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
		$old_status = $post_id > 0 ? (string) get_post_status( $post_id ) : '';
		if ( ! self::status_change_exceeds_limit( $post_id, $old_status, (string) $prepared_post->post_status ) ) {
			return $prepared_post;
		}

		return new WP_Error( 'radioedu_centro_limit', self::get_limit_message(), [ 'status' => 403 ] );
	}

	/**
	 * Applies the limit when a centro is restored from the trash outside the REST API.
	 *
	 * The admin list then explains why with the limit notice.
	 *
	 * @since 1.1
	 *
	 * @param bool|null $untrash Whether to go ahead; null lets WordPress decide.
	 * @param WP_Post   $post    Post being restored.
	 * @return bool|null False to keep the centro in the trash, otherwise $untrash.
	 */
	public static function check_untrash( ?bool $untrash, WP_Post $post ): ?bool {
		if ( null !== $untrash || CentroPostType::POST_TYPE !== $post->post_type ) {
			return $untrash;
		}

		// wp_untrash_post() restores to draft, or to the previous status through a filter; both count.
		return self::status_change_exceeds_limit( $post->ID, 'trash', 'draft' ) ? false : $untrash;
	}

	/**
	 * Applies the limit to every other save that would make an existing centro count.
	 *
	 * Covers the classic editor, Quick Edit, XML-RPC and code calling wp_update_post(), which
	 * reach neither the REST filter nor pre_untrash_post. The centro keeps its current status
	 * (auto-draft or trash), so what was typed is saved but does not count, and the edit screen
	 * explains why. New centros are not handled here: creating one needs CREATE_CAP.
	 *
	 * @since 1.1
	 *
	 * @param array<string, mixed> $data    Sanitized post data about to be saved.
	 * @param array<string, mixed> $postarr Post data passed to wp_insert_post().
	 * @return array<string, mixed> The data, with the status kept when the change would exceed the limit.
	 */
	public static function keep_uncounted_status( array $data, array $postarr ): array {
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $post_id <= 0 || CentroPostType::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		$old_status = (string) get_post_status( $post_id );
		if ( self::status_change_exceeds_limit( $post_id, $old_status, (string) ( $data['post_status'] ?? '' ) ) ) {
			$data['post_status'] = $old_status;
		}

		return $data;
	}

	/**
	 * Returns the configured limit.
	 *
	 * @since 1.1
	 *
	 * @return int|null Maximum number of centros per user, or null for no limit.
	 */
	public static function get_limit(): ?int {
		$value = Settings::get( self::SETTING, '' );

		return is_scalar( $value ) && ctype_digit( (string) $value ) ? (int) $value : null;
	}

	/**
	 * Grants CREATE_CAP to users who can edit centros and are under the limit.
	 *
	 * Reads the user's other capabilities from $allcaps instead of calling user_can(), which
	 * would run this filter again.
	 *
	 * @since 1.1
	 *
	 * @param array<string, bool> $allcaps Capabilities of the user.
	 * @param array<int, string>  $caps    Primitive capabilities being checked.
	 * @param array<int, mixed>   $args    Arguments of the capability check.
	 * @param WP_User             $user    User being checked.
	 * @return array<string, bool> Capabilities, with CREATE_CAP set.
	 */
	public static function grant_create_cap( array $allcaps, array $caps, array $args, WP_User $user ): array {
		if ( ! in_array( self::CREATE_CAP, $caps, true ) ) {
			return $allcaps;
		}

		$manages_all                 = ! empty( $allcaps['edit_others_radioedu_centros'] ) || ! empty( $allcaps['manage_radioedu'] );
		$allcaps[ self::CREATE_CAP ] = ! empty( $allcaps['edit_radioedu_centros'] ) && ( $manages_all || ! self::is_over_limit( $user->ID ) );

		return $allcaps;
	}

	/**
	 * Tells whether a user can create no more centros because of the limit.
	 *
	 * @since 1.1
	 *
	 * @param int $user_id User ID.
	 * @return bool True when a limit applies to the user and has been reached.
	 */
	public static function has_reached_limit( int $user_id ): bool {
		if ( user_can( $user_id, 'edit_others_radioedu_centros' ) || user_can( $user_id, 'manage_radioedu' ) ) {
			return false;
		}

		return self::is_over_limit( $user_id );
	}

	/**
	 * Shows coordinators at the limit why they cannot add another centro.
	 *
	 * On the centros list, and on the edit screen of a centro that does not count yet (an
	 * auto-draft), which keep_uncounted_status() will not let them publish.
	 *
	 * @since 1.1
	 */
	public static function render_limit_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || CentroPostType::POST_TYPE !== $screen->post_type
			|| ! current_user_can( 'edit_radioedu_centros' ) || ! self::has_reached_limit( get_current_user_id() ) ) {
			return;
		}

		$post = 'post' === $screen->base ? get_post() : null;
		if ( 'edit' !== $screen->base && ! ( $post instanceof WP_Post && ! in_array( $post->post_status, self::COUNTED_STATUSES, true ) ) ) {
			return;
		}

		wp_admin_notice( esc_html( self::get_limit_message() ), [ 'type' => 'edit' === $screen->base ? 'info' : 'warning' ] );
	}

	/**
	 * Explains the limit to a coordinator who has reached it.
	 *
	 * @since 1.1
	 *
	 * @return string Plain-text message.
	 */
	private static function get_limit_message(): string {
		$limit = (int) self::get_limit();
		if ( 0 === $limit ) {
			return __( 'Las radios las da de alta un administrador. Puedes editar las que tengas asignadas.', 'radioedu-conecta' );
		}

		return sprintf(
			/* translators: %d: maximum number of radios per user. */
			_n(
				'Has alcanzado el máximo de %d radio por usuario. Para dar de alta otra, contacta con un administrador.',
				'Has alcanzado el máximo de %d radios por usuario. Para dar de alta otra, contacta con un administrador.',
				$limit,
				'radioedu-conecta'
			),
			$limit
		);
	}

	/**
	 * Tells whether moving a centro to a new status would take a limited user over the limit.
	 *
	 * Only changes from a status that does not count (trash, auto-draft, a new post) to one that
	 * counts matter. The centro itself is not counted yet, so it needs no special handling.
	 *
	 * @param int    $post_id    Centro ID, or 0 for a new one.
	 * @param string $old_status Current status, or '' for a new centro.
	 * @param string $new_status Status being saved.
	 * @return bool True when the change must be refused.
	 */
	private static function status_change_exceeds_limit( int $post_id, string $old_status, string $new_status ): bool {
		if ( in_array( $old_status, self::COUNTED_STATUSES, true ) || ! in_array( $new_status, self::COUNTED_STATUSES, true ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		$author_id = $post_id > 0 ? (int) get_post_field( 'post_author', $post_id ) : $user_id;

		if ( user_can( $user_id, 'edit_others_radioedu_centros' ) || user_can( $user_id, 'manage_radioedu' ) ) {
			return false;
		}

		$limit = self::get_limit();
		if ( null === $limit ) {
			return false;
		}

		// The same save may be checked twice (REST then wp_insert_post_data, untrash then the update).
		if ( $post_id > 0 && in_array( $post_id, self::$reservations[ $author_id ] ?? [], true ) ) {
			return false;
		}

		// Check and write under the author's lock, so two requests at once cannot both take the
		// last place. If the other request does not finish in time, refuse rather than risk it.
		if ( ! self::lock_quota( $author_id ) ) {
			return true;
		}

		self::flush_count_cache();
		$taken = self::count_user_centros( $author_id, max( 1, $limit ) ) + count( self::$reservations[ $author_id ] ?? [] );
		if ( 0 === $limit || $taken >= $limit ) {
			if ( empty( self::$reservations[ $author_id ] ) ) {
				self::unlock_quota( $author_id );
			}
			return true;
		}

		self::$reservations[ $author_id ][] = $post_id;
		return false;
	}

	/**
	 * Frees the place reserved for a centro once it is saved, and the author's lock with the last one.
	 *
	 * @since 1.1
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Saved post.
	 * @param bool    $update  Whether an existing post was updated.
	 */
	public static function release_reservation( int $post_id, WP_Post $post, bool $update ): void {
		$author_id = (int) $post->post_author;
		if ( CentroPostType::POST_TYPE !== $post->post_type || empty( self::$reservations[ $author_id ] ) ) {
			return;
		}

		// A new centro was reserved as 0, before it had an ID.
		$key = array_search( $update ? $post_id : 0, self::$reservations[ $author_id ], true );
		if ( false !== $key ) {
			unset( self::$reservations[ $author_id ][ $key ] );
		}
		if ( empty( self::$reservations[ $author_id ] ) ) {
			unset( self::$reservations[ $author_id ] );
			self::unlock_quota( $author_id );
		}
	}

	/**
	 * Releases every quota lock this request still holds, for example after a failed save.
	 *
	 * @since 1.1
	 */
	public static function release_all_quota_locks(): void {
		foreach ( array_keys( self::$quota_locks ) as $author_id ) {
			self::unlock_quota( $author_id );
		}
		self::$reservations = [];
	}

	/**
	 * Takes the author's quota lock, waiting for another request that holds it.
	 *
	 * A MySQL/MariaDB named lock (GET_LOCK) belongs to the database connection, and the server
	 * releases it if the request dies. A request that already holds it keeps it.
	 *
	 * @param int $author_id Author whose centros are counted.
	 * @return bool True when this request holds the lock.
	 */
	private static function lock_quota( int $author_id ): bool {
		global $wpdb;
		if ( isset( self::$quota_locks[ $author_id ] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock tied to this connection; no core API provides one.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', self::quota_lock_name( $author_id ), self::QUOTA_LOCK_WAIT ) ) ) {
			return false;
		}

		self::$quota_locks[ $author_id ] = true;
		return true;
	}

	/**
	 * Releases the author's quota lock, if this request holds it.
	 *
	 * @param int $author_id Author whose centros are counted.
	 */
	private static function unlock_quota( int $author_id ): void {
		global $wpdb;
		if ( ! isset( self::$quota_locks[ $author_id ] ) ) {
			return;
		}

		unset( self::$quota_locks[ $author_id ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Releases the named lock taken in lock_quota().
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::quota_lock_name( $author_id ) ) );
	}

	/**
	 * Returns the name of the quota lock of an author on the current site.
	 *
	 * Named locks are shared by every database on the server, and each site has its own limit, so
	 * the name hashes the database and the site's table prefix. It stays under 64 characters.
	 *
	 * @since 1.1
	 *
	 * @param int $author_id Author whose centros are counted.
	 * @return string Lock name.
	 */
	public static function quota_lock_name( int $author_id ): string {
		global $wpdb;
		$database = defined( 'DB_NAME' ) ? (string) constant( 'DB_NAME' ) : '';

		return 'radioedu_conecta_quota_' . substr( md5( $database . '|' . $wpdb->prefix ), 0, 12 ) . '_' . $author_id;
	}

	/**
	 * Forgets the cached counts when a centro is created, changed, trashed or deleted.
	 *
	 * @since 1.1
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function flush_count_cache_for_post( int $post_id, WP_Post $post ): void {
		if ( CentroPostType::POST_TYPE === $post->post_type ) {
			self::flush_count_cache();
		}
	}

	/**
	 * Forgets every cached count.
	 *
	 * @since 1.1
	 */
	public static function flush_count_cache(): void {
		self::$counts = [];
	}

	/**
	 * Checks the limit without looking at the user's capabilities.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when there is a limit and the user's centros reach it.
	 */
	private static function is_over_limit( int $user_id ): bool {
		$limit = self::get_limit();
		if ( null === $limit ) {
			return false;
		}

		return 0 === $limit || self::count_user_centros( $user_id, $limit ) >= $limit;
	}

	/**
	 * Counts the user's centros, stopping at $up_to.
	 *
	 * @param int $user_id User ID.
	 * @param int $up_to   Highest count that matters (the limit).
	 * @return int Number of centros authored by the user, at most $up_to.
	 */
	private static function count_user_centros( int $user_id, int $up_to ): int {
		$key = get_current_blog_id() . ':' . $user_id . ':' . $up_to;
		if ( ! isset( self::$counts[ $key ] ) ) {
			self::$counts[ $key ] = count(
				get_posts(
					[
						'post_type'      => CentroPostType::POST_TYPE,
						'post_status'    => self::COUNTED_STATUSES,
						'author'         => $user_id,
						'posts_per_page' => $up_to,
						'fields'         => 'ids',
						'orderby'        => 'none',
						'no_found_rows'  => true,
					]
				)
			);
		}

		return self::$counts[ $key ];
	}
}
