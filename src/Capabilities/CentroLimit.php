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
use WP_Post;
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
	 * Per-request counts, keyed by "user:limit".
	 *
	 * @var array<string, int>
	 */
	private static array $counts = [];

	/**
	 * Registers the hooks.
	 *
	 * @since 1.1
	 */
	public static function register(): void {
		add_filter( 'user_has_cap', [ self::class, 'grant_create_cap' ], 10, 4 );
		add_action( 'clean_post_cache', [ self::class, 'flush_count_cache_for_post' ], 10, 2 );
		add_action( 'admin_notices', [ self::class, 'render_limit_notice' ] );
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
	 * Only on the centros list screen.
	 *
	 * @since 1.1
	 */
	public static function render_limit_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || 'edit-' . CentroPostType::POST_TYPE !== $screen->id
			|| ! current_user_can( 'edit_radioedu_centros' ) || ! self::has_reached_limit( get_current_user_id() ) ) {
			return;
		}

		$limit   = (int) self::get_limit();
		$message = 0 === $limit
			? __( 'Las radios las da de alta un administrador. Puedes editar las que tengas asignadas.', 'radioedu-conecta' )
			: sprintf(
				/* translators: %d: maximum number of radios per user. */
				_n(
					'Has alcanzado el máximo de %d radio por usuario. Para dar de alta otra, contacta con un administrador.',
					'Has alcanzado el máximo de %d radios por usuario. Para dar de alta otra, contacta con un administrador.',
					$limit,
					'radioedu-conecta'
				),
				$limit
			);

		wp_admin_notice( esc_html( $message ), [ 'type' => 'info' ] );
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
		$key = $user_id . ':' . $up_to;
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
