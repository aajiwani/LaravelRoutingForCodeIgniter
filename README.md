# LaravelRoutingForCodeIgniter
Laravel Style routing capabilities for Code Igniter. It adds abstraction to the whole routing process.

## Motivation to use it ##
* In /application/config/routes.php
```php
  $route_config['default_controller'] = 'welcome';
  $route_config['404_override'] = '';
  $route_config['translate_uri_dashes'] = FALSE;

  // New Routes
  $routes['greet/(name:any)']           = ['as' => 'greet_someone', 'uses' => 'welcome@greet'];
  // where Welcome is the Controller and greet is the Method
```

* In code
```php
  $this->load->helper('named_routes');

  redirect(site_url(
    route('greet_someone', [
      'name'  => 'John'
    ]);
  ));
```


### Steps to let the magic begin ###
1. Replace snippets under /system/core/CodeIgniter.php
  * Line (480 - 484)
  * Line (488 - 519)
2. Place Router.php under /application/core
3. Place named_routes_helper.php under /application/helpers
4. If you want, you can let the helper be auto-loaded

### Steps to produce new routes ###
1. In routes.php don't use $route variable any more, instead replace it with the name $routes
2. default_controller, 404_override, translate_uri_dashes keys must be placed under $route_config, previously under $route
3. There are two ways to express routes
  * To be used as named routes, it must have `as` key and `uses` key like: 
    * $routes['greet/(name:any)'] = ['as' => 'greet_someone', 'uses' => 'welcome@greet'];
    * where `as` key represents the name of the route
    * where `uses` key represents the method to be used for this route
    * notice that variable isn't supplied in the `uses` key explained in the next step, also introduction of @ symbol
    * also notice that the key now has named variable as well
      * P.S. You don't need to use numbers as variable orders like $1, $2 anymore
      * instead named params would supply required variables to method accordingly, I am not joking look at the example
  * You can use routes the old way as well but with proper named variables and @ symbol
    * controllerName@method is the new convention
    * key for routes must contain names of the variables
    * P.S. You don't need to use numbers as variable orders like $1, $2 anymore
    * instead named params would supply required variables to method accordingly, I am not joking look at the example
4. To use routes helper to form the routes on the go here is the way
  * route('greet_with_full_name', ['firstname' => 'John', 'lastname' => 'Doe']);
    * this will get the route with `as` key as `greet_with_full_name`
    * replace the named variables with parameters supplied i.e. firstname and lastname accordingly
    * You can have a look in the example to see how it works
    * You can use the output from route as an input of siteurl and produce working urls

It was a need for me, hence I created it. If you think it could be improved, just ping me.