<?php

return [
    'testing' => [
        // o starter kit usa `pages` minúsculo; o default do pacote procura `Pages`
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx', 'svelte', 'vue'],
    ],
];
