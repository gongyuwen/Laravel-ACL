<?php

namespace LaravelAcl;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ACLProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Gate::define('acl', function ($user, $action) {

            return ACLFacade::hasAccess( $action );
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('acl', ACL::class );
		if( class_exists('ACL') == false )
        	class_alias( ACLFacade::class, 'ACL' );
    }
}
