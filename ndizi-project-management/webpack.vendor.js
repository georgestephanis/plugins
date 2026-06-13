/**
 * Vendor build for the shared DataViews bundle.
 *
 * Intentionally separate from webpack.config.js so the heavy @wordpress/dataviews
 * payload is built once (via `npm run build:vendor`) and left out of the everyday
 * `npm run build`/`npm start` cycle. It emits:
 *   - build/vendor-dataviews.js        → exposed as window.ndiziDataViews
 *   - build/style-vendor-dataviews.css → DataViews' stylesheet (+ -rtl variant)
 *   - build/vendor-dataviews.asset.php → its external WP script dependencies
 *
 * Rebuild this only when @wordpress/dataviews is upgraded.
 */
const path = require( 'path' );
const {
	defaultConfig,
	VENDOR_ARTIFACTS,
	buildRules,
	buildPlugins,
} = require( './webpack.shared' );

module.exports = {
	...defaultConfig,
	plugins: buildPlugins( {
		dependencyExtraction: {
			requestToExternal( request ) {
				// Bundle every @wordpress/dataviews subpath (the `/wp` entry
				// point AND its `/build-style/style.css`) into this output; keep
				// all other @wordpress/* packages (react, wp-data, …) external so
				// the bundle reuses the copies WordPress already ships.
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
		},
		// This build only owns the vendor-dataviews.* outputs; clean just those so
		// it does not wipe the everyday build's artifacts (admin.js, …).
		cleanOnceBeforeBuildPatterns: VENDOR_ARTIFACTS,
	} ),
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
		// @wordpress/dataviews ships `"sideEffects": false`; mark the CSS rule as
		// having side effects so the bundled stylesheet import is not tree-shaken.
		rules: buildRules( { markCssSideEffects: true } ),
	},
};
