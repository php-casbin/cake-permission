<?php

return [
     /*
     * Cake-casbin model setting.
     */
    'model' => [
        // Available Settings: "file", "text"
        'config_type' => 'file',
        'config_file_path' => '/path/to/casbin-model.conf',
        'config_text' => '',
    ],

    // Cake-casbin adapter .
    'adapter' => '\Cake\Permission\Adapter',

    /*
     * Cake-casbin database setting.
     */
    'database' => [
        // Database connection for following tables.
        'connection' => '',
        // CasbinRule tables and model.
        'casbin_rules_table' => '',
    ],
];
