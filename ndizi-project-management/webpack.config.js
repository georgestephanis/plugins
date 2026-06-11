const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

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

module.exports = {
	...defaultConfig,
	entry: {
		admin: './src/admin/index.js',
		portal: './src/portal/index.js',
		block: './src/block/index.js',
		adminbar: './src/adminbar/index.js',
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
