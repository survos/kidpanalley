<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'survos-api-grid.css' => [
        'path' => './vendor/survos/api-grid-bundle/public/style.css',
        'type' => 'css',
    ],
    'twig' => [
        'version' => '1.17.1',
    ],
    'locutus/php/strings/sprintf' => [
        'version' => '2.0.32',
    ],
    'locutus/php/strings/vsprintf' => [
        'version' => '2.0.32',
    ],
    'locutus/php/math/round' => [
        'version' => '2.0.32',
    ],
    'locutus/php/math/max' => [
        'version' => '2.0.32',
    ],
    'locutus/php/math/min' => [
        'version' => '2.0.32',
    ],
    'locutus/php/strings/strip_tags' => [
        'version' => '2.0.32',
    ],
    'locutus/php/datetime/strtotime' => [
        'version' => '2.0.32',
    ],
    'locutus/php/datetime/date' => [
        'version' => '2.0.32',
    ],
    'locutus/php/var/boolval' => [
        'version' => '2.0.32',
    ],
    'axios' => [
        'version' => '1.7.9',
    ],
    'fos-routing' => [
        'version' => '0.0.6',
    ],
    'datatables.net-plugins/i18n/en-GB.mjs' => [
        'version' => '2.1.7',
    ],
    'datatables.net-bs5' => [
        'version' => '2.2.0',
    ],
    'jquery' => [
        'version' => '3.7.1',
    ],
    'datatables.net' => [
        'version' => '2.2.0',
    ],
    'datatables.net-bs5/css/dataTables.bootstrap5.min.css' => [
        'version' => '2.2.0',
        'type' => 'css',
    ],
    'datatables.net-buttons-bs5' => [
        'version' => '3.2.0',
    ],
    'datatables.net-buttons' => [
        'version' => '3.2.0',
    ],
    'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css' => [
        'version' => '3.2.0',
        'type' => 'css',
    ],
    'datatables.net-responsive-bs5' => [
        'version' => '3.0.3',
    ],
    'datatables.net-responsive' => [
        'version' => '3.0.3',
    ],
    'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css' => [
        'version' => '3.0.3',
        'type' => 'css',
    ],
    'datatables.net-scroller-bs5' => [
        'version' => '2.4.3',
    ],
    'datatables.net-scroller' => [
        'version' => '2.4.3',
    ],
    'datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    'datatables.net-searchpanes-bs5' => [
        'version' => '2.3.3',
    ],
    'datatables.net-searchpanes' => [
        'version' => '2.3.3',
    ],
    'datatables.net-searchpanes-bs5/css/searchPanes.bootstrap5.min.css' => [
        'version' => '2.3.3',
        'type' => 'css',
    ],
    'datatables.net-select-bs5' => [
        'version' => '2.1.0',
    ],
    'datatables.net-select' => [
        'version' => '2.1.0',
    ],
    'datatables.net-select-bs5/css/select.bootstrap5.min.css' => [
        'version' => '2.1.0',
        'type' => 'css',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'bootstrap' => [
        'version' => '5.3.3',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.3',
        'type' => 'css',
    ],
    'stimulus-content-loader' => [
        'version' => '4.2.0',
    ],
    'stimulus-reveal-controller' => [
        'version' => '4.1.0',
    ],
    '@kanety/stimulus-zoom-image' => [
        'version' => '1.1.0',
    ],
    '@kanety/stimulus-static-actions' => [
        'version' => '1.1.0',
    ],
    'perfect-scrollbar' => [
        'version' => '1.5.6',
    ],
    'perfect-scrollbar/css/perfect-scrollbar.min.css' => [
        'version' => '1.5.6',
        'type' => 'css',
    ],
    'bootstrap-icons/font/bootstrap-icons.min.css' => [
        'version' => '1.11.3',
        'type' => 'css',
    ],
    'idb' => [
        'version' => '8.0.1',
    ],
    'workbox' => [
        'version' => '0.0.0',
    ],
    'workbox-recipes' => [
        'version' => '7.3.0',
    ],
    'workbox-routing/registerRoute.js' => [
        'version' => '7.3.0',
    ],
    'workbox-strategies/StaleWhileRevalidate.js' => [
        'version' => '7.3.0',
    ],
    'workbox-strategies/CacheFirst.js' => [
        'version' => '7.3.0',
    ],
    'workbox-cacheable-response/CacheableResponsePlugin.js' => [
        'version' => '7.3.0',
    ],
    'workbox-expiration/ExpirationPlugin.js' => [
        'version' => '7.3.0',
    ],
    'workbox-strategies/NetworkFirst.js' => [
        'version' => '7.3.0',
    ],
    'workbox-routing/setCatchHandler.js' => [
        'version' => '7.3.0',
    ],
    'workbox-precaching/matchPrecache.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/logger.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/WorkboxError.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/assert.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/getFriendlyURL.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/cacheNames.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/cacheMatchIgnoreParams.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/Deferred.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/executeQuotaErrorCallbacks.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/timeout.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/dontWaitFor.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/registerQuotaErrorCallback.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/_private/waitUntil.js' => [
        'version' => '7.3.0',
    ],
    'workbox-core/copyResponse.js' => [
        'version' => '7.3.0',
    ],
    'workbox-strategies/Strategy.js' => [
        'version' => '7.3.0',
    ],
    'workbox-strategies' => [
        'version' => '7.3.0',
    ],
    'workbox-routing' => [
        'version' => '7.3.0',
    ],
    'workbox-cacheable-response' => [
        'version' => '7.3.0',
    ],
    'workbox-expiration' => [
        'version' => '7.3.0',
    ],
    'chart.js/auto' => [
        'version' => '4.4.7',
    ],
    'js-logger' => [
        'version' => '1.6.1',
    ],
    'datatables.net-searchbuilder-bs5' => [
        'version' => '1.8.1',
    ],
    'datatables.net-searchbuilder' => [
        'version' => '1.8.1',
    ],
    'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css' => [
        'version' => '1.8.1',
        'type' => 'css',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    'chart.js' => [
        'version' => '4.4.7',
    ],
    'datatables.net-plugins/i18n/es-ES.mjs' => [
        'version' => '2.1.7',
    ],
    'datatables.net-plugins/i18n/de-DE.mjs' => [
        'version' => '2.1.7',
    ],
    '@tabler/core' => [
        'version' => '1.0.0-beta21',
    ],
    '@tabler/core/dist/css/tabler.min.css' => [
        'version' => '1.0.0-beta21',
        'type' => 'css',
    ],
    'babel-runtime/core-js/promise' => [
        'version' => '6.26.0',
    ],
    'core-js/library/fn/promise' => [
        'version' => '2.6.12',
    ],
];
