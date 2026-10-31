/**
 * ESLint config: the @wordpress/scripts defaults, plus the WordPress packages that the build
 * loads from core as `wp.*` scripts (see build/*.asset.php) instead of bundling them, so they
 * are not installed as npm dependencies.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		settings: {
			'import/core-modules': [
				'@wordpress/block-editor',
				'@wordpress/components',
				'@wordpress/core-data',
				'@wordpress/data',
				'@wordpress/editor',
				'@wordpress/element',
				'@wordpress/plugins',
				'@wordpress/preferences',
			],
		},
	},
];
