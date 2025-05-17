# Cake-Permission

[![Test](https://github.com/php-casbin/cake-permission/actions/workflows/test.yml/badge.svg)](https://github.com/php-casbin/cake-permission/actions/workflows/test.yml)
[![Coverage Status](https://coveralls.io/repos/github/php-casbin/cake-permission/badge.svg)](https://coveralls.io/github/php-casbin/cake-permission)
[![Latest Stable Version](https://poser.pugx.org/casbin/cake-permission/v/stable)](https://packagist.org/packages/casbin/cake-permission)
[![Total Downloads](https://poser.pugx.org/casbin/cake-permission/downloads)](https://packagist.org/packages/casbin/cake-permission)
[![License](https://poser.pugx.org/casbin/cake-permission/license)](https://packagist.org/packages/casbin/cake-permission)

Use Casbin in [CakePHP](https://github.com/cakephp/cakephp) Framework, Casbin is a powerful and efficient open-source access control library.

### Installation

Require this package in the `composer.json` of your CakePHP project. This will download the package.

```
composer require casbin/cake-permission
```

create config file `config/casbin.php` for Casbin:

```php
<?php

return [
    'Casbin' => [
         /*
         * Cake-casbin model setting.
         */
        'model' => [
            // Available Settings: "file", "text"
            'config_type' => 'file',
            'config_file_path' => __DIR__.'/casbin-model.conf',
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
    ],
];
```

create a new model config file named `config/casbin-model.conf`.

```
[request_definition]
r = sub, obj, act

[policy_definition]
p = sub, obj, act

[policy_effect]
e = some(where (p.eft == allow))

[matchers]
m = r.sub == p.sub && r.obj == p.obj && r.act == p.act
```


### Usage

```php

$sub = 'alice'; // the user that wants to access a resource.
$obj = 'data1'; // the resource that is going to be accessed.
$act = 'read'; // the operation that the user performs on the resource.

$casbin = new \Cake\Permission\Casbin();

if (true === $casbin->enforce($sub, $obj, $act)) {
    // permit alice to read data1
} else {
    // deny the request, show an error
}
```

### Define your own model.conf

You can modify the config file named `config/casbin-model.conf`

### Learning Casbin

You can find the full documentation of Casbin [on the website](https://casbin.org/).
