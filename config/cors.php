<?php

return [
    /*
     * Match all paths so OPTIONS preflight and responses always get CORS headers.
     * (Scoped patterns like api/* miss some setups, e.g. subpaths or proxies.)
     */
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
