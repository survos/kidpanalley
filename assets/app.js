/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
// import './styles/app.scss';

// require('@sneat/bootstrap-html-admin-template-free/assets/vendor/js/helpers');
// require('@sneat/bootstrap-html-admin-template-free/assets/vendor/js/menu');
// require('@sneat/bootstrap-html-admin-template-free/assets/vendor/js/bootstrap');
// require('@sneat/bootstrap-html-admin-template-free/assets/js/main');
// require('@sneat/bootstrap-html-admin-template-free/assets/js/config');


// start the Stimulus application
console.log('stimulus...');
import './bootstrap.js';

import 'bootstrap/dist/css/bootstrap.min.css'

// hack because ps isn't loading right from sneat/base.html.twig
// window.PerfectScrollbar = require('perfect-scrollbar');

import hljs from 'highlight.js';
// https://highlightjs.org/usage/#getting-the-library
// const hljs = require('highlight.js/lib/core');
// import 'highlight.js/styles/github.css';
// import 'highlight.js/styles/php.css';
// import 'highlight.js/styles/html.css';
// import 'highlight.js/styles/twig.css';

// const hljs = require('highlight.js/lib/common');
// hljs.registerLanguage('twig', require('highlight.js/lib/languages/twig'));
// // hljs.registerLanguage('html', require('highlight.js/lib/languages/html'));
// hljs.registerLanguage('php', require('highlight.js/lib/languages/php'));
// hljs.highlightAll();

