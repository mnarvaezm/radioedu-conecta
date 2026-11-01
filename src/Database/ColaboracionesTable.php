<?php
/**
 * Gestor de la tabla personalizada de colaboraciones.
 *
 * @package RadioEdu\Conecta\Database
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Database;

use RadioEdu\Conecta\PostTypes\CentroPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Clase encargada de la creación, actualización y gestión de la tabla de colaboraciones.
 */
class ColaboracionesTable {

	/**
	 * Versión del esquema de base de datos y capacidades.
	 */
	public const DB_VERSION = '1.0.3';

	/**
	 * Nombre base de la tabla (sin prefijo de WP).
	 */
	public const TABLE_NAME = 'radioedu_colaboraciones';

	/**
	 * Estados válidos de respuesta a una propuesta.
	 */
	public const RESPONSE_STATUSES = [ 'aceptada', 'rechazada' ];

	/**
	 * Longitudes máximas de los campos de texto (coinciden con el esquema o limitan los TEXT).
	 */
	public const MAX_IDEA_LENGTH      = 5000;
	public const MAX_RESPUESTA_LENGTH = 2000;
	public const MAX_EDADES_LENGTH    = 100;
	public const MAX_IDIOMAS_LENGTH   = 100;
	public const MAX_FECHAS_LENGTH    = 150;

	/**
	 * Columns the plugin reads or writes. The schema version is saved only when all of them exist.
	 */
	/**
	 * Indexes the queries rely on: the version is saved only when they exist too.
	 */
	private const REQUIRED_INDEXES = [
		'PRIMARY',
		'centro_emisor_id',
		'centro_receptor_id',
		'usuario_emisor_id',
		'usuario_respuesta_id',
		'estado',
	];

	private const REQUIRED_COLUMNS = [
		'id',
		'centro_emisor_id',
		'centro_receptor_id',
		'usuario_emisor_id',
		'idea_proyecto',
		'curso_edades',
		'idiomas',
		'fechas_aprox',
		'estado',
		'respuesta_mensaje',
		'usuario_respuesta_id',
		'created_at',
		'updated_at',
	];

	/**
	 * Obtiene el nombre completo de la tabla con el prefijo de la BD actual.
	 *
	 * @return string Nombre con prefijo.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Crea o migra la tabla personalizada utilizando dbDelta().
	 *
	 * @since 1.1 Returns whether the schema is complete, and only then saves its version.
	 *
	 * @return bool True when the table has every column the plugin uses.
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// Sintaxis estricta de dbDelta:
		// - 2 espacios tras PRIMARY KEY.
		// - Cada columna en su propia línea.
		// - KEY en lugar de INDEX.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			centro_emisor_id bigint(20) unsigned NOT NULL,
			centro_receptor_id bigint(20) unsigned NOT NULL,
			usuario_emisor_id bigint(20) unsigned NOT NULL,
			idea_proyecto text NOT NULL,
			curso_edades varchar(100) NOT NULL DEFAULT '',
			idiomas varchar(100) NOT NULL DEFAULT '',
			fechas_aprox varchar(150) NOT NULL DEFAULT '',
			estado varchar(20) NOT NULL DEFAULT 'pendiente',
			respuesta_mensaje text DEFAULT NULL,
			usuario_respuesta_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY centro_emisor_id (centro_emisor_id),
			KEY centro_receptor_id (centro_receptor_id),
			KEY usuario_emisor_id (usuario_emisor_id),
			KEY usuario_respuesta_id (usuario_respuesta_id),
			KEY estado (estado)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// dbDelta() reports nothing when the database refuses the DDL (for example, without the
		// CREATE or ALTER privilege). Saving the version anyway would stop every later retry.
		if ( ! self::has_current_schema() ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only with WP_DEBUG_LOG enabled; it names the table and holds no personal data.
				error_log( sprintf( 'RadioEdu Conecta: the %s table could not be created or updated.', $table_name ) );
			}
			return false;
		}

		update_option( 'radioedu_conecta_db_version', self::DB_VERSION );
		return true;
	}

	/**
	 * Tells whether the table exists with every column and index the plugin uses.
	 *
	 * Uses SHOW COLUMNS and SHOW INDEX rather than SHOW TABLES, which does not list temporary
	 * tables. A missing index is checked too: dbDelta() adds a column and its index in separate
	 * statements, and the second one can fail on its own.
	 *
	 * @since 1.1
	 *
	 * @return bool True when no required column or index is missing.
	 */
	public static function has_current_schema(): bool {
		global $wpdb;

		// A missing table is an expected answer here, not an error to print.
		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check right after dbDelta(); a cached answer would hide the failure.
		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::get_table_name() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Same check for the indexes; the third column of SHOW INDEX is the index name.
		$indexes = $wpdb->get_col( $wpdb->prepare( 'SHOW INDEX FROM %i', self::get_table_name() ), 2 );
		$wpdb->suppress_errors( $suppress );

		return [] === array_diff( self::REQUIRED_COLUMNS, $columns ) && [] === array_diff( self::REQUIRED_INDEXES, $indexes );
	}

	/**
	 * Elimina la tabla de la base de datos (utilizado en tests o uninstall).
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table teardown; no core API exists.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
		delete_option( 'radioedu_conecta_db_version' );
	}

	/**
	 * Añade la tabla del plugin a la lista de tablas que WordPress borra al eliminar un sitio de la red.
	 *
	 * @param array<int|string, string> $tables Tablas del sitio que se van a eliminar.
	 * @return array<int|string, string>
	 */
	public static function add_to_dropped_tables( array $tables ): array {
		$tables[] = self::get_table_name();
		return $tables;
	}

	/**
	 * Obtiene una propuesta de colaboración por su ID.
	 *
	 * @param int $id ID de la colaboración.
	 * @return object|null Fila de la tabla o null si no existe.
	 */
	public static function get_by_id( int $id ): ?object {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; state changes on every response, so it is not cached.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::get_table_name(), $id ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Lista las propuestas visibles para un usuario: las que envió y las de los centros que gestiona.
	 *
	 * @since 1.1 Pages through the results with $offset, newest first with a stable order, and finds
	 *            the user's centros in SQL instead of taking a capped list of IDs.
	 *
	 * @param int  $user_id ID del usuario.
	 * @param bool $all     Si es true devuelve todas las propuestas (gestores de todos los centros).
	 * @param int  $limit   Número máximo de filas.
	 * @param int  $offset  Rows to skip, for pagination.
	 * @return array<int, object>
	 */
	public static function get_for_user( int $user_id, bool $all = false, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;
		[ $where, $args ] = self::user_scope( $user_id, $all );
		$query_args       = array_merge( [ self::get_table_name() ], $args, [ max( 1, min( 500, $limit ) ), max( 0, $offset ) ] );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is fixed SQL with placeholders only, built in user_scope(); every value goes through prepare(). Listings must reflect the latest state.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", ...$query_args ) );
		// phpcs:enable

		return is_array( $rows ) ? array_values( array_filter( $rows, 'is_object' ) ) : [];
	}

	/**
	 * Counts the proposals that get_for_user() can list for a user.
	 *
	 * @since 1.1
	 *
	 * @param int  $user_id ID del usuario.
	 * @param bool $all     Si es true cuenta todas las propuestas (gestores de todos los centros).
	 * @return int Number of proposals.
	 */
	public static function count_for_user( int $user_id, bool $all = false ): int {
		global $wpdb;
		[ $where, $args ] = self::user_scope( $user_id, $all );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is fixed SQL with placeholders only, built in user_scope(); every value goes through prepare(). The count must match the listing.
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", ...array_merge( [ self::get_table_name() ], $args ) ) );
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Builds the WHERE clause that limits proposals to those a user may see.
	 *
	 * Matches ColaboracionesController::user_can_manage_center(): the proposals the user sent and
	 * those of every centro they author or coordinate (by email, compared without case by the
	 * column collation), whatever its status.
	 *
	 * @param int  $user_id ID del usuario.
	 * @param bool $all     Whether the user sees every proposal.
	 * @return array{0: string, 1: array<int, int|string>} Clause with placeholders only, and its values.
	 */
	private static function user_scope( int $user_id, bool $all ): array {
		global $wpdb;

		if ( $all ) {
			return [ '1 = 1', [] ];
		}

		$authored = 'SELECT ID FROM %i WHERE post_type = %s AND post_author = %d';
		$where    = "usuario_emisor_id = %d OR centro_emisor_id IN ({$authored}) OR centro_receptor_id IN ({$authored})";
		$args     = [ $user_id, $wpdb->posts, CentroPostType::POST_TYPE, $user_id, $wpdb->posts, CentroPostType::POST_TYPE, $user_id ];

		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( $user && '' !== $user->user_email ) {
			$coordinated = "SELECT post_id FROM %i WHERE meta_key = '_radioedu_coordinador_email' AND meta_value = %s";
			$where      .= " OR centro_emisor_id IN ({$coordinated}) OR centro_receptor_id IN ({$coordinated})";
			array_push( $args, $wpdb->postmeta, $user->user_email, $wpdb->postmeta, $user->user_email );
		}

		return [ $where, $args ];
	}

	/**
	 * Resuelve una propuesta pendiente (aceptada o rechazada).
	 *
	 * La condición `estado = 'pendiente'` en el WHERE garantiza que una propuesta ya respondida
	 * no puede volver a modificarse (ni reenviar notificaciones), incluso con peticiones concurrentes.
	 *
	 * @since 1.1 Records who answers, and tells a database error apart from a proposal that was
	 *            no longer pending.
	 *
	 * @param int    $id        ID de la colaboración.
	 * @param string $estado    Nuevo estado: 'aceptada' o 'rechazada'.
	 * @param string $respuesta Mensaje de respuesta ya sanitizado.
	 * @param int    $user_id   User who answers, so their reply can be exported and erased.
	 * @return int|false 1 when the proposal was answered, 0 when it was no longer pending, or
	 *                   false on a database error.
	 */
	public static function resolve_pending( int $id, string $estado, string $respuesta, int $user_id ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->update(
			self::get_table_name(),
			[
				'estado'               => $estado,
				'respuesta_mensaje'    => $respuesta,
				'usuario_respuesta_id' => $user_id,
				'updated_at'           => current_time( 'mysql' ),
			],
			[
				'id'     => $id,
				'estado' => 'pendiente',
			],
			[ '%s', '%s', '%d', '%s' ],
			[ '%d', '%s' ]
		);

		return false === $updated ? false : min( 1, (int) $updated );
	}
}
