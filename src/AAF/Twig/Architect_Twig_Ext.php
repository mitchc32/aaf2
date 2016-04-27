<?php

class Architect_Twig_Ext extends Twig_Extension {
	
	public function getName() {
        return 'Architect';
    }
	
    public function getGlobals() {
        /* get the defined constants */
        $cons = get_defined_constants(true);
		
		/* set the available items */
		return [
            '_url' => \AAF\Http\Response::$url,
            'SESSION' => (isset($_SESSION)) ? $_SESSION : [],
            'SERVER' => $_SERVER,
            'CONSTANTS' => $cons['user'],
            'input' => \AAF\App::$input
        ];
    }
    
    public function getFilters() {
    	return [
			
			new Twig_SimpleFilter('escapeTemplate', function($str){
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
            new Twig_SimpleFunction('plugin', function($src='', $config=array()){
            	/* get the plugin */
            	$p = Plugin::create($src, $config);
				
				/* check for an action */
				$action = (App::valid('action', $config)) ? $config['action'] : '';
				
				/* get the current buffer */
				$ob = ob_get_contents();
				
				/* clean the buffer */
				ob_clean();
				
				/* check for a delayed config item */
				if (App::valid('delayed', $config)) {
					$output = '{||'.uniqid().'||}';
					Page::$delayed[$output] = array(
						'obj' => $p,
						'action' => $action
					);
				} else {
					/* execute */
					$output = $p->execute($action);
				}
				
				/* done */
				return $ob . $output;
            }),
            
            new Twig_SimpleFunction('dump', function($context, $obj=false){
            	return (!empty($obj)) ? App::dump($obj) : App::dump($context);
            }, array('needs_context' => true)),
            
            new Twig_SimpleFunction('include', function($context, $path, $marker='') {
				/* split the file */
				$info = pathinfo($path);
				
				/* add the directory */
				$path = (empty($info['dirname']) || $info['dirname'] == '.') ? 'templates/'.$info['basename'] : $path;
				
				/* look for the file */
				if (!file_exists($path)) {
					return '<p>Template Error: Could not find file "'.$path.'"</p>';
				}
				
				/* load it up */
				$template = new Template($path);
				
				/* finish */
				return $template->fill($context, $marker);
			}, array('needs_context' => true)),
            
            new Twig_SimpleFunction('now', function(){
            	return time();
            }),
            
            new Twig_SimpleFunction('datetime', function($dt, $format='m/d/Y h:i A'){
            	switch (true) {
            		case (is_object($dt) && $dt instanceof MongoDB\BSON\UTCDateTime):
            			return date($format, MDB::sec($dt));
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
            
            new Twig_SimpleFunction('summarize', function($str, $len=50){
            	return Util::summarize($str, $len);
            }),
            
        ];
    }
	
}