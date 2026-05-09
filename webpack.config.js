/**
 * Custom wp-scripts webpack config.
 *
 * Extends the default with a CopyWebpackPlugin pattern that ships the
 * `block/partials/` directory alongside the rendered `render.php`. Without
 * it, render.php's `require __DIR__ . '/partials/...'` calls fail with a
 * fatal because the build output only contains the files copy-webpack-plugin
 * is told to copy.
 */
const path = require( 'path' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins,
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve( __dirname, 'block/partials' ),
					to: path.resolve( __dirname, 'build/partials' ),
					noErrorOnMissing: true,
				},
			],
		} ),
	],
};
