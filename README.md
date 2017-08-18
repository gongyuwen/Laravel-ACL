## Laravel-ACL
ACL Plugins for Laravel

## Installation 
Use [Composer] to install the package:
```
$ composer require gongyuwen/laravel-acl
```

## Usage
1.register command
```php
//App\Console\Kernel.php

protected $commands = [    
    \LaravelAcl\Console\MakeAclCommand::class
];
```

2.use command
```
$ php artisan make:acl
```
3.edit config/acl.php
```php
/**
* Example:
* URL: admin/list
* ACTION: Administrator\DefaultController@index
* METHOD: get,post
* ALLOW: member, admin
* DENY: ACL_NO_ROLE
* MIDDLEWARE: acl
* ROUTE NAME: adminlist
**/
return [
    'menus' => [
        'admin' => [
            'namespace' => 'Administrator',
            'action'    => 'DefaultController@index',
            'uri'       => 'list',
            'method'    => 'get,post',
            'allow'     => 'member, admin',
            'deny'      => 'ACL_NO_ROLE',            
            'middleware'=> 'acl',
            'name'      => 'adminlist'
        ]
    ]
];
```

## Method
Method                  |  Description  |  Required  |  Type  |                     Explain     
------------------------| --------------|------------|--------|----------------------------------------------------------
\ACL::hasAccess()       |    $action    |     Yes    | Mixed  | 1.String use '@', for example: DefaultController@index        2.String use '/', for example: users/detail/{user} 3.Illuminate\Routing\Route, for example: Route::current()
\ACL::sidebars()        |  ...$menuname |     Yes    | string | the key in config/acl.php menus group                                           
\ACL::sidebarsExcept()  |  ...$menuname |     No     | string | the key in config/acl.php menus group    

