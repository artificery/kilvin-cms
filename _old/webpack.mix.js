const { mix } = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

/*
Look in webpack.mix.js in the node module for available options
*/


mix.sass(
	'public/themes/cp_themes/default/scss/default.scss',
	'public/themes/cp_themes/default/default.css'
).options({ processCssUrls: false });
