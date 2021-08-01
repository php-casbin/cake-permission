<?php

return [
    'Casbin' => [
         /*
         * Cake-casbin model setting.
         */
        'model' => [
            // Available Settings: "file", "text"
            'config_type' => 'file',
            'config_file_path' => __DIR__ . '/casbin-model.conf',
            'config_text' => '',
        ],

        // Cake-casbin adapter .
        'adapter' => '\CasbinAdapter\Cake\Adapter',

        /*
         * Cake-casbin database setting.
         */
        'database' => [
            // Database connection for following tables.
            'connection' => 'default',
            // CasbinRule tables and model.
            'casbin_rules_table' => 'casbin_rules',
        ],
    ],
];