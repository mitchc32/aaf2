<?php

namespace AAF\Twig;

use AAF\App;
use AAF\Controllers\BaseController;
use AAF\Http\Response;

class TwigEnvExtension extends \Twig_Extension {
	
	public function getName() {
        return 'Architect';
    }
	
    public function getGlobals() {
        /* set the available items */
		return [
            'REQUEST' => [
				'url' => Response::$url
			],
            'SESSION' => (isset($_SESSION)) ? $_SESSION : [],
            'SERVER' => $_SERVER,
            'INPUT' => App::$input,
            'ENV' => App::$env
        ];
    }
    
    public function getFilters() {
    	return [
			
			new \Twig_SimpleFilter('escapeTemplate', function($str){
				/* validate the input */
				if (!is_string($str) || empty($str)) {
					return '';
				}
				
				/* convert the key template markers */
				return str_replace(['{{', '}}', '{%', '%}'], ['&#123;&#123;', '&#125;&#125;', '&#123;&#37;', '&#37;&#125;'], $str);
			})
			
		];
    }
    
    public function getFunctions() {
        return [
            new \Twig_SimpleFunction('plugin', function($src='', $config=[], $params=[]){
            	/* use the plugin factory method to create an instance of the handler
				using the route details */
				$p = BaseController::create($src, $config);

				/* Get the action from the route, but make sure to remove any special
				characters from it in case this came from the URL. We want to make sure
				that "/this-method-name" maps to "this_method_name" for pretty URLs and
				SEO reasons. */
				$m = preg_replace('/[\-\s]/i', '_', App::get('action', $config));

				/* get the current buffer */
				$ob = ob_get_contents();
				
				/* clean the buffer */
				ob_clean();

				/* check that the action isn't empty and exists in the class before
				trying execute it. */
				if (!empty($m) && method_exists($p, $m)) {
					$output = call_user_func_array(array($p, $m), $params);
				} else {
					/* call the default method */
					$output = (string) $p->_default();
				}

				/* done */
				return $ob . $output;
            }),
            
            new \Twig_SimpleFunction('dump', function($context, $obj=false){
            	return (!empty($obj)) ? App::dump($obj) : App::dump($context);
            }, array('needs_context' => true)),

            new \Twig_SimpleFunction('now', function(){
            	return time();
            }),
            
            new \Twig_SimpleFunction('datetime', function($dt, $format='m/d/Y h:i A'){
            	switch (true) {
            		case (is_object($dt) && $dt instanceof \MongoDB\BSON\UTCDateTime):
            			return date($format, \AAF\Database\MDB::sec($dt));
            			break;
           			
           			case (is_int($dt) || (is_string($dt) && (int) $dt > 0)):
					   	if (empty($dt)) {
					   		return '';
					   	}
					   	
						return date($format, $dt);
           				break;
          				
					case (is_string($dt)):
						$dt = strtotime($dt);
						if (empty($dt)) {
							return '';
						}
						
						return date($format, strtotime($dt));
						break;
					
					default:
						return '';
						break;
            	}
            }),
            
            new \Twig_SimpleFunction('summarize', function($str, $len=50, $replaceLineBreaks=false){
            	/* make sure we have a string */
        		if (!is_string($str)) {
        			return '';
        		}
        		
        		/* see if we neeed to strip out line breaks */
        		if($replaceLineBreaks === true){
        			$str = str_replace("\n",' ',$str);
        		}
        		
        		/* remove any template fields */
        		$str = preg_replace('/{[%{](.+?)[%}]}/im', '', $str);
        		
        		/* clean it all up */
        		$str = explode("\n", trim(wordwrap(strip_tags(stripslashes($str)), $len, "\n")));
        		
        		return $str[0].((count($str) > 1) ? '...' : '');
            }),

			new \Twig_SimpleFunction('formatBytes', function($bytes, $precision = 2) {
				$bytes = (int) $bytes;
				$units = array('B', 'KB', 'MB', 'GB', 'TB');

				$bytes = max($bytes, 0);
				$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
				$pow = min($pow, count($units) - 1);

				$bytes /= pow(1024, $pow);

				return round($bytes, $precision) . ' ' . $units[$pow];
			}),
			
			new \Twig_SimpleFunction('controller', function($controller, $action='', $params=[]) {
				try {
					/* use the plugin factory method to create an instance of the handler
					using the route details */
					$plugin = BaseController::create($controller);
					
					/* check that the action isn't empty and exists in the class before
					trying execute it. */
					if (!empty($action) && method_exists($plugin, $action)) {
						return call_user_func_array(array($plugin, $action), $params);
					}
					
					/* call the default method */
					return (string) $plugin->_default();
				} catch (\BaseControllerException $e) {
					return '<pre>ERROR: ' . $e->getMessage() . '</pre>';
				}
			})
            
        ];
    }
	
}