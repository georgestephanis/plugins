const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

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
};
