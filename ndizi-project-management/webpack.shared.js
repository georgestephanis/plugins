/**
 * Shared webpack helpers for the everyday build (webpack.config.js) and the
 * separate DataViews vendor build (webpack.vendor.js).
 *
 * The two builds are kept as distinct webpack configs on purpose: the heavy
 * @wordpress/dataviews payload is built once via `npm run build:vendor` and
 * left out of the everyday `npm run build`/`npm start` cycle. These helpers
 * remove the boilerplate both configs would otherwise duplicate.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );

// Outputs owned by the vendor build. wp-scripts' CleanWebpackPlugin wipes the
// whole build/ dir before every build, so each config must exclude the other's
// files from cleaning (see buildPlugins) to avoid deleting them.
const VENDOR_ARTIFACTS = [
	'vendor-dataviews.js',
	'vendor-dataviews.js.map',
	'vendor-dataviews.asset.php',
	'style-vendor-dataviews.css',
	'style-vendor-dataviews-rtl.css',
];

/**
 * Clone the default module rules with the Dart Sass legacy API deprecation
 * warnings silenced.
 *
 * @param {Object}  [options]
 * @param {boolean} [options.markCssSideEffects] When true, flag the CSS rule as
 *        having side effects. @wordpress/dataviews ships `"sideEffects": false`,
 *        so without this webpack tree-shakes the bare CSS import in the vendor
 *        entry and the stylesheet is never extracted.
 * @return {Array} The transformed rules array.
 */
function buildRules( { markCssSideEffects = false } = {} ) {
	return defaultConfig.module.rules.map( ( rule ) => {
		// Avoid mutating @wordpress/scripts' shared defaultConfig in-place.
		rule = {
			...rule,
			use: Array.isArray( rule.use )
				? rule.use.map( ( loaderEntry ) =>
						typeof loaderEntry === 'object'
							? { ...loaderEntry }
							: loaderEntry
				  )
				: rule.use,
		};
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
		if (
			markCssSideEffects &&
			rule.test &&
			rule.test.toString().includes( '.css' )
		) {
			rule.sideEffects = true;
		}
		return rule;
	} );
}

/**
 * Clone the default plugins, swapping in a custom DependencyExtractionWebpackPlugin
 * and a CleanWebpackPlugin scoped so this build does not delete the other build's
 * output.
 *
 * @param {Object} options
 * @param {Object} options.dependencyExtraction       Options for the replacement
 *        DependencyExtractionWebpackPlugin (e.g. requestToExternal/requestToHandle).
 * @param {string[]} options.cleanOnceBeforeBuildPatterns Glob patterns for the
 *        replacement CleanWebpackPlugin's pre-build cleanup.
 * @return {Array} The transformed plugins array.
 */
function buildPlugins( {
	dependencyExtraction,
	cleanOnceBeforeBuildPatterns,
} ) {
	return defaultConfig.plugins.map( ( plugin ) => {
		if ( plugin instanceof DependencyExtractionWebpackPlugin ) {
			return new DependencyExtractionWebpackPlugin(
				dependencyExtraction
			);
		}
		if ( plugin instanceof CleanWebpackPlugin ) {
			return new CleanWebpackPlugin( {
				cleanStaleWebpackAssets: false,
				cleanOnceBeforeBuildPatterns,
			} );
		}
		return plugin;
	} );
}

module.exports = {
	defaultConfig,
	VENDOR_ARTIFACTS,
	buildRules,
	buildPlugins,
};
