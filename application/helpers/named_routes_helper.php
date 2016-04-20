<?php
/**
 * User: Amirali
 * Date: 4/19/2016
 * Time: 6:26 PM
 */

if ( ! function_exists('generate_named_route_with_values()') )
{
    function generate_named_route_with_values($routeKey, $routeValues)
    {
        // Route key can be something that contains name and type
        // Like: /AnExample/(num:num)/OfRoute/(id:num)
        if (preg_match_all('/\(([^:]*):(?:num|any)\)/i', $routeKey, $matches) !== FALSE)
        {
            $routeMatches = $matches[0];
            $routeParamNames = $matches[1];

            for ($i = 0; $i < count($routeMatches); $i++)
            {
                $routeKey = str_replace($routeMatches[$i], $routeValues[$routeParamNames[$i]], $routeKey);
            }
        }

        return $routeKey;
    }
}

if ( ! function_exists('route()') )
{
    function route($routeName, $routeParams)
    {
        if (!(isset($routes) && is_array($routes)))
        {
            $routes = [];
        }

        if (file_exists(APPPATH.'config/routes.php'))
        {
            include(APPPATH.'config/routes.php');
        }

        if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/routes.php'))
        {
            include(APPPATH.'config/'.ENVIRONMENT.'/routes.php');
        }

        $neededObject = array_filter(
            $routes,
            function ($e) use (&$routeName)
            {
                if (is_array($e) && array_key_exists('as', $e))
                {
                    return (strncmp($e['as'], $routeName, strlen($e['as'])) == 0);
                }

                return false;
            }
        );

        if (count($neededObject) > 1)
        {
            throw new Exception('Ambiguity found in named routes, please don\'t use same names for two or more routes. Check route name: ' . current($neededObject)['as']);
        }
        else if (count($neededObject) == 1)
        {
            return generate_named_route_with_values(key($neededObject), $routeParams);
        }
        else
        {
            return false;
        }
    }
}
