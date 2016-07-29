<?php

namespace AAF\Controllers;

use AAF\App;
use AAF\Http\Response;
use AAF\Exceptions\BaseControllerException;
use AAF\Twig\Template;

/**
 * BaseController
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
class BaseController {
	
	/**
	 * @var Twig_Environment $twig
	 */
	public $twig = false;

	/**
	 * @var string $viewPath overwrites the global environment path if provided
	 */
	public $viewPath = '';

	/**
	 * BaseController::__construct()
	 * 
	 * @param mixed $config
	 * @return void
	 */
	public function __construct($config=[]) {
		/* benchmark the request */
		if (App::$env['profile']) {
			App::benchmark('Start Controller: '.get_class($this));
		}
		
		/* set properties from the config */
		$this->setConfig($config);
	}
	
	/**
	 * BaseController::_default()
	 * 
	 * The default plugin action.
	 * 
	 * @return string
	 */
	public function _default() {
		throw new BaseControllerException('You have reached the default function for the AAF\Controller\BaseController class. Please define a _default() method for "'.__CLASS__.'".');
	}
	
	/**
	 * BaseController::render()
	 * 
	 * Fill the specified template with the provided variables using the twig
	 * template engine.
	 *  
	 * @param mixed $vars key/value pair
	 * @param string $template path to template file
	 * @return string
	 */
	public function render($vars, $template) {
		return (string) Template::render($vars, $template);
	}
    
    /**
     * BaseController::renderView()
     * 
     * Fill the specified template string with the provided variables using
     * the twig template engine. This does not reference a file.
     * 
     * @param mixed $vars
     * @param string $templateString full template string
     * @return string
     */
    public function renderString($vars, $templateString) {
        return (string) Template::renderString($vars, $templateString);
    }

	/**
	 * BaseController::setConfig
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
	 * BaseController::create()
	 * 
	 * Factory method to create a plugin instance.
	 * 
	 * @param string $src the plugin file with or without path
	 * @param mixed $config optional config options to overwrite properties
	 * @return BaseController instance
	 */
	public static function create($src, $config=array()) {
		/* make sure we have a name */
		if (empty($src)) {
			throw new BaseControllerException('Invalid source provided for plugin factory "'.$src.'"');
		}
		
		/* set the file */
		$info = pathinfo($src);
		$name = $info['filename'];

		/* check several folders automatically */
		if ($info['dirname'] == '' || $info['dirname'] == '.') {
			/* check the registered plugin folders */
			$file = rtrim(App::$env['paths']['controllers'], '/').'/'.$info['basename'].((!isset($info['extension'])) ? '.php' : '');
		} else {
			/* just use the provided source */
			$file = $src;
		}

		/* stop here if not found */
		if (!file_exists($file)) {
			throw new BaseControllerException('Invalid source provided for plugin factory "'.$src.'"');
		}
		
		/* include the file */
		require_once $file;
		
		/* make sure the class exists */
		if (!class_exists($name)) {
			throw new BaseControllerException('Invalid source provided for plugin factory "'.$src.'"');
		}
		
		return new $name($config);
	}
	
}