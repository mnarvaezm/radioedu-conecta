<?php
/**
 * Gestor de la tabla personalizada de colaboraciones.
 *
 * @package RadioEdu\Conecta\Database
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Clase encargada de la creación, actualización y gestión de la tabla de colaboraciones.
 */
class ColaboracionesTable {

	/**
	 * Versión del esquema de base de datos y capacidades.
	 */
	public const DB_VERSION = '1.0.1';

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
	 */
	public static function create_table(): void {
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
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY centro_emisor_id (centro_emisor_id),
			KEY centro_receptor_id (centro_receptor_id),
			KEY usuario_emisor_id (usuario_emisor_id),
			KEY estado (estado)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'radioedu_conecta_db_version', self::DB_VERSION );
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
	 * @param int             $user_id    ID del usuario.
	 * @param array<int, int> $center_ids IDs de los centros que gestiona el usuario.
	 * @param bool            $all        Si es true devuelve todas las propuestas (gestores del plugin).
	 * @param int             $limit      Número máximo de filas.
	 * @return array<int, object>
	 */
	public static function get_for_user( int $user_id, array $center_ids, bool $all = false, int $limit = 100 ): array {
		global $wpdb;
		$table_name = self::get_table_name();
		$limit      = max( 1, min( 500, $limit ) );

		if ( $all ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; listing must reflect the latest state.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d', $table_name, $limit ) );
		} elseif ( empty( $center_ids ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; listing must reflect the latest state.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE usuario_emisor_id = %d ORDER BY created_at DESC LIMIT %d', $table_name, $user_id, $limit ) );
		} else {
			$center_ids   = array_map( 'absint', $center_ids );
			$placeholders = implode( ',', array_fill( 0, count( $center_ids ), '%d' ) );
			$query_args   = array_merge( [ $table_name, $user_id ], $center_ids, $center_ids, [ $limit ] );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders only contains "%d" tokens built above; every value goes through prepare().
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE usuario_emisor_id = %d OR centro_emisor_id IN ({$placeholders}) OR centro_receptor_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d",
					...$query_args
				)
			);
			// phpcs:enable
		}

		return is_array( $rows ) ? array_values( array_filter( $rows, 'is_object' ) ) : [];
	}

	/**
	 * Resuelve una propuesta pendiente (aceptada o rechazada).
	 *
	 * La condición `estado = 'pendiente'` en el WHERE garantiza que una propuesta ya respondida
	 * no puede volver a modificarse (ni reenviar notificaciones), incluso con peticiones concurrentes.
	 *
	 * @param int    $id        ID de la colaboración.
	 * @param string $estado    Nuevo estado: 'aceptada' o 'rechazada'.
	 * @param string $respuesta Mensaje de respuesta ya sanitizado.
	 * @return bool True si se actualizó una propuesta pendiente.
	 */
	public static function resolve_pending( int $id, string $estado, string $respuesta ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->update(
			self::get_table_name(),
			[
				'estado'            => $estado,
				'respuesta_mensaje' => $respuesta,
				'updated_at'        => current_time( 'mysql' ),
			],
			[
				'id'     => $id,
				'estado' => 'pendiente',
			],
			[ '%s', '%s', '%s' ],
			[ '%d', '%s' ]
		);

		return 1 === $updated;
	}
}
