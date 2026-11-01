<?php
/**
 * Tests for the bundled translations.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use WP_UnitTestCase;

/**
 * Verifies that the bundled translations load for PHP strings and for every script.
 */
class TranslationsTest extends WP_UnitTestCase {

	private const DOMAIN = 'radioedu-conecta';

	/**
	 * Locale returned by the determine_locale filter.
	 *
	 * @var string
	 */
	private string $locale = 'en_US';

	public function set_up(): void {
		parent::set_up();
		add_filter( 'determine_locale', [ $this, 'filter_locale' ] );
		// The suite loads the plugin from outside the plugins folder, so point WordPress at its languages folder.
		add_filter( 'lang_dir_for_domain', [ $this, 'filter_lang_dir' ], 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'determine_locale', [ $this, 'filter_locale' ] );
		remove_filter( 'lang_dir_for_domain', [ $this, 'filter_lang_dir' ] );
		unload_textdomain( self::DOMAIN, true );
		foreach ( array_keys( self::scripts() ) as $file ) {
			wp_deregister_script( 'radioedu-test-' . $file );
		}
		parent::tear_down();
	}

	public function filter_locale(): string {
		return $this->locale;
	}

	/**
	 * @param string|false $path   Languages directory path.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public function filter_lang_dir( $path, string $domain ) {
		return self::DOMAIN === $domain ? RADIOEDU_CONECTA_DIR . 'languages/' : $path;
	}

	private function use_locale( string $locale ): void {
		$this->locale = $locale;
		unload_textdomain( self::DOMAIN, true );
	}

	/**
	 * URL a compiled script has once the plugin is installed.
	 *
	 * load_script_textdomain() finds the JSON file by the MD5 of the script path inside the plugin
	 * folder ("build/admin.js"), which it reads from the script URL. The suite loads the plugin from
	 * wherever the checkout is (in CI, /home/runner/work/...), and that URL only gives the same path
	 * when the checkout sits one folder deep, so the scripts are registered as an installed copy.
	 *
	 * @param string $file Script file name, without extension.
	 */
	private static function installed_script_url( string $file ): string {
		return plugins_url( 'radioedu-conecta/build/' . $file . '.js' );
	}

	/**
	 * One source string of each compiled script, with the script file name as key.
	 *
	 * @return array<string, string>
	 */
	private static function scripts(): array {
		return [
			'admin'        => 'Usar este logo',
			'centro-panel' => 'Datos de la emisora',
			'directorio'   => 'Añadir a favoritos',
			'network-sync' => 'Sitio de origen',
		];
	}

	/**
	 * @return array<string, array{string, string, array<string, string>}>
	 */
	public static function data_locales(): array {
		return [
			'Catalan'   => [
				'ca',
				'Paràmetres',
				[
					'admin'        => 'Utilitza aquest logotip',
					'centro-panel' => "Dades de l'emissora",
					'directorio'   => 'Afegeix als preferits',
					'network-sync' => "Lloc d'origen",
				],
			],
			'Valencian' => [
				'ca_valencia',
				'Paràmetres',
				[
					'admin'        => 'Utilitza este logotip',
					'centro-panel' => "Dades de l'emissora",
					'directorio'   => 'Afig als preferits',
					'network-sync' => "Lloc d'origen",
				],
			],
			'Galician'  => [
				'gl_ES',
				'Axustes',
				[
					'admin'        => 'Usar este logo',
					'centro-panel' => 'Datos da emisora',
					'directorio'   => 'Engadir a favoritos',
					'network-sync' => 'Sitio de orixe',
				],
			],
			'Basque'    => [
				'eu',
				'Ezarpenak',
				[
					'admin'        => 'Erabili logo hau',
					'centro-panel' => 'Irratiaren datuak',
					'directorio'   => 'Gehitu gogokoetara',
					'network-sync' => 'Jatorrizko gunea',
				],
			],
		];
	}

	public function test_the_languages_folder_is_registered_on_init(): void {
		$this->assertSame( 0, has_action( 'init', 'radioedu_conecta_load_textdomain' ) );

		remove_filter( 'lang_dir_for_domain', [ $this, 'filter_lang_dir' ] );
		radioedu_conecta_load_textdomain();

		// With no file for this locale, the registry falls back to the folder the plugin registered.
		$expected = WP_PLUGIN_DIR . '/' . dirname( RADIOEDU_CONECTA_BASENAME ) . '/languages/';
		$this->assertSame( $expected, $GLOBALS['wp_textdomain_registry']->get( self::DOMAIN, 'zz_ZZ' ) );
	}

	/**
	 * @dataProvider data_locales
	 *
	 * @param string                $locale   Locale.
	 * @param string                $settings Translation of "Ajustes".
	 * @param array<string, string> $unused   Script translations.
	 */
	public function test_php_strings_are_translated( string $locale, string $settings, array $unused ): void {
		$this->use_locale( $locale );

		$this->assertSame( $settings, __( 'Ajustes', self::DOMAIN ) );
	}

	/**
	 * @dataProvider data_locales
	 *
	 * @param string                $locale   Locale.
	 * @param string                $unused   Translation of "Ajustes".
	 * @param array<string, string> $expected Expected translation of each script's sample string.
	 */
	public function test_every_script_has_its_translations( string $locale, string $unused, array $expected ): void {
		$this->use_locale( $locale );

		foreach ( self::scripts() as $file => $source ) {
			$handle = 'radioedu-test-' . $file;
			wp_register_script( $handle, self::installed_script_url( $file ), [], '1', true );

			$json = load_script_textdomain( $handle, self::DOMAIN, RADIOEDU_CONECTA_DIR . 'languages' );
			$this->assertIsString( $json, "No translations for build/{$file}.js in {$locale}." );

			$messages = json_decode( $json, true )['locale_data']['messages'];
			$this->assertSame( $locale, $messages['']['lang'] );
			$this->assertSame( [ $expected[ $file ] ], $messages[ $source ] );
		}
	}

	public function test_plural_forms_reach_the_scripts(): void {
		$this->use_locale( 'ca' );
		wp_register_script( 'radioedu-test-network-sync', self::installed_script_url( 'network-sync' ), [], '1', true );

		$json     = (string) load_script_textdomain( 'radioedu-test-network-sync', self::DOMAIN, RADIOEDU_CONECTA_DIR . 'languages' );
		$messages = json_decode( $json, true )['locale_data']['messages'];

		$this->assertSame( 'nplurals=2; plural=(n != 1);', $messages['']['plural-forms'] );
		$this->assertSame( [ '%d emissora sincronitzada', '%d emissores sincronitzades' ], $messages['%d emisora sincronizada'] );
	}
}
