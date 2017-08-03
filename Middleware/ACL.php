<?php

namespace LaravelAcl\Middleware;

use Closure;
use LaravelAcl\ACLFacade;

class ACL
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if( ACLFacade::hasAccess( $request->route() ))
            return $next( $request );

        return redirect('/');
    }
}
