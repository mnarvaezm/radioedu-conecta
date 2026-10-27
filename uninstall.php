<?php
/**
 * Fichero de desinstalación de RadioEdu Conecta.
 * Se ejecuta exclusivamente al borrar el plugin desde el panel de administración.
 *
 * @package RadioEdu\Conecta
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Borra los datos del plugin en un sitio individual.
 *
 * @global wpdb $wpdb
 */
function radioedu_conecta_uninstall_single_site(): void {
	global $wpdb;

	// 1. Eliminar tabla personalizada de colaboraciones.
	$table_name = $wpdb->prefix . 'radioedu_colaboraciones';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// 2. Eliminar opciones del plugin.
	delete_option( 'radioedu_conecta_settings' );
	delete_option( 'radioedu_conecta_db_version' );

	// 3. Eliminar transients de caché de filtros y rate-limiting.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_radioedu_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_radioedu_' ) . '%'
		)
	);

	// 4. Retirar capacidades personalizadas de los roles del sitio.
	$capabilities = [
		'manage_radioedu',
		'edit_radioedu_centros',
		'edit_others_radioedu_centros',
		'publish_radioedu_centros',
		'read_private_radioedu_centros',
		'delete_radioedu_centros',
		'propose_radioedu_colaboracion',
	];

	$roles = [ 'administrator', 'editor', 'author' ];
	foreach ( $roles as $role_slug ) {
		$role = get_role( $role_slug );
		if ( $role instanceof WP_Role ) {
			foreach ( $capabilities as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

// Comprobación Multisite: limpiar red entera o sitio individual.
if ( is_multisite() ) {
	// Eliminar opciones de red si las hubiera.
	delete_site_option( 'radioedu_conecta_network_settings' );
	delete_site_option( 'radioedu_conecta_db_version' );

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0, // Recorrer todos los sitios de la red.
		]
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		radioedu_conecta_uninstall_single_site();
		restore_current_blog();
	}
} else {
	radioedu_conecta_uninstall_single_site();
}
