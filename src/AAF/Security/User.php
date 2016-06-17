<?php

namespace AAF\Security;

use AAF\App as App;

/**
 * User
 * 
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class User {
	
	/**
	 * User::authorize()
	 * 
	 * Authorize the user for the provided list of roles.
	 * 
	 * @param mixed $roles
	 * @return bool
	 */
	public static function authorize($roles) {
		/* make sure it isn't empty */
		if (empty($roles)) {
			throw new \Exception('Invalid roles provided for user authorization. At least one role is required.');
		}
		
		/* standardize the provided roles */
		if (is_string($roles)) {
			$roles = [$roles];
		}
		
		/* save the session */
		$_SESSION['_aaf_user'] = [
			'roles' => $roles,
			'authOn' => time(),
			'expOn' => strtotime(App::$env['sessionExpires'])
		];
		
		/* done */
		return true;
	}
	
	/**
	 * User::unauthorize()
	 * 
	 * Clear a user's session.
	 * 
	 * @return bool
	 */
	public static function unauthorize() {
		/* dump the session */
		unset($_SESSION['_aaf_user']);
		
		/* done */
		return true;
	}
	
	/**
	 * User::isAuthorized()
	 * 
	 * Check to see if a user has a provided role. If an array of roles is provided
	 * it will check for at least one role in the set.
	 * 
	 * @param mixed $roles name as a string or an array of names to check
	 * @return bool
	 */
	public static function isAuthorized($roles) {
		/* make sure there is a session */
		if (!App::valid('_aaf_user', $_SESSION) || !is_array($_SESSION['_aaf_user'])) {
			return false;
		}
		
		/* make sure the session has the required keys */
		if (!App::valid(['roles', 'authOn', 'expOn'], $_SESSION['_aaf_user'])) {
			return false;
		}
		
		/* make sure the session hasn't expired */
		if (time() >= $_SESSION['_aaf_user']['expOn']) {
			/* clear the session */
			self::unauthorize();
			
			/* done */
			return false;
		}
		
		/* standardize the role */
		if (is_string($roles)) {
			$roles = [$roles];
		}
		
		/* check the roles */
		$intersect = array_intersect($roles, $_SESSION['_aaf_user']['roles']);
		if (empty($intersect)) {
			return false;
		}
		
		/* looks good */
		return true;
	}
	
}