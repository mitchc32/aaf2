<?php

namespace AAF\Twig;

use AAF\App;
use AAF\Twig\TwigEnvExtension;
use AAF\Exceptions\TemplateException;

class Template {
    
    /**
     * @var Twig $twig instance
     */
    public static $twig;
    
    /**
     * @var mixed $envDefaults twig environment defaults
     */
    public static $envDefaults = [
        'cache' => false,
		'charset' => 'utf-8',
		'auto_reload' => 1,
		'strict_variables' => false,
		'autoescape' => false
    ];
    
    /**
     * @var boolean $initialized
     */
    public static $initialized = false;
    
    /**
     * Template::__callStatic()
     * 
     * Ensure that the twig environment has been intialized before processing
     * any rendering jobs.
     * 
     * @param string $method
     * @param mixed $params
     * @return mixed
     */
    public static function __callStatic($method, $params) {
        if (!self::$initialized) {
			/* check for twig environment settings */
            $config = (App::valid('twig', App::$env)) ? App::$env['twig'] : [];
			
			/* set up the environment */
            self::$twig = self::createEnvironment($config);
		}
        
		/* prefix the method with an underscore to match the actual method name */
		$method = '_'.$method;
		
		/* make sure the method exists */
		if (!method_exists(__CLASS__, $method)) {
			throw new TemplateException('Invalid method requested from Redis.');
		}
		
		/* run the method */
		return call_user_func_array([__CLASS__, $method], $params);
    }
    
    /**
     * Template::_render()
     * 
     * Render a the provided template from the file system using the passed in
     * variables.
     * 
     * @param mixed $vars
     * @param string $templateFile relative to the environemnt view path
     * @return string
     */
    protected static function _render($vars, $templateFile) {
        return (string) self::$twig->render($templateFile, $vars);
    }
    
    /**
     * Template::_renderString()
     * 
     * Render the provided template string using the passed in variables.
     * 
     * @param mixed $vars
     * @param string $templateString
     * @return string
     */
    protected static function _renderString($vars, $templateString) {
        // create a template object from the passed in string
        $template = self::$twig->createTemplate($templateString);
        
        return (string) $template->render($vars);
    }
    
    /**
     * Template::createEnvironment()
     * 
     * Create the twig environment.
     * 
     * @param mixed $opts
     * @return twig instance
     */
    public static function createEnvironment($opts=[]) {
        /* create the config */
        $config = (empty($opts) || !is_array($opts)) ? self::$envDefaults : array_merge(self::$envDefaults, $opts);
        
        /* remove any custom extension from the config array to prevent an error
        when creating the environment */
        if (App::valid('extension', $config)) {
            unset($config['extension']);
        }
        
        /* set the path */
		$path = (App::valid('path', $config) && is_string($config['path'])) ? $config['path'] : App::$env['paths']['views'];
        
        /* remove the config version of the path */
        if (App::valid('path', $config)) {
            unset($config['path']);
        }
        
		/* create twig loader */
		$loader = new \Twig_Loader_Filesystem($path);
		
		/* create the environment */
		$twig = new \Twig_Environment($loader, $config);
		
		/* add in the custom AAF extension */
		$twig->addExtension(new TwigEnvExtension());
		
		/* add in the twig string loader extension */
		$twig->addExtension(new \Twig_Extension_StringLoader());
		
		/* load any custom extension */
		self::loadCustomExtension($twig);
		
		/* done */
		return $twig;
    }
    
    /**
     * Template::loadCustomExtension()
     * 
     * Check the environment variable for any customized twig extensions that
     * the site needs to load.
     * 
     * @throws TwigException
     * @param TwigEnvironment $twig
     * @return TwigEnvironment
     */
    public static function loadCustomExtension($twig) {
        if (!App::valid('twig', App::$env) || !App::valid('extension', App::$env['twig'])) {
            return $twig;
        }
        
        /* set the directory to look in and the full file path */
        $dir = (App::valid('paths', App::$env)) ? rtrim(App::get('root', App::$env['paths']), '/').'/' : '';
        $filePath = $dir . ltrim(App::$env['twig']['extension'], '/');
        
        /* make sure the file exists */
        if (!file_exists($filePath)) {
            throw new TemplateException('Invalid twig extension file path; file "'.$filePath.'" does not exist.');
        }
        
        /* include the class */
        require_once $filePath;
        
        /* get the class name from the path: lib\CustomTwig.php = CustomTwig */
        $className = pathinfo($filePath, PATHINFO_FILENAME);
        
        /* make sure the class exists */
        if (!class_exists($className)) {
            throw new TemplateException('Invalid twig extension; class "'.$className.'" does not exist.');
        }
        
        /* try to create the class */
        try {
            $twig->addExtension(new $className());
        } catch (\Exception $e) {
            throw new TemplateException('Invalid twig extension; '.$e->getMessage());
        }
        
        return $twig;
    }
    
}