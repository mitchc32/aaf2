<?php

namespace AAF\Http;

/**
 * Input
 * 
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class Input {
	
	public static $loaded = false;
	public static $input = array();
	public static $blacklist = array('applet', 'body', 'bgsound', 'base', 'basefont', 'embed', 'frame', 'frameset', 'head', 'html', 'id', 'iframe', 'ilayer', 'layer', 'link', 'meta', 'name', 'object', 'script', 'style', 'title', 'xml');
	public static $baseURI = '';
	
	/**
	 * Input::load()
	 * 
	 * Load and filter out the global $_REQUEST variable.
	 * 
	 * @return mixed
	 */
	public static function load() {
		/* stop if already processed */
		if (self::$loaded) {
			return self::$input;
		}
		
		/* flag it as called */
		self::$loaded = true;

		/* load the request variables */
		self::$input = (!empty($_REQUEST)) ? self::clean($_REQUEST) : array();
		
		/* done */
		return self::$input;
	}
	
	/**
	 * Input::clean()
	 * 
	 * Clean out blacklisted html tags.
	 * 
	 * @param mixed $obj
	 * @return mixed
	 */
	public static function clean($obj) {
		/* ensure we have a string */
		switch (true) {
			case (empty($obj) && (string) $obj != '0'):
				return '';
				break;
			
			case (is_array($obj)):
				return self::cleanArray($obj);
				break;
			
			case (!is_string($obj)):
				return $obj;
				break;
		}
		
		/* set defaults */
		$obj = urldecode($obj);
		$matches = array();
		$tags = array();
		$allow = array();
		
		/* get a list of all the tags the field contains */
		preg_match_all('/<([a-z0-9]+)/i', $obj, $matches);
		
		/* stop if non were found */
		if (empty($matches[0])) {
			return $obj;
		}
		
		/* build the tag list */
		$tags = array_unique($matches[1]);
		
		/* get rid of the blacklisted items */
		$tags = array_diff($tags, self::$blacklist);
		
		/* create the list of allowable tags */
		foreach ($tags as $tag) {
			$allow[] = "<$tag><$tag/><$tag />";
		}
		
		/* clean it up */
		return strip_tags($obj, implode('',$allow));
	}
	
	/**
	 * Input::cleanArray()
	 * 
	 * Recursive function to dig into arrays.
	 * 
	 * @param mixed $vars
	 * @return mixed
	 */
	public static function cleanArray($vars) {
		/* stop if not an array or empty */
		if (!is_array($vars) || empty($vars)) {
			return $vars;
		}
		
		/* process the array */
		foreach ($vars as $k=>$v) {
			if (is_array($v)) {
				$vars[$k] = self::cleanArray($v);
			} else {
				$vars[$k] = self::clean($v);
			}
		}
		
		return $vars;
	}
	
	/**
	 * Input::getBaseUrl()
	 * 
	 * Get the base url without a query string.
	 * 
	 * @return string
	 */
	public static function getBaseUrl() {
		/* check for a query string */
		$queryStart = strpos($_SERVER['REQUEST_URI'], '?');
		
		/* send back the url without a query string */
		return ($queryStart !== false) ? substr($_SERVER['REQUEST_URI'], 0, $queryStart) : $_SERVER['REQUEST_URI'];
	}
	
	/**
	 * Input::_parseURL()
	 * 
	 * Break the URL into parts.
	 * 
	 * @example /my/request/url/test,123,321?test=1 = array(0=>'my', 1=>'request', 2=>'url', 'test'=>array(0=>123, 1=>321))
	 * @return mixed
	 */
	protected static function _parseURL() {
		/* stop if not available */
		if (!isset($_SERVER['REQUEST_URI'])) {
			return array();
		}
		
		/* set defaults */
		$clean = array();
		self::$baseURI = self::getBaseUrl();
		
		/* break up the sections */
		$parts = (self::$baseURI == '' || self::$baseURI == '/') ? array() : explode('/', self::$baseURI);
		
		/* remove the empty first item */
		array_shift($parts);
		
		/* process the set */
		foreach ($parts as $part) {
			/* see how we should treat it */
			switch (true) {
				case (strpos($part, ',')):
					/* split it up */
					$set = explode(',', $part);
					$key = array_shift($set);
					
					/* clean it */
					$clean[$key] = self::clean($set);
					break;
				
				default:
					$clean[] = self::clean($part);
					break;
			}
		}
		
		/* done */
		return $clean;
	}
	
}

