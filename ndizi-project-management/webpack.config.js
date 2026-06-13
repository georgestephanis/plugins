const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );

// Outputs owned by the separate vendor build (webpack.vendor.js). The everyday
// build must NOT delete these — they are built once via `npm run build:vendor`.
const VENDOR_ARTIFACTS = [
	'vendor-dataviews.js',
	'vendor-dataviews.js.map',
	'vendor-dataviews.asset.php',
	'style-vendor-dataviews.css',
	'style-vendor-dataviews-rtl.css',
];

// Modify sass-loader options to silence Dart Sass legacy API deprecation warnings
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

	return rule;
} );

// DataViews is built once into the shared `build/vendor-dataviews.js` bundle
// (see webpack.vendor.js) and registered in PHP as the `ndizi-dataviews` script
// handle on the `window.ndiziDataViews` global. WordPress core does not register
// a public `wp-dataviews` handle of its own (DataViews ships only bundled inside
// the editor packages — https://github.com/WordPress/gutenberg/issues/63657), so
// here we externalize `@wordpress/dataviews` to our shared global and map it to
// the `ndizi-dataviews` handle. That keeps the heavy DataViews code out of every
// app rebuild and lets multiple scripts share the one bundle.
const DATAVIEWS_REQUESTS = [ '@wordpress/dataviews', '@wordpress/dataviews/wp' ];
const plugins = defaultConfig.plugins.map( ( plugin ) => {
	if ( plugin instanceof DependencyExtractionWebpackPlugin ) {
		return new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if ( DATAVIEWS_REQUESTS.includes( request ) ) {
					// Array form → `window.ndiziDataViews` (matches the
					// library name emitted by webpack.vendor.js).
					return [ 'ndiziDataViews' ];
				}
				// Defer all other requests to the default behavior.
				return undefined;
			},
			requestToHandle( request ) {
				if ( DATAVIEWS_REQUESTS.includes( request ) ) {
					// Written into each consuming entry's .asset.php so WP loads
					// the shared bundle before the dependent script.
					return 'ndizi-dataviews';
				}
				return undefined;
			},
		} );
	}
	// wp-scripts wipes the whole build/ dir before each build; keep the
	// separately-built vendor bundle so the everyday build does not delete it.
	if ( plugin instanceof CleanWebpackPlugin ) {
		return new CleanWebpackPlugin( {
			cleanStaleWebpackAssets: false,
			cleanOnceBeforeBuildPatterns: [
				'**/*',
				...VENDOR_ARTIFACTS.map( ( file ) => `!${ file }` ),
			],
		} );
	}
	return plugin;
} );

module.exports = {
	...defaultConfig,
	plugins,
	entry: {
		admin: './src/admin/index.js',
		portal: './src/portal/index.js',
		block: './src/block/index.js',
		adminbar: './src/adminbar/index.js',
		standalone: './src/standalone/index.js',
		'time-entries': './src/admin/time-entries.js',
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
