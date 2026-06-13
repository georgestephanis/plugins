/**
 * Vendor build for the shared DataViews bundle.
 *
 * This is intentionally separate from webpack.config.js so the heavy
 * @wordpress/dataviews payload is built once (via `npm run build:vendor`) and
 * left out of the everyday `npm run build`. It emits:
 *   - build/vendor-dataviews.js        → exposed as window.ndiziDataViews
 *   - build/style-vendor-dataviews.css → DataViews' stylesheet (+ -rtl variant)
 *   - build/vendor-dataviews.asset.php → its external WP script dependencies
 *
 * Rebuild this only when @wordpress/dataviews is upgraded.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );

// This build only owns the vendor-dataviews.* outputs. Clean just those so it
// does not wipe the everyday build's artifacts (admin.js, time-entries.js, …).
const VENDOR_ARTIFACTS = [
	'vendor-dataviews.js',
	'vendor-dataviews.js.map',
	'vendor-dataviews.asset.php',
	'style-vendor-dataviews.css',
	'style-vendor-dataviews-rtl.css',
];

// Silence the Dart Sass legacy API deprecation warnings (as webpack.config.js
// does) and mark the CSS rule as having side effects. @wordpress/dataviews ships
// `"sideEffects": false`, so without this webpack tree-shakes the bare CSS
// import in src/vendor/dataviews.js and the stylesheet is never extracted.
const rules = defaultConfig.module.rules.map( ( rule ) => {
	if ( rule.use && Array.isArray( rule.use ) ) {
		const sassLoaderIndex = rule.use.findIndex(
			( loaderEntry ) =>
				loaderEntry.loader &&
				loaderEntry.loader.includes( 'sass-loader' )
		);
		if ( sassLoaderIndex !== -1 ) {
			const sassLoader = rule.use[ sassLoaderIndex ];
			sassLoader.options = {
				...sassLoader.options,
				sassOptions: {
					...sassLoader.options?.sassOptions,
					silenceDeprecations: [ 'legacy-js-api' ],
				},
			};
		}
	}
	if ( rule.test && rule.test.toString().includes( '.css' ) ) {
		rule.sideEffects = true;
	}
	return rule;
} );

// Bundle every @wordpress/dataviews subpath (the `/wp` entry point AND its
// `/build-style/style.css`) into this output; keep all other @wordpress/*
// packages (react, wp-components, wp-private-apis, …) external so the bundle
// reuses the copies WordPress already ships.
const plugins = defaultConfig.plugins.map( ( plugin ) => {
	if ( plugin instanceof DependencyExtractionWebpackPlugin ) {
		return new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if (
					request === '@wordpress/dataviews' ||
					request.startsWith( '@wordpress/dataviews/' )
				) {
					// `false` (not `undefined`) skips the default cascade and
					// forces the request to be bundled here.
					return false;
				}
				return undefined;
			},
		} );
	}
	if ( plugin instanceof CleanWebpackPlugin ) {
		return new CleanWebpackPlugin( {
			cleanStaleWebpackAssets: false,
			cleanOnceBeforeBuildPatterns: VENDOR_ARTIFACTS,
		} );
	}
	return plugin;
} );

module.exports = {
	...defaultConfig,
	plugins,
	entry: {
		'vendor-dataviews': {
			import: './src/vendor/dataviews.js',
			// Exposed as window.ndiziDataViews; webpack.config.js externalizes
			// @wordpress/dataviews to this same global.
			library: { name: 'ndiziDataViews', type: 'window' },
		},
	},
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
	module: {
		...defaultConfig.module,
		rules,
	},
};
