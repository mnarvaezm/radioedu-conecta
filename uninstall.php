<?php
/**
 * Fichero de desinstalación de RadioEdu Conecta.
 * Se ejecuta exclusivamente al borrar el plugin desde el panel de administración.
 *
 * @package RadioEdu\Conecta
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Borra todos los datos del plugin en un sitio individual sin dejar residuos.
 *
 * @global wpdb $wpdb
 */
function radioedu_conecta_uninstall_single_site(): void {
	global $wpdb;

	// 1. Eliminar tabla personalizada de colaboraciones.
	$table_name = $wpdb->prefix . 'radioedu_colaboraciones';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

	// 2. Eliminar todos los posts del CPT 'radioedu_centro' y sus metadatos.
	$centro_ids = get_posts(
		[
			'post_type'      => 'radioedu_centro',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
		]
	);

	foreach ( $centro_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	// 3. Eliminar los términos de las 5 taxonomías del plugin.
	$taxonomies = [
		'radioedu_etapa',
		'radioedu_idioma',
		'radioedu_tematica',
		'radioedu_formato',
		'radioedu_localizacion',
	];

	foreach ( $taxonomies as $taxonomy ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT term_id FROM %i WHERE taxonomy = %s',
				$wpdb->term_taxonomy,
				$taxonomy
			)
		);

		if ( is_array( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}
	}

	// 4. Eliminar opciones del plugin.
	delete_option( 'radioedu_conecta_settings' );
	delete_option( 'radioedu_conecta_db_version' );

	// 5. Eliminar transients de caché de filtros y rate-limiting.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_radioedu_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_radioedu_' ) . '%'
		)
	);

	// 6. Retirar todas las capacidades personalizadas de los roles del sitio.
	$capabilities = [
		'manage_radioedu',
		'edit_radioedu_centros',
		'edit_others_radioedu_centros',
		'edit_published_radioedu_centros',
		'edit_private_radioedu_centros',
		'publish_radioedu_centros',
		'read_private_radioedu_centros',
		'delete_radioedu_centros',
		'delete_private_radioedu_centros',
		'delete_published_radioedu_centros',
		'delete_others_radioedu_centros',
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
	delete_site_option( 'radioedu_conecta_network_settings' );
	delete_site_option( 'radioedu_conecta_db_version' );

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
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
