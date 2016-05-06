<?php

namespace AAF\Controllers;

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
	 * @var string $viewPath overwrites the global environment path if provided
	 */
	public $viewPath = '';

	/**
	 * Plugin::__construct()
	 * 
	 * @param mixed $config
	 * @return void
	 */
	public function __construct($config=[]) {
		/* benchmark the request */
		if (App::$env['profile']) {
			App::benchmark('Start Plugin: '.get_class($this));
		}
		
		/* set properties from the config */
		$this->setConfig($config);

		/* setup the twig template engine */
		$this->twig = $this->_createTwigEnv();
	}
	
	/**
	 * Plugin::_default()
	 * 
	 * The default plugin action.
	 * 
	 * @return string
	 */
	public function _default() {
		throw new PluginException('You have reached the default function for the AAF\Controller\Plugin class. Please define a _default() method for "'.__CLASS__.'".');
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
		return (string) $this->twig->render($template, $vars);
	}

	/**
	 * Plugin::setConfig
	 *
	 * @param array $config
	 * @return bool
	 */
	public function setConfig($config=[]) {
		if (!is_array($config) || empty($config)) {
			return false;
		}

		foreach ($config as $k=>$v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}

		return true;
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

		/* check several folders automatically */
		if ($info['dirname'] == '' || $info['dirname'] == '.') {
			/* check the registered plugin folders */
			$file = rtrim(App::$env['paths']['plugins'], '/').'/'.$info['basename'].((!isset($info['extension'])) ? '.php' : '');
		} else {
			/* just use the provided source */
			$file = $src;
		}

		/* stop here if not found */
		if (!file_exists($file)) {
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
	 * @return Twig_Environment
	 */
	protected function _createTwigEnv() {
		/* set the path */
		$path = (!empty($this->viewPath) && is_string($this->viewPath)) ? $this->viewPath : App::$env['paths']['views'];

		/* create twig loader */
		$loader = new \Twig_Loader_Filesystem($path);
		
		/* create the environment */
		$twig = new \Twig_Environment($loader, array(
			'cache' => false,
			'charset' => 'utf-8',
			'auto_reload' => 1,
			'strict_variables' => false,
			'autoescape' => false
		));
		
		/* add in the custom AAF extension */
		$twig->addExtension(new TwigEnvExtension());
		
		/* done */
		return $twig;
	}
	
}