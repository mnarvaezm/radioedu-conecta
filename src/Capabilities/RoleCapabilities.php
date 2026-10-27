<?php
/**
 * Gestión de roles y capacidades personalizadas para RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta\Capabilities
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Capabilities;

use WP_Role;

defined( 'ABSPATH' ) || exit;

/**
 * Registra y asigna capacidades personalizadas para coordinadores y administradores.
 */
class RoleCapabilities {

	/**
	 * Añade las capacidades personalizadas a los roles estándar de WordPress.
	 */
	public static function add_capabilities(): void {
		// 1. Administrador: control total.
		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			$admin->add_cap( 'manage_radioedu' );
			$admin->add_cap( 'edit_radioedu_centros' );
			$admin->add_cap( 'edit_others_radioedu_centros' );
			$admin->add_cap( 'publish_radioedu_centros' );
			$admin->add_cap( 'read_private_radioedu_centros' );
			$admin->add_cap( 'delete_radioedu_centros' );
			$admin->add_cap( 'delete_others_radioedu_centros' );
			$admin->add_cap( 'propose_radioedu_colaboracion' );
		}

		// 2. Editor: gestión de centros y propuestas.
		$editor = get_role( 'editor' );
		if ( $editor instanceof WP_Role ) {
			$editor->add_cap( 'edit_radioedu_centros' );
			$editor->add_cap( 'edit_others_radioedu_centros' );
			$editor->add_cap( 'publish_radioedu_centros' );
			$editor->add_cap( 'read_private_radioedu_centros' );
			$editor->add_cap( 'propose_radioedu_colaboracion' );
		}

		// 3. Autor (coordinador de centro): editar su centro y proponer match.
		$author = get_role( 'author' );
		if ( $author instanceof WP_Role ) {
			$author->add_cap( 'edit_radioedu_centros' );
			$author->add_cap( 'publish_radioedu_centros' );
			$author->add_cap( 'propose_radioedu_colaboracion' );
		}
	}

	/**
	 * Retira las capacidades personalizadas de los roles.
	 */
	public static function remove_capabilities(): void {
		$caps = [
			'manage_radioedu',
			'edit_radioedu_centros',
			'edit_others_radioedu_centros',
			'publish_radioedu_centros',
			'read_private_radioedu_centros',
			'delete_radioedu_centros',
			'delete_others_radioedu_centros',
			'propose_radioedu_colaboracion',
		];

		$roles = [ 'administrator', 'editor', 'author' ];
		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role instanceof WP_Role ) {
				foreach ( $caps as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
