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
	 * Lista completa de capacidades personalizadas del plugin.
	 *
	 * @return array<int, string>
	 */
	public static function get_all_capabilities(): array {
		return [
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
	}

	/**
	 * Añade las capacidades personalizadas a los roles estándar de WordPress.
	 */
	public static function add_capabilities(): void {
		// 1. Administrador: control total.
		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			foreach ( self::get_all_capabilities() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		// 2. Editor: gestión de centros y propuestas.
		$editor = get_role( 'editor' );
		if ( $editor instanceof WP_Role ) {
			$editor_caps = [
				'edit_radioedu_centros',
				'edit_others_radioedu_centros',
				'edit_published_radioedu_centros',
				'edit_private_radioedu_centros',
				'publish_radioedu_centros',
				'read_private_radioedu_centros',
				'delete_radioedu_centros',
				'delete_private_radioedu_centros',
				'delete_published_radioedu_centros',
				'propose_radioedu_colaboracion',
			];
			foreach ( $editor_caps as $cap ) {
				$editor->add_cap( $cap );
			}
		}

		// 3. Autor (coordinador de centro): crear, editar y publicar su propio centro y proponer match.
		$author = get_role( 'author' );
		if ( $author instanceof WP_Role ) {
			$author_caps = [
				'edit_radioedu_centros',
				'edit_published_radioedu_centros',
				'publish_radioedu_centros',
				'delete_radioedu_centros',
				'delete_published_radioedu_centros',
				'propose_radioedu_colaboracion',
			];
			foreach ( $author_caps as $cap ) {
				$author->add_cap( $cap );
			}
		}
	}

	/**
	 * Retira las capacidades personalizadas de los roles.
	 */
	public static function remove_capabilities(): void {
		$caps  = self::get_all_capabilities();
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
