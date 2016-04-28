<?php

namespace AAF\Controller;

use AAF\App as App;
use AAF\Http\Response as Response;
use AAF\Exceptions\PluginException as PluginException;
use AAF\Twig\TwigEnvExtension as TwigEnvExtension;

/**
 * Plugin
 *
 * The primary route handler and base controller class.
 *
 * NOTE ON ASSETS:
 *
 * Assets (CSS/JS files) should be added by using Request::addAssets(['/assets/js/file.js', '/assets/css/file.css');
 *
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class Plugin {
	
	/**
	 * @var Twig_Environment $twig
	 */
	public $twig = false;

	/**
	 * Plugin::__construct()
	 * 
	 * @param mixed $config
	 * @return void
	 */
	public function __construct($config=[]) {
		
	}
	
	/**
	 * Plugin::_default()
	 * 
	 * The default plugin action.
	 * 
	 * @return string
	 */
	public function _default() {
		return '<h1>Plugin Default Function</h1><p>You have reached the default function for the AAF\Controller\Plugin class.</p>';
	}
	
	/**
	 * Plugin::render()
	 * 
	 * Fill the specified template with the provided variables using the twig
	 * template engine.
	 *  
	 * @param mixed $vars key/value pair
	 * @param string $template path to template file
	 * @return string
	 */
	public function render($vars, $template) {
		/* set defaults */
		$base = [
			
		];
		
		/* setup the twig environment */
		$this->twig = $this->_createTwigEnv([
			'tmp' => file_get_contents($template)
		]);
	}
	
	/**
	 * Plugin::create()
	 * 
	 * Factory method to create a plugin instance.
	 * 
	 * @param string $src the plugin file with or without path
	 * @param mixed $config optional config options to overwrite properties
	 * @return Plugin instance
	 */
	public static function create($src, $config=array()) {
		/* make sure we have a name */
		if (empty($src)) {
			throw new PluginException('Invalid source provided for plugin factory "'.$src.'"');
		}
		
		/* set the file */
		$info = pathinfo($src);
		$name = $info['filename'];
		$files = array();
		
		/* check several folders automatically */
		if ($info['dirname'] == '' || $info['dirname'] == '.') {
			/* check the registered plugin folders */
			$files[] = rtrim(App::$env['defaultHandlerPath'], '/').'/'.$info['basename'].((!isset($info['extension'])) ? '.php' : '');
		} else {
			/* just use the provided source */
			$files[] = $src;
		}
		
		/* look for a file */
		foreach ($files as $file) {
			/* stop here if the file exists */
			if (file_exists($file)) {
				break;
			}
			
			/* clear it so we don't have to do another IO command to test it later */
			$file = false;
		}
		
		/* stop here if not found */
		if (!$file) {
			throw new PluginException('Invalid source provided for plugin factory "'.$src.'"');
		}
		
		/* include the file */
		require_once $file;
		
		/* make sure the class exists */
		if (!class_exists($name)) {
			throw new PluginException('Invalid source provided for plugin factory "'.$src.'"');
		}
		
		return new $name($config);
	}
	
	/**
	 * Plugin::_createTwigEnv()
	 * 
	 * @param mixed $loaderConfig
	 * @return Twig_Environment
	 */
	protected function _createTwigEnv($loaderConfig=array()) {
		/* create twig loader */
		if (empty($loaderConfig)) {
			$loader = new Twig_Loader_Array($this->template);
		} else {
			$loader = new Twig_Loader_Array($loaderConfig);
		}
		
		/* create the environment */
		$twig = new Twig_Environment($loader, array(
			'cache' => false,
			'charset' => 'utf-8',
			'auto_reload' => 1,
			'strict_variables' => false,
			'autoescape' => false
		));
		
		/* add in extensions */
		$twig->addExtension(new TwigEnvExtension());
		
		/* done */
		return $twig;
	}
	
}