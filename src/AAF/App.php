<?php

namespace AAF;

use AAF\Http\Input as Input;
use AAF\Routing\Routes as Routes;

/**
 * App
 * 
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class App {
	
	/**
	 * @var mixed $env Environment config array/details
	 */
	public static $env = [
		'timezone'				=> 'America/Chicago',
		'sessionExpires'		=> '+6 hour',
		'defaultHandlerPath'	=> 'plugins/',
		'forceAssetInjection'	=> false,
		'assetBasePath'			=> ''
	];
	
	/**
	 * @var mixed $input GET/POST input from the $_REQUEST superglobal and filtered for HTML content
	 */
	public static $input = [];
	
	/**
	 * App::create()
	 * 
	 * Setup the application environment based on the provided config file and the
	 * referenced environment key. A config file should be a JSON file with at
	 * least an "all" key. Each environment should have a key with properties that
	 * will be merged with those in "all".
	 * 
	 * @param mixed $configFile
	 * @param string $envKey
	 * @return void
	 */
	public static function create($configFile, $envKey='dev') {
		/* check the file exists */
		if (!file_exists($configFile)) {
			throw new \Exception('Invalid config file provided.');
		}
		
		/* try to parse it */
		$json = json_decode(file_get_contents($configFile), true);
		if (empty($json)) {
			throw new \Exception('Could not parse the config file as JSON.');
		}
		
		/* check that there is at least an "all" or the $envKey */
		if (!isset($json['all']) && !isset($json[$envKey])) {
			throw new \Exception('Invalid config properties. Please ensure you have a property of "all" and/or "'.$envKey.'"');
		}
		
		/* standardize both keys */
		foreach (['all', $envKey] as $k) {
			if (!self::valid($k, $json)) {
				$json[$k] = [];
			} elseif (!is_array($json[$k]) || empty($json[$k])) {
				$json[$k] = [];
			}
		}
		
		/* try to merge the all and $envKey */
		self::$env = array_merge(self::$env, $json['all'], $json[$envKey]);
		
		/* set the timezone */
		date_default_timezone_set(self::$env['timezone']);
		
		/* process the input */
		self::$input = Input::load();
		
		/* load the routes */
		if (self::valid('routes', self::$env)) {
			Routes::loadFile(self::$env['routes']);
		}
	}
	
	/**
	 * App::dump()
	 * 
	 * Get a full explination of the passed in variable.
	 * 
	 * @param mixed $obj
	 * @return string
	 */
	public static function dump($obj) {
		return '<pre>'.print_r($obj, true).'</pre>';
	}
	
	/**
	 * App::valid()
	 * 
	 * Check if the provided variables are valid in the provided array. By "valid"
	 * we imply that the key exists and is not an empty string.
	 * 
	 * @param mixed $vars string key or array of keys
	 * @param mixed $set array of key/value pairs
	 * @return bool
	 */
	public static function valid($vars, $set=array()) {
		/* if set is empty or not an array, then use the input */
		$set = (empty($set) || !is_array($set)) ? self::$input : $set;
		$keys = (!is_array($vars)) ? array($vars) : $vars;
		$valid = true;
		
		/* check the value */
		foreach ($keys as $key) {
			$key = (string) $key;
			
			if (!isset($set[$key]) || (is_string($set[$key]) && strlen($set[$key]) == 0) || (is_array($set[$key]) && empty($set[$key]))) {
				$valid = false;
			}
		}
		
		/* return the valid */
		return $valid;
	}
	
	/**
	 * App::get()
	 * 
	 * Get the value of a key or an empty string.
	 * 
	 * @param stirng $var key to try and find
	 * @param mixed $set array of key/value pairs; defaults to App::$input if empty
	 * @return mixed value or empty string
	 */
	public static function get($var, $set=array()) {
		if (is_object($var) || is_array($var)) {
			return '';
		}
		
		$set = (!is_array($set) || empty($set)) ? self::$input : $set;
		
		return (isset($set[$var])) ? $set[$var] : '';
	}
	
	/**
	 * App::autoload()
	 * 
	 * Autoload framework classes.
	 * 
	 * @param mixed $className
	 * @return void
	 */
	public static function autoload($className) {
		/* crete a full path to the class */
		$file = __DIR__.'/'.preg_replace(['/^AAF\\\/', '/\\\/'], ['', '/'], ltrim($className, '\\')).'.php';
		
		/* check for existance */
		if (!file_exists($file)) {
			throw new \Exception('Could not find class '.$className);
		}
		
		/* include */
		require_once $file;
	}
	
	/**
	 * App::success()
	 * 
	 * Return a standardized success.
	 * 
	 * @param mixed $data to return
	 * @param bool $json to return as a json string or php array
	 * @return mixed
	 */
	public static function success($data=array(), $json=false) {
		$resp = array('error'=>false, 'msg'=>'', 'data'=>$data);
		return ($json) ? json_encode($resp) : $resp;
	}
	
	/**
	 * App::error()
	 * 
	 * Return a standardized error.
	 * 
	 * @param string $msg string detailing the error
	 * @param bool $json to return as a json string or php array
	 * @param mixed $data to pass back with the error
	 * @return mixed
	 */
	public static function error($msg='', $json=false, $data=array()) {
		$resp = array('error'=>true, 'msg'=>$msg, 'data'=>$data);
		return ($json) ? json_encode($resp) : $resp;
	}
	
}