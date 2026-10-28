<?php
/**
 * Importador de datos de demostración para RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta\Admin
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Permite a los administradores importar las 6 emisoras de prueba desde el panel de ajustes.
 */
class DemoDataImporter {

	public const NONCE_ACTION = 'radioedu_import_demo_nonce_action';
	public const NONCE_NAME   = 'radioedu_import_demo_nonce';

	/**
	 * Registra el hook para procesar la importación manual de datos demo.
	 */
	public static function register(): void {
		add_action( 'admin_post_radioedu_import_demo_data', [ self::class, 'handle_import_request' ] );
	}

	/**
	 * Procesa la petición POST enviada desde la página de ajustes.
	 */
	public static function handle_import_request(): void {
		if ( ! current_user_can( 'manage_radioedu' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'radioedu-conecta' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$imported_count = self::import_demo_centers();

		$redirect_url = add_query_arg(
			[
				'post_type'     => CentroPostType::POST_TYPE,
				'page'          => 'radioedu-conecta-settings',
				'demo_imported' => $imported_count,
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Crea o actualiza las 6 emisoras de ejemplo del proyecto Traspasando Fronteras.
	 *
	 * @return int Número de emisoras creadas o actualizadas.
	 */
	public static function import_demo_centers(): int {
		$plugin_url = RADIOEDU_CONECTA_URL;

		$centros = [
			[
				'nombre'      => 'IES Sierra de Gata Radio',
				'slug'        => 'ies-sierra-de-gata-radio',
				'descripcion' => 'Emisora escolar comarcal centrada en la divulgación medioambiental y proyectos de radio rural.',
				'bandera'     => 'es-ex',
				'ubicacion'   => 'Hoyos, Cáceres (Extremadura)',
				'lat'         => '40.1706',
				'lng'         => '-6.7197',
				'edades'      => '12-16 años (ESO)',
				'podcast_url' => 'https://radioedu.educarex.es/sierradegata',
				'cover'       => $plugin_url . 'assets/images/demo/header-sierra-gata.jpg',
				'logo'        => $plugin_url . 'assets/images/demo/logo-sierra-gata.jpg',
				'etapas'      => [ 'Secundaria' ],
				'idiomas'     => [ 'Español' ],
				'tematicas'   => [ 'Medioambiente', 'Rural', 'Intercambio cultural' ],
				'formatos'    => [ 'Podcast', 'Entrevistas' ],
			],
			[
				'nombre'      => 'Ràdio Institut Montjuïc',
				'slug'        => 'radio-institut-montjuic',
				'descripcion' => 'Radio comunitaria estudiantil con programas de cultura urbana, debates y música independiente.',
				'bandera'     => 'es-ct',
				'ubicacion'   => 'Barcelona, Cataluña',
				'lat'         => '41.3667',
				'lng'         => '2.1583',
				'edades'      => '14-18 años (ESO y Bachillerato)',
				'podcast_url' => 'https://radioedu.educarex.es/montjuic',
				'cover'       => $plugin_url . 'assets/images/demo/header-montjuic.jpg',
				'logo'        => $plugin_url . 'assets/images/demo/logo-montjuic.jpg',
				'etapas'      => [ 'Secundaria', 'Bachillerato' ],
				'idiomas'     => [ 'Español', 'Catalán' ],
				'tematicas'   => [ 'Música', 'Cultura', 'Ciudad', 'Proyectos europeos' ],
				'formatos'    => [ 'Entrevistas', 'Magacín' ],
			],
			[
				'nombre'      => 'CEIP La Mar',
				'slug'        => 'ceip-la-mar',
				'descripcion' => 'Proyectos sonoros escolares sobre la biodiversidad marina y sostenibilidad costera.',
				'bandera'     => 'es-vc',
				'ubicacion'   => 'Altea, Alicante (Comunidad Valenciana)',
				'lat'         => '38.6000',
				'lng'         => '-0.0500',
				'edades'      => '6-12 años (Primaria)',
				'podcast_url' => 'https://radioedu.educarex.es/lamar',
				'cover'       => $plugin_url . 'assets/images/demo/header-la-mar.jpg',
				'logo'        => $plugin_url . 'assets/images/demo/logo-la-mar.jpg',
				'etapas'      => [ 'Primaria' ],
				'idiomas'     => [ 'Español', 'Valenciano' ],
				'tematicas'   => [ 'Entorno marino', 'Sostenibilidad', 'Intercambio cultural', 'Música' ],
				'formatos'    => [ 'Podcast', 'Ficción sonora' ],
			],
			[
				'nombre'      => 'CEIP San José',
				'slug'        => 'ceip-san-jose',
				'descripcion' => 'La voz de los más pequeños explorando el periodismo escolar, noticias y convivencia.',
				'bandera'     => 'es-ex',
				'ubicacion'   => 'Zafra, Badajoz (Extremadura)',
				'lat'         => '38.4258',
				'lng'         => '-6.4172',
				'edades'      => '8-12 años (Primaria)',
				'podcast_url' => 'https://radioedu.educarex.es/sanjosezafra',
				'cover'       => $plugin_url . 'assets/images/demo/header-san-jose.jpg',
				'logo'        => $plugin_url . 'assets/images/demo/logo-san-jose.jpg',
				'etapas'      => [ 'Primaria' ],
				'idiomas'     => [ 'Español' ],
				'tematicas'   => [ 'Convivencia', 'Igualdad', 'Aprendizaje servicio' ],
				'formatos'    => [ 'Podcast', 'Noticias' ],
			],
			[
				'nombre'      => 'IES Valle de Tena',
				'slug'        => 'ies-valle-de-tena',
				'descripcion' => 'Onda Pirineos: programas juveniles de alta montaña, deportes de invierno y ciencias de la naturaleza.',
				'bandera'     => 'es-ar',
				'ubicacion'   => 'Sabiñánigo, Huesca (Aragón)',
				'lat'         => '42.5186',
				'lng'         => '-0.3644',
				'edades'      => '12-18 años',
				'podcast_url' => 'https://radioedu.educarex.es/valledetena',
				'cover'       => $plugin_url . 'assets/images/demo/header-valle-tena.jpg',
				'logo'        => $plugin_url . 'assets/images/demo/logo-valle-tena.jpg',
				'etapas'      => [ 'Secundaria', 'Bachillerato', 'FP' ],
				'idiomas'     => [ 'Español' ],
				'tematicas'   => [ 'Deportes', 'Naturaleza', 'Juventud', 'Intercambio cultural' ],
				'formatos'    => [ 'Entrevistas', 'Magacín' ],
			],
			[
				'nombre'      => 'Colegio Internacional San Patricio',
				'slug'        => 'colegio-internacional-san-patricio',
				'descripcion' => 'International school radio connecting students across Europe through bilingual podcasts.',
				'bandera'     => 'ie',
				'ubicacion'   => 'Dublín, Irlanda',
				'lat'         => '53.3498',
				'lng'         => '-6.2603',
				'edades'      => '11-17 años',
				'podcast_url' => 'https://radioedu.educarex.es/sanpatricio-dublin',
				'cover'       => $plugin_url . 'assets/images/demo/header-san-patricio.jpg',
				'logo'        => $plugin_url . 'assets/images/demo/logo-san-patricio.jpg',
				'etapas'      => [ 'Secundaria', 'Bachillerato' ],
				'idiomas'     => [ 'Inglés', 'Español' ],
				'tematicas'   => [ 'Idiomas', 'Vida estudiantil', 'Cultura', 'Proyectos internacionales' ],
				'formatos'    => [ 'Podcast' ],
			],
		];

		$count = 0;

		foreach ( $centros as $data ) {
			// Comprobar si ya existe por slug.
			$existing = get_page_by_path( $data['slug'], OBJECT, CentroPostType::POST_TYPE );
			$post_id  = $existing ? $existing->ID : 0;

			$post_args = [
				'post_title'   => $data['nombre'],
				'post_name'    => $data['slug'],
				'post_excerpt' => $data['descripcion'],
				'post_content' => $data['descripcion'] . "\n\nSomos una emisora escolar comprometida con la red Traspasando Fronteras.",
				'post_status'  => 'publish',
				'post_type'    => CentroPostType::POST_TYPE,
			];

			if ( $post_id > 0 ) {
				$post_args['ID'] = $post_id;
				wp_update_post( $post_args );
			} else {
				$post_id = wp_insert_post( $post_args );
			}

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, '_radioedu_bandera', $data['bandera'] );
			update_post_meta( $post_id, '_radioedu_lat', $data['lat'] );
			update_post_meta( $post_id, '_radioedu_lng', $data['lng'] );
			update_post_meta( $post_id, '_radioedu_edades', $data['edades'] );
			update_post_meta( $post_id, '_radioedu_podcast_url', $data['podcast_url'] );
			update_post_meta( $post_id, '_radioedu_disponible_colaborar', '1' );
			update_post_meta( $post_id, '_radioedu_cover_url', $data['cover'] );
			update_post_meta( $post_id, '_radioedu_logo_url', $data['logo'] );

			// Asignar términos de taxonomía.
			wp_set_object_terms( $post_id, $data['etapas'], TaxonomiesRegistrar::TAX_ETAPA );
			wp_set_object_terms( $post_id, $data['idiomas'], TaxonomiesRegistrar::TAX_IDIOMA );
			wp_set_object_terms( $post_id, $data['tematicas'], TaxonomiesRegistrar::TAX_TEMATICA );
			wp_set_object_terms( $post_id, $data['formatos'], TaxonomiesRegistrar::TAX_FORMATO );
			wp_set_object_terms( $post_id, [ $data['ubicacion'] ], TaxonomiesRegistrar::TAX_LOCALIZACION );

			$count++;
		}

		// Asegurar que existe una página con el shortcode [radioedu_directorio].
		$existing_page = get_page_by_path( 'traspasando-fronteras' );
		if ( ! $existing_page ) {
			wp_insert_post(
				[
					'post_title'   => 'Traspasando Fronteras',
					'post_name'    => 'traspasando-fronteras',
					'post_content' => '<!-- wp:shortcode -->[radioedu_directorio]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				]
			);
		}

		return $count;
	}
}
