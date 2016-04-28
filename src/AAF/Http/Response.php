<?php

namespace AAF\Http;

use AAF\App as App;
use AAF\Routing\Routes as Routes;

/**
 * Response
 * 
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class Response {
	
	/**
	 * @var string $url is the base URL to use with links and other things to reference this same route/path
	 */
	public static $url = '';
	
	/**
	 * @var mixed $assets list of external assets to be injected into the response
	 */
	public static $assets = [
		'js'		=> [], // external javascript files by path /js/test.js
		'css'		=> [], // external stylesheet files by path /css/test.css
		'script'	=> '', // inline script to add to the bottom of the page - will be wrapped in script tags automatically
		'style' 	=> ''  // inline stylesheets to add to the bottom of the head - will be wrapped in style tags automatically
	];
	
	/**
	 * Response::create()
	 * 
	 * Generate the response from the provided list of routes using the the URL
	 * from $_SERVER['REQUEST_URI'].
	 * 
	 * @return string content
	 */
	public static function create() {
		/* process the route */
		$content = Routes::runRequestUrl();
		
		/* inject the css, js, scripts and styles */
		$content = self::_injectAssets($content);
		
		/* show the content */
		echo $content;
	}
	
	/**
	 * Response::addAssets()
	 * 
	 * Add references to one or more external js or css file. If the URL does not
	 * contain an extension, you will need to include the type as the second
	 * parameter. CSS links will be included before the closing head tag (if
	 * found), while JS scripts will be included before the closing body tag (if
	 * found).
	 * 
	 * This method is intended to be used with plugins that are processed as part
	 * of the template instead of through a route. For example, a plugin could be
	 * created to generate the dynamica navigation of a website. This plugin may
	 * a specific javascript or css file that it requires in certain instances.
	 * This will allow the plugin to include the required assets without having
	 * to put thing in the base templates on every page.
	 * 
	 * @param mixed $files
	 * @param string $type js or css; only required if extension not included
	 * @return void
	 */
	public static function addAssets($files, $type='') {
		/* set defaults */
		$type = strtolower(trim($type));
		$base = App::$env['assetBasePath'];
		$list = array();
		
		/* standardize the $files property so that it is always an array of files to
		iterate over in cases where a string is provided */
		$files = (is_string($files)) ? array($files) : $files;
		
		/* process the list of files */
		foreach ($files as $file) {
			/* get the info */
			$info = pathinfo($file);
			$host = parse_url($file, PHP_URL_HOST);
			
			/* check to see if we need to add in a domain to the file url */
			$file = (empty($host)) ? $base.'/'.ltrim($file, '/') : $file;
			
			/* if a specific type is provided, use that. otherwise use the file
			extension as the default */
			$fType = (!empty($type)) ? $type : strtolower(trim($info['extension']));
			
			/* check to make sure we don't add duplicates to the list */
			if (isset(self::$assets[$fType][$file]) || isset($list[$fType][$file])) {
				continue;
			}
			
			/* add it to the final list of files to be included */
			$list[$fType][$file] = true;
		}
		
		/* add in the list */
		foreach ($list as $k=>$v) {
			self::$assets[$k] = array_merge(self::$assets[$k], $v);
		}
		
		/* done */
		return true;
	}
	
	/**
	 * Response::addScript()
	 * 
	 * Add some inline javascript to the response. These will be automatically
	 * appended to the end of the body of the output (if found). All scripts
	 * will be wrapped in a single script tag.
	 * 
	 * @param string $script
	 * @return bool
	 */
	public static function addScript($script) {
		/* make sure it isn't empty */
		if (empty($script)) {
			return false;
		}
		
		/* strip out any script tags */
		$script = preg_replace(['\<script\s*.*?\>', '/\<\/script\>/'], ['', ''], $script);
		
		/* append to the list */
		self::$assets['script'] .= "\n".$script."\n";
		
		/* done */
		return true;
	}
	
	/**
	 * Response::addStyle()
	 * 
	 * Add some inline stylesheets to the response. These styles are automatically
	 * appended to the head of the output (if found). All styles will be wrapped
	 * in a single style tag.
	 * 
	 * @param string $style
	 * @return void
	 */
	public static function addStyle($style) {
		/* make sure it isn't empty */
		if (empty($style)) {
			return false;
		}
		
		/* strip out any script tags */
		$style = preg_replace(['\<style\s*.*?\>', '/\<\/style\>/'], ['', ''], $style);
		
		/* append to the list */
		self::$assets['styles'] .= "\n".$style."\n";
		
		/* done */
		return true;
	}
	
	/**
	 * Response::getError()
	 * 
	 * Get an error page with the provided HTTP Status Code.
	 * 
	 * @param integer $httpCode
	 * @param string $msg error details/message
	 * @return string
	 */
	public static function getError($httpCode=404, $msg='') {
		/* set the code */
		http_response_code((int) $httpCode);
		
		/* set the content */
		return '<h1>Oh no! '.$httpCode.'!</h1><p>'.$msg.'</p>';
	}
	
	/**
	 * Response::_injectAssets()
	 * 
	 * Inject the assets into the provided content.
	 * 
	 * @param string $content
	 * @return string content
	 */
	protected static function _injectAssets($content) {
		/* set defaults */
		$force = App::$env['forceAssetInjection'];
		$js = '';
		$css = '';
		
		/* create the js */
		if (!empty(self::$assets['js']) && is_array(self::$assets['js'])) {
			foreach(self::$assets['js'] as $path => $v) {
				$js .= "<script src=\"$path\"></script>\n";
			}
		}
		
		if (!empty(self::$assets['script']) && is_string(self::$assets['script'])) {
			$js .= "<script>\n".self::$assets['script']."\n</script>\n";
		}
		
		/* create the css */
		if (!empty(self::$assets['css']) && is_array(self::$assets['css'])) {
			foreach(self::$assets['css'] as $path => $v) {
				$css .= "<link rel=\"stylesheet\" href=\"$path\">\n";
			}
		}
		
		if (!empty(self::$assets['styles']) && is_string(self::$assets['styles'])) {
			$css .= "<style>\n".self::$assets['styles']."\n</style>\n";
		}
		
		/* insert the js */
		if (!empty($js)) {
			/* check for a closing body tag */
			if (preg_match('</body>', $content)) {
				preg_replace('/\<\/body\>/', $js.'</body>', $content);
			} elseif ($force) {
				$content .= "\n".$js;
			}
		}
		
		/* insert the js */
		if (!empty($css)) {
			/* check for a closing head tag */
			if (preg_match('</head>', $content)) {
				preg_replace('/\<\/head\>/', $css.'</head>', $content);
			} elseif ($force) {
				$content .= "\n".$css;
			}
		}
		
		/* done */
		return $content;
	}
	
}