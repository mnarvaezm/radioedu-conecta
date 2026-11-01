/**
 * Build of the plugin scripts and of the directory script module.
 *
 * `@wordpress/scripts` 37 builds script modules only from block.json files in its source folder,
 * not from entries given on the command line, so this config names both sets of entries. It runs
 * with --experimental-modules, which makes the default config a [ scripts, modules ] pair.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

if ( ! Array.isArray( defaultConfig ) ) {
	throw new Error(
		'Run the build with --experimental-modules (npm run build).'
	);
}

const [ scriptConfig, moduleConfig ] = defaultConfig;

module.exports = [
	{
		...scriptConfig,
		entry: {
			admin: './assets/js/admin.js',
			'centro-panel': './assets/js/centro-panel.js',
			'network-sync': './assets/js/network-sync.js',
		},
	},
	{
		...moduleConfig,
		entry: {
			'directorio-view': './assets/js/directorio-view.js',
		},
	},
];
