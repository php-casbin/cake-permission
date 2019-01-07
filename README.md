# Cake-Casbin

[![Latest Stable Version](https://poser.pugx.org/casbin/cake-adapter/v/stable)](https://packagist.org/packages/casbin/cake-adapter)
[![Total Downloads](https://poser.pugx.org/casbin/cake-adapter/downloads)](https://packagist.org/packages/casbin/cake-adapter)
[![License](https://poser.pugx.org/casbin/cake-adapter/license)](https://packagist.org/packages/casbin/cake-adapter)

Use Casbin in CakePHP Framework, Casbin is a powerful and efficient open-source access control library.

### Installation

Require this package in the `composer.json` of your CakePHP project. This will download the package.

```
composer require casbin/cake-adapter
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
        'adapter' => '\CasbinAdapter\Cake\Adapter',

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

$casbin = new \CasbinAdapter\Cake\Casbin();

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
