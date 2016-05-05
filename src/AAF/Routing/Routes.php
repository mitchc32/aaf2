<?php

namespace AAF\Routing;

use AAF\App as App;
use AAF\Security\User as User;
use AAF\Http\Response as Response;
use AAF\Exceptions\RouteException as RouteException;
use AAF\Controllers\Plugin as Plugin;

/**
 * Routes
 * 
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class Routes {
	
	/**
	 * @var mixed $routes the list of added routes to check
	 */
	public static $routes = [];
	
	/**
	 * @var mixed $beforeQueue the list of callables to execute before a route is processed
	 */
	public static $beforeQueue = [];
	
	/**
	 * @var mixed $afterQueue the list of callables to execute after a route is processed
	 */
	public static $afterQueue = [];
	
	/**
	 * Routes::loadFile()
	 * 
	 * Load a routes config file. Valid files are JSON as described below, or a similarly
	 * formatted PHP array with the array going into a variable called "$routes".
	 * 
	 * @param string $file
	 * @return void
	 */
	public static function loadFile($file) {
		/* stop if empty */
		if (empty($file)) {
			throw new RouteException('Empty routes file provided.');
		}
		
		/* make sure the file exists and can be read */
		if (!file_exists($file) || !is_readable($file)) {
			throw new RouteException('Routes file, '.$file.', either does not exist or is not readable.');
		}
		
		/* get the file type based on the extension */
		$type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		
		/* handle it based on type */
		switch ($type) {
			case 'json':
				self::loadJSON(file_get_contents($file));
				break;
			
			case 'php':
				/* include the file */
				require $file;
				
				/* look for a new variable called "$routes" - this is optional
				so there is no need to throw an error if it isn't so */
				if (isset($routes) && !empty($routes) && is_array($routes)) {
					foreach ($routes as $url=>$opts) {
						/* validate */
						if (empty($url) || empty($opts) || !App::valid('handler', $opts)) {
							throw new RouteException('Invalid route provided for "'.$url.'"');
						}
						
						/* add it */
						self::add($url, $opts['handler'], $opts);
					}
				}
				break;
			
			default:
				throw new RouteException('Invalid routes file type provided. Only PHP and JSON are accepted.');
				break;
		}
	}
	
	/**
	 * Routes::loadJSON()
	 * 
	 * Load a JSON string of routes. The string should be an object with each top-level key
	 * holding a url. Each URL should have an object with additional information.
	 * 
	 * { "test": { "url":"/test", "handler": "Handler.php", "action":"main" } }
	 * 
	 * URL Options:
	 * 
	 * - Wildcards use * such as /* or /test/* for all URLs (great for catch-all routes)
	 * - Dynamic actions use {_action} placeholder such as /test/{_action}
	 * - Named params will be passed to the handler method use {...} such as /events/{date}/{title}
	 * 
	 * URL/Path Options:
	 *
	 * - url: url path to attach to
	 * - handler: class file to handle the request
	 * - action: the specific method on the handler to call (optional)
	 * - method: any/get/post/put/delete (optional)
	 * - security: role name or array of role names (optional)
	 * 
	 * @param string $json
	 * @return void
	 */
	public static function loadJSON($json) {
		/* make sure the contents aren't empty */
		if (empty($json) || !is_string($json)) {
			throw new RouteException('Invalid routes JSON string provided.');
		}
		
		/* try to parse it */
		$json = json_decode($json, true);
		
		/* validate the data */
		if (empty($json)) {
			throw new RouteException('Could not parse the provided routes JSON string.');
		}
		
		/* load the routes */
		foreach ($json as $name=>$opts) {
			/* validate */
			if (!App::valid('url', $opts) || empty($opts) || !App::valid('handler', $opts)) {
				throw new RouteException('Invalid route provided for "'.$name.'"');
			}
			
			/* add it */
			self::add($name, $opts['url'], $opts['handler'], $opts);
		}
	}
	
	/**
	 * Routes::add()
	 * 
	 * Add a route to the list/set. The handler provided can be any callable PHP function
	 * or method. It can also be a string reference to a handler as with the JSON format.
	 * 
	 * URL Options:
	 * 
	 * - Wildcards use * such as /* or /test/* for all URLs (great for catch-all routes)
	 * - Dynamic actions use {_action} placeholder such as /test/{_action}
	 * - Named params will be passed to the handler method use {...} such as /events/{date}/{title}
	 * 
	 * Available Options:
	 *
	 * - action: the specific method on the handler to call (optional)
	 * - method: any/get/post/put/delete (optional)
	 * - security: role_name (optional)
	 *
	 * @param string $name route name/id
	 * @param string $route url
	 * @param mixed $handler
	 * @param mixed $opts
	 * @return bool
	 */
	public static function add($name, $route, $handler, $opts=[]) {
		/* validate the route */
		if (empty($route) || !is_string($route)) {
			throw new RouteException('Invalid route provided. Route URLs must be a string and at least one character.');
		}
		
		/* validate the handler */
		if (!is_string($handler) && !is_callable($handler)) {
			throw new RouteException('Invalid route handler provided for '.$route.'. Handlers must be either a callable function or a string reference to a plugin.');
		}
		
		/* create the regex to match the url for the route */
		$opts['regex'] = '^'.preg_replace(['/\*/', '/\/{.*}/', '~(/?\.\*){2,}~', '~/{2,}~'], ['.*', '/?.*', '/?.*', '/'], $route).'$';
		
		/* add some final items */
		$opts['route'] = $route;
		$opts['handler'] = $handler;
		
		/* add it to the routes list */
		self::$routes[$name] = $opts;
		
		/* done */
		return true;
	}
	
	/**
	 * Routes::addBeforeExecute()
	 * 
	 * Add a function to be executed once a route has been matched, but not processed. Each
	 * callback should accept a single parameter for the route. The response is not used
	 * or logged.
	 * 
	 * function($route) { ... }
	 * 
	 * @param mixed $callback should be an annonymous function
	 * @return void
	 */
	public static function addBeforeExecute($callback) {
		/* validate the callback */
		if (!is_callable($callback)) {
			throw new RouteException('Before queue provided callback is not callable.');
		}
		
		/* add it to the queue */
		self::$beforeQueue[] = $callback;
	}
	
	/**
	 * Routes::addAfterExecute()
	 * 
	 * Add a function to be executed once a route has been matched and processed. Each
	 * callback should accept two parameters: the route and the route response. If the
	 * response is returned, it will be passed along to the following routes until passed
	 * back to the caller through the runUrl method.
	 * 
	 * function($route, $response) { ... }
	 * 
	 * @param mixed $callback should be an annonymous function
	 * @return void
	 */
	public static function addAfterExecute($callback) {
		/* validate the callback */
		if (!is_callable($callback)) {
			throw new RouteException('After queue provided callback is not callable.');
		}
		
		/* add it to the queue */
		self::$afterQueue[] = $callback;
	}
	
	/**
	 * Routes::runRequestUrl()
	 * 
	 * Run the current requested URL ($_SERVER['REQUEST_URI']) against the list of
	 * added routes.
	 * 
	 * @return string response
	 */
	public static function runRequestUrl() {
		/* set the default url from the server */
		$url = $_SERVER['REQUEST_URI'];
		
		/* strip off any query string or hash */
		$url = preg_replace(['/(\?.*|\#.*)/i'], [''], $url);
		
		/* remove any trailing slash */
		$url = rtrim($url, '/');
		
		/* run the sanitized url */
		return self::runUrl($url);
	}
	
	/**
	 * Routes::runUrl()
	 * 
	 * Run the provided URL against the added routes. If a route is matched, the
	 * before queue will be processed, then the route processed and then the after
	 * queue will be processed.
	 * 
	 * @param mixed $url
	 * @return string response
	 */
	public static function runUrl($url) {
		/* make sure we have some routes */
		if (empty(self::$routes)) {
			throw new RouteException('No routes have been defined for the application.');
		}
		
		/* check each */
		foreach (self::$routes as $route) {
			/* stop here if we do not have a match */
			if (self::_checkRoute($url, $route)) {
				return self::_executeRoute($url, $route);
			}
		}
		
		/* send back a 404 error because we did not match a route */
		return Response::getError(404);
	}
	
	/**
	 * Routes::_checkRoute()
	 * 
	 * Validate a route against the provided URL for a match.
	 *
	 * @param string $url to check the route for
	 * @param mixed $route
	 * @return bool
	 */
	protected static function _checkRoute($url, $route) {
		/* validate the request method */
		if (App::valid('method', $route) && strtolower(trim($route['method'])) != strtolower(trim($_SERVER['REQUEST_METHOD']))) {
			return false;
		}

		/* check the route */
		return preg_match('~'.$route['regex'].'~i', $url);
	}
	
	/**
	 * Routes::_executeRoute()
	 * 
	 * Execute the matched route.
	 * 
	 * @param string $url to execute the route against
	 * @param mixed $route the matched route
	 * @return string response
	 */
	protected static function _executeRoute($url, $route) {
		/* set defaults */
		$params = self::_getParamsFromUrlForRoute($url, $route);

		/* set the base URL to be used in the handler */
		Response::$url = preg_replace(['/(\/?{.*?}.*)/'], [''], $route['route']);
		
		/* check for a security flag on the route against the user's session */
		if (App::valid('security', $route) && !User::isAuthorized($route['security'])) {
			return Response::getError(503);
		}

		/* process the before queue */
		self::_executeBeforeQueue($route);

		/* below we want to make sure that the route always has an action value set,
		so we'll check to see if one was provided in the params or in the action config
		before defaulting it to the plugin default action */
		if (App::valid('_action', $params)) {
			/* set the param action to overwrite the route action */
			$route['action'] = $params['_action'];
			
			/* remove the action from the list of params */
			unset($params['_action']);
		} elseif (!App::valid('action', $route)) {
			$route['action'] = '_default';
		}
		
		/* run the route */
		$response = self::_processHandler($route, $params);
		
		/* execute the after queue */
		$content = self::_executeAfterQueue($route, $response);
		if (!empty($content) && is_string($content)) {
			$response = $content;
		}
		
		/* wrap up */
		return $response;
	}
	
	/**
	 * Routes::_executeBeforeQueue()
	 * 
	 * Process the full before queue.
	 * 
	 * @param mixed $route
	 * @return bool
	 */
	protected static function _executeBeforeQueue($route) {
		/* stop here if the queue is empty */
		if (empty(self::$beforeQueue)) {
			return false;
		}
		
		/* process the list */
		foreach (self::$beforeQueue as $f) {
			$f($route);
		}
		
		return true;
	}
	
	/**
	 * Routes::_executeAfterQueue()
	 * 
	 * Process the full after queue. The response is ignored if it is not a string
	 * or if it is empty.
	 * 
	 * @param mixed $route
	 * @return string response
	 */
	protected static function _executeAfterQueue($route, $response) {
		/* stop here if the queue is empty */
		if (empty(self::$afterQueue)) {
			return $response;
		}
		
		/* process the list */
		foreach (self::$afterQueue as $f) {
			$sub = $f($route, $response);
			
			if (!empty($sub)) {
				$response = $sub;
			}
		}
		
		/* send back the change */
		return $response;
	}
	
	/**
	 * Routes::_processHandler()
	 * 
	 * Proccess the route handler. Handlers can be a callable function, reference
	 * to a plugin or other class.
	 * 
	 * @param mixed $route
	 * @param mixed $params from the url
	 * @return string response
	 */
	protected static function _processHandler($route, $params) {
		/* process the handler based on the type */
		switch (true) {
			/* handle anon functions */
			case (is_callable($route['handler'])):
				return (string) call_user_func_array($route['handler'], $params);
				break;
			
			/* handle class references and plugins */
			case (is_string($route['handler']) && !empty($route['handler'])):
				/* use the plugin factory method to create an instance of the handler
				using the route details */
				$plugin = Plugin::create($route['handler'], App::get('opts', $route));
				
				/* Get the action from the route, but make sure to remove any special
				characters from it in case this came from the URL. We want to make sure
				that "/this-method-name" maps to "this_method_name" for pretty URLs and
				SEO reasons. */
				$m = preg_replace('/[\-\s]/i', '_', App::get('action', $route));
				
				/* check that the action isn't empty and exists in the class before
				trying execute it. */
				if (!empty($m) && method_exists($plugin, $m)) {
					return call_user_func_array(array($plugin, $m), $params);
				}
				
				/* call the default method */
				return (string) $plugin->_default();
				break;
			
			/* error */
			default:
				return Response::getError(500);
				break;
		}
	}
	
	/**
	 * Routes::_getParamsFromUrlForRoute()
	 * 
	 * This method is responsible for pulling the list of variable parameters that
	 * are defined in the route from the request URL.
	 * 
	 * Example Route: 	/test/{_action}/{id}
	 * Example URL:		/test/method/123
	 * Example Result:	[_action => method, id => 123]
	 * 
	 * @param string $url
	 * @param mixed $route
	 * @return mixed list of params for the route
	 */
	protected static function _getParamsFromUrlForRoute($url, $route) {
		/* set defaults */
		$params = [];
		$pMatches = [];
		$uMatches = [];
		
		/* parse the route for any variables */
		preg_match_all('~/{(.*?)}~i', $route['route'], $pMatches);
		
		/* stop here if empty */
		if (empty($pMatches[1])) {
			return [];
		}
		
		/* convert the route to a regex to pull the params by name */
		$regex = preg_replace(['~/{.*?}~i'], ['/?([^/]*)'], $route['route']);
		
		/* run the regex */
		preg_match_all('~'.$regex.'~i', $url, $uMatches);
		
		/* build the params list */
		foreach ($pMatches[1] as $i=>$v) {
			/* get the match set */
			$set = App::get(($i + 1), $uMatches);
			
			/* set the named parameter */
			$params[$v] = App::get(0, $set);
		}
		
		/* done */
		return $params;
	}
	
}