
try {
    window.$ = window.jQuery = require('jquery');
    require('bootstrap');
} catch (e) {}

window.moment = require('moment');
require('moment-timezone');

window.zxcvbn = require('zxcvbn');
