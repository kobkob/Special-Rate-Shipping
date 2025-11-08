const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
	const isProduction = argv.mode === 'production';

	return {
		entry: {
			admin: './assets/src/js/admin.js',
			frontend: './assets/src/js/frontend.js',
			settings: './assets/src/js/settings.js',
			'admin-style': './assets/src/scss/admin.scss',
			'frontend-style': './assets/src/scss/frontend.scss'
		},
		output: {
			path: path.resolve(__dirname, 'assets/dist'),
			filename: 'js/[name].js',
			clean: true
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					exclude: /node_modules/,
					use: {
						loader: 'babel-loader',
						options: {
							presets: ['@babel/preset-env']
						}
					}
				},
				{
					test: /\.scss$/,
					use: [
						MiniCssExtractPlugin.loader,
						'css-loader',
						'sass-loader'
					]
				}
			]
		},
		plugins: [
			new MiniCssExtractPlugin({
				filename: 'css/[name].css'
			})
		],
		optimization: {
			minimize: isProduction
		},
		devtool: isProduction ? false : 'source-map',
		externals: {
			jquery: 'jQuery'
		}
	};
};