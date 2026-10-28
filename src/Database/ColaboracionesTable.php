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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
		delete_option( 'radioedu_conecta_db_version' );
	}
}
