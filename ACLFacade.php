<?php
namespace LaravelAcl;

use Illuminate\Support\Facades\Facade;

class ACLFacade extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'acl';
    }
}