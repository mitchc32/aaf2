<?php

namespace AAF\Caching;

use AAF\App;
use AAF\Exceptions\CachingException;

class Redis {
    
    /**
     * @var boolean $initialized connected flag
     */
    public static $initialized = false;
    
    /**
     * @var Redis $redis object
     */
    public static $redis = false;
    
    /**
     * @var string $prefix string to prepend to each key for scoping
     */
    public static $prefix = '';
    
    /**
     * Redis::__callStatic()
     * 
	 * This method will self-initialize the redis connection if it hasn't been 
     * done before every call. The connection details are pulled from the
     * environment variables under the key "redis".
	 * 
     * @param string $method
     * @param mixed $params
     * @return mixed
     */
    public static function __callStatic($method, $params) {
        if (!self::$initialized) {
			/* make sure we have redis settings */
			if (!App::valid('redis', App::$env) || !App::valid(['host', 'port'], App::$env['redis'])) {
				throw new CachingException('Invalid or missing parameters for Redis. Required: host, port');
			}
			
			/* set a shortcut */
			$p = App::$env['redis'];
			
			/* try to connect */
			self::connect($p['host'], $p['port'], App::get('pass', $p));
            
            /* set the prefix if provided */
            if (App::valid('prefix', $p) && is_string($p['prefix'])) {
                self::$prefix = $p['prefix'];
            }
		}
        
		/* prefix the method with an underscore to match the actual method name */
		$method = '_'.$method;
		
		/* make sure the method exists */
		if (!method_exists(__CLASS__, $method)) {
			throw new CachingException('Invalid method requested from Redis.');
		}
		
		/* run the method */
		return call_user_func_array([__CLASS__, $method], $params);
    }
    
    /**
     * Redis::connect()
     * 
     * Connect to the Redis server.
     * 
     * @param string $host
     * @param integer $port
     * @param string $password
     * @return boolean
     */
    public static function connect($host='localhost', $port=6379, $password='') {
        // create the instance
        self::$redis = new Redis();
		
        // try to connect
		if(!self::$redis->connect($host, $port)){
			throw new CachingException('Error connecting to Redis server: '.$host);
		}
		
		if(!empty($password)){
			if(!self::$redis->auth($password)){
				throw new CachingException('Redis authentication failed');
			}
		}
        
        // flag it as connect
        self::$initialized = true;
		
		return true;
    }
    
	/**
	 * Redis::get()
	 * 
	 * @param mixed $key
	 * @param boolean $addPrefix
	 * @return mixed
	 */
	protected static function _get($key, $addPrefix=true){
		/* prepend the key */
		$key = (!empty(self::$prefix) && $addPrefix) ? self::$prefix.':'.$key : $key;
		
		return self::$redis->get($key);
	}
	
	/**
	 * Redis::set()
	 * 
	 * @param string $key
	 * @param mixed $val
	 * @param integer $compress 0/1 to compress - NOTE: not used in Redis, retained for backwards compatibility with the MC class
	 * @param integer $ttl time to live (defaults to 10 days)
	 * @param boolean $addPrefix true to include the site caching prefix
	 * @return mixed
	 */
	protected static function _set($key, $val, $compress=0, $ttl=0, $addPrefix=true) {
		/* set the ttl to ten days if zero */
		$ttl = (empty($ttl)) ? (strtotime('+10 day')-time()) : $ttl;
		
		/* prepend the key */
		$key = (!empty(self::$prefix) && $addPrefix) ? self::$prefix.':'.$key : $key;
		
		/* set optional arguments */
		$args = ($ttl > 0) ? ['ex'=>$ttl] : [];
		
		/* set the value */
		return self::$redis->set($key, $val, $args);
	}
	
	/**
	 * Redis::replace()
	 * 
	 * @param string $key
	 * @param mixed $val
	 * @return mixed
	 */
	protected static function _replace($key, $val){
		/* redis doesn't have an explict replace function that I know of, so we're just calling set() */
		return self::set($key, $val);
	}
	
	/**
	 * Redis::delete()
	 * 
	 * @param string $key
	 * @param boolean $addPrefix
	 * @return mixed
	 */
	protected static function _delete($key, $addPrefix=true){
		/* prepend the key */
		$key = (!empty(self::$prefix) && $addPrefix) ? self::$prefix.':'.$key : $key;
		
		/* remove it */
		return self::$redis->delete($key);
	}
    
}