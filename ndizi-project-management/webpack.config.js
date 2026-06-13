const path = require( 'path' );
const {
	defaultConfig,
	VENDOR_ARTIFACTS,
	buildRules,
	buildPlugins,
} = require( './webpack.shared' );

// DataViews is built once into the shared `build/vendor-dataviews.js` bundle
// (see webpack.vendor.js) and registered in PHP as the `ndizi-dataviews` script
// handle on the `window.ndiziDataViews` global. WordPress core does not register
// a public `wp-dataviews` handle of its own (DataViews ships only bundled inside
// the editor packages — https://github.com/WordPress/gutenberg/issues/63657), so
// here we externalize `@wordpress/dataviews` to our shared global and map it to
// the `ndizi-dataviews` handle. That keeps the heavy DataViews code out of every
// app rebuild and lets multiple scripts share the one bundle.
const DATAVIEWS_REQUESTS = [ '@wordpress/dataviews', '@wordpress/dataviews/wp' ];

module.exports = {
	...defaultConfig,
	plugins: buildPlugins( {
		dependencyExtraction: {
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
		},
		// Keep the separately-built vendor bundle; only the vendor build owns it.
		cleanOnceBeforeBuildPatterns: [
			'**/*',
			...VENDOR_ARTIFACTS.map( ( file ) => `!${ file }` ),
		],
	} ),
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
		rules: buildRules(),
	},
};
