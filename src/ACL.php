<?php
namespace LaravelAcl;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Routing\Route as RoutingRoute;
class ACL
{
    /*所有人*/
    const ACL_EVERYONE = 'acl_everyone';
    /*游客*/
    const ACL_NO_ROLE = 'acl_no_role';
    /*ACL_HAS_ROLE*/
    const ACL_HAS_ROLE = 'acl_has_role';

    private $_MENUS = [];

    private $_DEFAULT_METHODS = [];

    private $_GROUP_ATTRIBUTES = [];

    function __construct()
    {
        $this->_DEFAULT_METHODS = config('acl.default_methods');

        $this->_GROUP_ATTRIBUTES = config('acl.group_attributes');

        $this->_MENUS = config('acl.menus');
    }

    public function hasAccess( $action )
    {
        if ($action instanceof RoutingRoute )
            return $this->getAccessUseRoute( $action );
        elseif( str_contains( $action, '@') )
            return $this->getAccessUseAction( $action );
        else
            return $this->getAccessUseUri( $action );

        return FALSE;
    }

    public function routes( $menu = [] )
    {
        $menu = empty( $menu ) ? $this->_MENUS : $menu;

        $callback = $this->resolveRoutes( $menu );

        $this->resoveCallback( $callback );
    }

    public function sidebars( ...$prefixs )
    {
        $menus = array_only( $this->_MENUS, $prefixs );

        return $this->getSidebars( $menus );
    }
    public function sidebarsExcept( ...$prefixs )
    {
        $menus = array_except( $this->_MENUS, $prefixs );

        return $this->getSidebars( $menus );
    }

    private function allowOrDeny( $allows, $denys )
    {
        $allow = array_pop( $allows );

        if( count( $allow ) )
            return $this->checkAccess( compact('allow') );

        $deny = array_pop( $denys );

        if( count( $deny ) )
            return $this->checkAccess( compact('deny') );

        return TRUE;
    }

    private function analysisMenuForSiderbar()
    {
        return function( $menu, $allows = [], $denys = [], $namespaces = [], $maps = [], $pieces = [] )
        {
            if( empty( $maps ))
                array_push( $maps, [] );


            list( $allows, $denys ) = $this->getAllowAndDenyAccess( $menu, $allows, $denys );

            $namespaces = $this->getNamespaces( $menu, $namespaces );

            if( $subMenu = array_get( $menu, 'items' ) )
            {
                $callback = $this->analysisMenuForSiderbar();

                if( $label = array_get( $menu, 'label' ) )
                {
                    $icon = array_get( $menu, 'icon' );

                    list( $maps, $pieces ) = $this->setMapsDatas( $maps, $label, $icon, $pieces  );

                    array_forget( $menu, [ 'label', 'allow', 'deny' ] );

                    $maps = $this->resoveCallback( $callback, $menu, $allows, $denys, $namespaces, $maps, $pieces );
                }
                else
                {
                    foreach ( $subMenu as $item )
                        $maps = $this->resoveCallback( $callback, $item, $allows, $denys, $namespaces, $maps, $pieces );
                }
            }
            else
            {
                /*如果没有label 则不需要添加*/
                if( array_has( $menu, 'label') == FALSE )
                    return $maps;

                /*判断该菜单是否有权限进入*/
                $hasAccess = $this->allowOrDeny( $allows, $denys );

                if( ! $hasAccess )
                    return $maps;

                $label = array_get( $menu, 'label' );

                list( $full_label ) = $this->implodeArrs( $pieces, '\\', $label );

                /*获取url*/
                list( $action,  ) = $this->implodeArrs( $namespaces, '\\', $menu['action'] );

                /*如果已经有相同菜单*/
                if( array_has( $maps, $full_label )  )
                {
                    $depth = array_get( $maps, $full_label );

                    return $this->setMapsCurrent( $maps, $action, $depth );
                }

                $icon = array_get( $menu, 'icon' );

                list( $maps ) = $this->setMapsDatas( $maps, $label, $icon, $pieces, $action );
            }
            return $maps;
        };
    }

    private function checkAccess( $keyAndAccess )
    {
        list( $keys, $access ) = array_divide( $keyAndAccess );

        $access = head( $access );

        $result = head( $keys ) == 'allow';

        $roles = \Auth::check() ? $this->normalize( session('roles') ) : [];

        if( count( array_intersect( $access, $roles ) ) )
            return $result;

        elseif ( in_array( self::ACL_EVERYONE, $access ) )
            return $result;

        elseif( in_array( self::ACL_HAS_ROLE, $access ) && count( $roles ) )
            return $result;

        elseif( in_array( self::ACL_NO_ROLE, $access ) && count( $roles ) == 0 )
            return $result;

        return !$result;
    }

    private function getAccessUseAction( $action )
    {
        $fullaction = $this->getFullAction( $action );

        $route = $this->getRoute()->getByAction( $fullaction );

        return $this->getAccessUseRoute( $route );
    }

    private function getAccessUseRoute( RoutingRoute $route )
    {
        $prefix = trim( $route->getPrefix(), '/' );

        $prefixs = strlen( $prefix ) ? array_filter( explode( '/', $prefix ) ) : [0];

        $uri = trim( str_replace( $prefix, '', $route->uri() ), '/' );

        return $this->resolveAccess( $prefixs, $uri );
    }

    private function getAccessUseUri( $uri )
    {
        $routes = $this->getRoute()->getRoutes();

        $route = array_first( $routes, function( $value ) use( $uri ){
            return str_is( $uri, $value->uri() );
        });
        return $this->getAccessUseRoute( $route );
    }

    private function getAllowAndDenyAccess( $target, $allows = [], $denys = [] )
    {
        $allows = $this->getAllowAccess( $target, $allows );

        $denys = $this->getDenyAccess( $target, $denys );

        return [ $allows, $denys ];
    }

    private function getAllowAndDenyAccessUseDepth( $depth, $key, $value, $allows, $denys )
    {
        $menu = $this->_MENUS;

        list( $target, $depth ) = $this->searchTargetUseDepth( $menu, $depth, $key, $value );

        if( !is_null( $target ) )
            list( $allows, $denys ) = $this->getAllowAndDenyAccess( $target, $allows, $denys );

        return [ $allows, $denys, $depth ];
    }

    private function getAllowAccess( $target, $allows )
    {
        return $this->getUseDepth( $target, $allows, [ 'allow' ] );
    }

    private function getDenyAccess( $target, $denys )
    {
        return $this->getUseDepth( $target, $denys, [ 'deny' ] );
    }

    private function getFullAction( $action )
    {
        $namespace = config( 'acl.default_namespace');

        return starts_with( $action, $namespace ) ? $action : $namespace . $action;
    }

    private function getNamespaces( $target, $namespaces = [], $depth = [] )
    {
        array_push( $depth, 'namespace' );

        return $this->getUseDepth( $target, $namespaces, $depth, FALSE );
    }

    private function getRoute()
    {
        return app()->make('routes');
    }

    private function getSidebars( $menus )
    {
        $results = [];

        foreach ( $menus as $prefix => $item )
            $results[ $prefix ] = $this->resolveSidebars( $item );

        return count( $results ) == 1 ?  head( $results ) : $results;
    }

    private function getUseDepth( $target, $destination, $depth, $normalize = true )
    {
        $full_path = implode( '.', $depth );

        if( $access = array_get( $target, $full_path ) )
        {
            if( $normalize )
                $access = $this->normalize( $access );

            array_push( $destination, $access );
        }
        return $destination;
    }

    private function implodeArrs( array $arr, $delimiter = '.', ...$appends )
    {
        if( count( $appends ) )
            $arr = array_merge( $arr, $appends );

        $result = implode( $delimiter, $arr );

        return empty( $appends ) ? $result : [ $result, $arr ];
    }

    private function isCurrent( $action )
    {
        $action = $this->getFullAction( $action );

        return Route::currentRouteUses( $action );
    }

    private function normalize( $str )
    {
        if( is_null( $str ) )
            return $str;

        return preg_split( '/,\s*/',  trim( strtolower( $str ) ));
    }

    private function resolveAccess( $prefixs, $uri )
    {
        $allows = $denys = $depth = [];

        foreach ( $prefixs as $prefix )
        {
            list( $allows, $denys, $depth ) = $this->getAllowAndDenyAccessUseDepth( $depth, 'prefix', $prefix, $allows, $denys );

            array_push( $depth, 'items' );
        }

        list( $allows, $denys ) = $this->getAllowAndDenyAccessUseDepth( $depth, 'uri', $uri, $allows, $denys );

        return $this->allowOrDeny( $allows, $denys );
    }

    private function resoveCallback( \Closure $callback, ...$parameters )
    {
        return call_user_func_array( $callback, $parameters );
    }

    private function resolveRoutes( Array $menu )
    {
        return function () use ($menu)
        {
            foreach ( $menu as $prefix => $values )
            {
                /*如果有prefix 则使用 Route::group*/
                if ( array_has( $values, 'prefix') || is_string( $prefix ) )
                {
                    $prefix = array_has( $values, 'prefix' ) ? array_get( $values, 'prefix' ) : $prefix;

                    array_forget( $values, 'prefix' );

                    if( array_has( $values, 'items') )
                    {
                        $params = compact( 'prefix' );

                        if( $namespace = array_get( $values, 'namespace') )
                            $params['namespace'] = $namespace;

                        if( $middleware = array_get( $values, 'middleware') )
                            $params['middleware'] = $middleware;

                        //添加自定义的属性
                        $attributes = array_merge( $this->_GROUP_ATTRIBUTES, array_get( $values, 'attributes', [] ) );

                        $attributes = array_diff( $attributes, array_keys( $params ) );

                        foreach ( $attributes as $attribute )
                        {
                            if( $value = array_get( $values, $attribute ) )
                                $params[ $attribute ] = $value;
                        }

                        Route::group($params, $this->resolveRoutes( $this->wrap( $values ) ));
                    }
                    else
                        $this->resoveCallback( $this->resolveRoutes( $this->wrap( $values ) ) );
                }

                /*如果有子菜单*/
                elseif ( $items = array_get( $values, 'items') )
                    $this->resoveCallback( $this->resolveRoutes( $items ) );

                /*如果没有子菜单*/
                /*Case 0: use DEFAULT_METHODS*/
                elseif( array_has( $values, 'method') == FALSE )
                    $route = Route::match( $this->_DEFAULT_METHODS, $values['uri'], $values['action'] );

                /*Case 1: method: ['get','post',...]*/
                elseif ( is_array( $values['method'] ))
                    $route = Route::match( $values['method'], $values['uri'], $values['action'] );

                /*Case 2: method: 'post,get,...'*/
                elseif ( is_string( $values['method'] ) && str_contains( $values['method'], ',' ) )
                    $route = Route::match( $this->normalize( $values['method'] ), $values['uri'], $values['action'] );

                /*Case 3: method: all*/
                elseif ( is_string( $values['method'] ) && str_is( 'all', $values['method'] ))
                    $route = Route::any( $values['uri'], $values['action']);
                /*Case 4: method: post|get|...*/
                else
                    $route = Route::{$values['method']}( $values['uri'], $values['action'] );


                if( isset( $route ) && $name = array_get( $values, 'name') )
                    $route->name( $name );
            }
        };
    }

    private function resolveSidebars( $menu )
    {
        $callback  = $this->analysisMenuForSiderbar();

        $maps = $this->resoveCallback( $callback, $menu );

        return array_where( head( $maps ), function( $item ){
            return array_has( $item, 'subs') ? count( $item['subs'] ) : true;
        });
    }

    private function searchTargetUseDepth( $target, $depth, $key, $value )
    {
        list( $full_path, $depth  )= $this->implodeArrs( $depth, '.', $value );

        if( $result = array_get( $target, $full_path ) )
            return [ $result, $depth ];
        else
        {
            array_pop( $depth );

            $full_path = $this->implodeArrs( $depth );

            $target = array_get( $target, $full_path );

            if( is_null( $target ) )
                return [ null, $depth ];

            $selected = null;

            foreach ( $target as $index => $item )
            {
                if( array_get( $item, $key ) == $value )
                {
                    $selected = $index;
                    break;
                }
            }

            if( is_null( $selected ) == FALSE )
            {
                array_push( $depth, $selected );

                return [ $target[ $selected ], $depth ];
            }
        }
    }
    private function setMapsDatas( $maps, $label, $icon, $pieces, $action = null )
    {
        if( array_has( $maps, $label ) && is_null( $action ) )
        {
            $depth = array_get( $maps , $label );

            list( $push_path, $depth ) = $this->implodeArrs( $depth, '.', 'subs' );
        }
        else
        {
            $depth = count( $pieces ) ? array_get( $maps, $this->implodeArrs( $pieces, '\\' ) ) : [0];

            $target_path = $this->implodeArrs( $depth );

            $len = count( array_get( $maps, $target_path ) );

            list( $push_path, $depth ) = $this->implodeArrs( $depth, '.', $len );

            list( $full_label ) = $this->implodeArrs( $pieces, '\\', $label );

            $append = compact( 'label', 'icon' );

            if( is_null( $action ) )
            {
                $append['subs'] = [];

                array_push( $depth , 'subs' );
            }

            $append['url'] = is_null( $action ) ? '' : URL::action( $action );

            array_set( $maps, $push_path, $append );

            $maps[ $full_label ] = $depth;

            if( $action )
                $maps = $this->setMapsCurrent( $maps, $action, $depth );
        }
        array_push( $pieces, $label );

        return [ $maps, $pieces ];
    }

    private function setMapsCurrent( $maps, $action, $depth )
    {
        if( $is_current = $this->isCurrent( $action ) )
        {
            $count = count( $depth ) - 1;

            for( $i = 0; $i < $count; $i++ )
            {
                $pop = array_pop( $depth );

                if( strcmp( $pop, 'subs' ) == 0 )
                    continue;

                list( $push_path, ) = $this->implodeArrs( $depth, '.', $pop, 'is_current' );

                array_set( $maps, $push_path, $is_current );
            }
        }
        return $maps;
    }

    private function wrap( $value )
    {
        if( is_null( $value ) )
            return [];

        return [ $value ];
    }
}