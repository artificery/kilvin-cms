const mix = require('laravel-mix');
const webpack = require('webpack');

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

// https://github.com/JeffreyWay/laravel-mix/issues/1326
mix.setPublicPath('./themes');

mix.js('resources/js/cp.js', 'themes/cp/cp.js');
mix.js('resources/js/installer.js', 'themes/installer/installer.js');

mix.sass('resources/sass/cp/default/default.scss', 'themes/cp/default.css').version();
mix.sass('resources/sass/installer/installer.scss', 'themes/installer/installer.css').version();
