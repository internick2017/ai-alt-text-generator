const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/block-editor/index.js',
		'admin-settings': './src/admin-settings/index.js',
		'admin-bulk': './src/admin-bulk/index.js',
		'admin-audit': './src/admin-audit/index.js',
	},
};
