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

	$post_type = 'radioedu_centro';
	// Taxonomía => jerárquica (igual que en TaxonomiesRegistrar).
	$taxonomies = [
		'radioedu_etapa'        => true,
		'radioedu_idioma'       => false,
		'radioedu_tematica'     => false,
		'radioedu_formato'      => false,
		'radioedu_localizacion' => true,
	];

	// El plugin no está cargado durante la desinstalación: se registran temporalmente el CPT y
	// las taxonomías para que wp_delete_post()/wp_delete_term() limpien relaciones y metadatos.
	if ( ! post_type_exists( $post_type ) ) {
		register_post_type( $post_type, [ 'public' => false ] );
	}
	foreach ( $taxonomies as $taxonomy => $hierarchical ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				$post_type,
				[
					'public'       => false,
					'hierarchical' => $hierarchical,
				]
			);
		}
	}

	// 1. Eliminar tabla personalizada de colaboraciones.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table teardown on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'radioedu_colaboraciones' ) );

	// 2. Eliminar todos los centros (incluidos papelera y borradores) y sus metadatos, por lotes.
	do {
		$centro_ids = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		$deleted = 0;
		foreach ( $centro_ids as $centro_id ) {
			if ( wp_delete_post( (int) $centro_id, true ) ) {
				++$deleted;
			}
		}
	} while ( $deleted > 0 );

	// 3. Eliminar los términos de las 5 taxonomías del plugin y la caché de jerarquía del core.
	foreach ( array_keys( $taxonomies ) as $taxonomy ) {
		$term_ids = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_array( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}

		delete_option( $taxonomy . '_children' );
	}

	// 4. Eliminar opciones del plugin.
	delete_option( 'radioedu_conecta_settings' );
	delete_option( 'radioedu_conecta_db_version' );

	// 5. Eliminar transients de rate-limiting (radioedu_rate_{user_id}).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transients with dynamic keys cannot be enumerated through the Options API.
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_radioedu_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_radioedu_' ) . '%'
		)
	);

	// 6. Retirar las capacidades personalizadas de todos los roles del sitio.
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

	foreach ( wp_roles()->role_objects as $role ) {
		foreach ( $capabilities as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

/**
 * Ejecuta la desinstalación en el sitio actual o en todos los sitios de la red.
 */
function radioedu_conecta_uninstall(): void {
	if ( ! is_multisite() ) {
		radioedu_conecta_uninstall_single_site();
		return;
	}

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
}

radioedu_conecta_uninstall();
