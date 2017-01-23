<?php

namespace AAF\Database;

use AAF\App;
use AAF\Exceptions\DatabaseException;

/**
 * MDB
 * 
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class MDB {
	
	public static $initialized = false;
	public static $mongo = false;
	public static $db = false;
	
	public static $conString = false;
	public static $host = false;
	public static $dbName = false;
	public static $port = false;
	public static $authDb = false;
	public static $user = false;
	public static $pass = false;
	
	public static $log = [];
	
	/**
	 * MDB::__callStatic()
	 * 
	 * This method will self-initialize the database connection if it hasn't
	 * been done before every call.
	 * 
	 * @param string $method
	 * @param mixed $params
	 * @return mixed
	 */
	public static function __callStatic($method, $params) {
		if (!self::$initialized) {
			/* make sure we have database settings */
			if (!App::valid('database', App::$env) || (!App::valid(['host', 'port', 'db'], App::$env['database']) && !App::valid('connectionString', App::$env['database']))) {
				throw new DatabaseException('Invalid or missing parameters for MDB. Required: host, port, db');
			}
			
			/* set a shortcut */
			$p = App::$env['database'];
			
			/* check for a connection string */
			if (App::valid('connectionString', App::$env['database'])) {
				self::connectWithString(App::$env['database']['connectionString']);
			} else {
				/* try to connect with creds */
				self::connect($p['host'], $p['db'], $p['port'], App::get('authDb', $p), App::get('user', $p), App::get('pass', $p));
			}
		}
		
		/* set the request start */
		$start = microtime(true);
		
		/* prefix the method with an underscore to match the actual method name */
		$method = '_'.$method;
		
		/* make sure the method exists */
		if (!method_exists(__CLASS__, $method)) {
			throw new DatabaseException('Invalid method requested from MDB.');
		}
		
		/* run the method */
		$resp = call_user_func_array([__CLASS__, $method], $params);
		
		/* add to the log */
		self::$log[] = [
			'time' => round(microtime(true) - $start, 3),
	        'type' => ltrim($method, '_'),
	        'error' => $resp['error'],
	        'msg' => $resp['msg']
		];
		
		return $resp;
	}
	
	/**
	 * MDB::connect()
	 * 
	 * @param mixed $host
	 * @param mixed $db
	 * @param string $port
	 * @param string $authDb
	 * @param string $user
	 * @param string $pass
	 * @return bool
	 */
	public static function connect($host, $db='', $port='27017', $authDb='admin', $user='', $pass='') {
		/* create the default connection string */
		$conString = $host.':'.$port.'/'.$db;
		
		/* set the connection config */
		$config = array(
			'connect' => true,
			'fsync' => true
		);
		
		/* update the config for authentication */
		if (!empty($authDb) && !empty($user) && !empty($pass)) {
			/* update the config */
			$config['authSource'] = $authDb;
			$config['password'] = $pass; // pass in the database here in case it includes an @ symbol
			
			/* update the connection string */
			$conString = $user.'@'.$host.':'.$port.'/'.$db;
		}
		
		/* connect */
		try {
			self::$mongo = new \MongoDB\Driver\Manager('mongodb://'.$conString, $config);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return false;
		} catch (\MongoDB\Driver\Exception $e) {
			return false;
		}
		
		/* select the database */
		self::$db = $db;
		self::$dbName = $db;
		self::$host = $host;
		self::$port = $port;
		self::$authDb = $authDb;
		self::$user = $user;
		self::$pass = $pass;
		
		/* done */
		return true;
	}
	
	/**
	 * MDB::connectWithString()
	 * 
	 * Connect to the database using a connection string instead of parameters.
	 * 
	 * @params string $conString full connection string
	 * @return bool
	 */
	public static function connectWithString($conString) {
		/* set the connection config */
		$config = array(
			'connect' => true,
			'fsync' => true
		);
		
		/* connect */
		try {
			self::$mongo = new \MongoDB\Driver\Manager($conString, $config);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return false;
		} catch (\MongoDB\Driver\Exception $e) {
			return false;
		}
		
		/* set the property for reference elsewhere */
		self::$conString = $conString;
		self::$db = App::get('db', App::$env['database']);
		self::$dbName = App::get('db', App::$env['database']);
		
		/* done */
		return true;
	}
	
	/**
	 * MDB::_aggregate()
	 * 
	 * This method runs an aggregate command using the MDB::_command method. The
	 * only difference is how the response is formatted for consistency with
	 * _find method.
	 * 
	 * @param string $collection
	 * @param mixed $pipeline
	 * @param boolean $asCursor
	 * @return mixed
	 */
	protected static function _aggregate($collection, $pipeline, $asCursor=false) {
		/* check for a db reference */
		$col = self::_getColAndDb($collection);
		
		/* call the command for consistency */
		$resp = self::_command($col[0], [
			'aggregate' => $col[1],
			'pipeline' => $pipeline
		], $asCursor);
		
		if ($resp['error']) {
			return $resp;
		}
		
		if ($asCursor) {
			return $resp;
		}
		
		return App::success([
			'numrows' => count($resp['data'][0]['result']),
			'rows' => $resp['data'][0]['result']
		]);
	}
	
	/**
	 * MDB::_command()
	 * 
	 * Run a standard command against the provided database.
	 * 
	 * @param string $database
	 * @param mixed $config
	 * @param boolean $asCursor return the resulting cursor instead of the full data set
	 * @return mixed
	 */
	protected static function _command($database, $config, $asCursor=false) {
		try {
			/* create the new command object */
			$command = new \MongoDB\Driver\Command($config);
			
			/* run the command */
		    $cursor = MDB::$mongo->executeCommand($database, $command);
		    
		    /* set the typemap to give back an array */
			$cursor->setTypeMap(array(
				'root' => 'array',
				'document' => 'array'
			));
			
			if ($asCursor) {
				/* set the data */
				$data = ['cursor' => $cursor];
			} else {
				/* grab the full data set */
				$data = iterator_to_array($cursor, false);
				
				/* check for an ok flag */
				if (isset($data[0]['ok']) && $data[0]['ok'] != 1) {
					return App::error('Unknown database error.');
				}
			}
			
			/* done */
			return App::success($data);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch(\MongoDB\Driver\Exception $e) {
		    return App::error($e->getMessage());
		} catch (\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_cursor()
	 * 
	 * @param string $collection name
	 * @param mixed $filter array('fieldName'=>'') or array('fieldName'=>array('$gt'=>10, '$lte'=>25))
	 * @param mixed $sort array('fieldName'=>1, 'fieldName'=>-1) 1 = ASC, -1 = DESC
	 * @param mixed $limit array(0, 12) like MySQL LIMIT 0, 12
	 * @param mixed $fields array('fieldName':1) 1 = include in result, 0 = exclude from result
	 * @return mixed
	 */
	protected static function _cursor($collection, $filter=array(), $sort=array(), $limit=array(), $fields=array()) {
		try {
			/* check for a db reference */
			$col = implode('.', self::_getColAndDb($collection));
			
			/* set the query options */
			$options = array();
			
			/* set the fields to return */
			if (!empty($fields) && is_array($fields)) {
				/* check to see if this is an indexed array - if it is we need to convert it
				to a supported field_name => 1 associative array instead */
				$fk = array_keys($fields);
				if (is_int($fk[0])) {
					$set = [];
					foreach ($fields as $f) {
						$set[$f] = 1;
					}
				} else {
					$set = $fields;
				}
				
				/* add the field projection */
				$options['projection'] = $set;
			}
						
			/* sort */
			if (!empty($sort) && is_array($sort)) {
				$options['sort'] = $sort;
			}
			
			/* add in the starting limit */
			if (isset($limit[0]) && !empty($limit[0])) {
				$options['skip'] = $limit[0];
			}
			
			/* add in the total to return */
			if (isset($limit[1]) && !empty($limit[1])) {
				$options['limit'] = $limit[1];
			}
			
			/* create the query */
			$query = new \MongoDB\Driver\Query($filter, $options);
			
			/* set the read preference */
			$readPreference = new \MongoDB\Driver\ReadPreference(\MongoDB\Driver\ReadPreference::RP_PRIMARY);
			
			/* execute */
			$cursor = self::$mongo->executeQuery($col, $query, $readPreference);
			
			/* set the typemap to give back an array */
			$cursor->setTypeMap(array(
				'root' => 'array',
				'document' => 'array'
			));
			
			/* done */
			return App::success(array('cursor'=>$cursor));
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_find()
	 * 
	 * @param string $collection name
	 * @param mixed $filter array('fieldName'=>'') or array('fieldName'=>array('$gt'=>10, '$lte'=>25))
	 * @param mixed $sort array('fieldName'=>1, 'fieldName'=>-1) 1 = ASC, -1 = DESC
	 * @param mixed $limit array(0, 12) like MySQL LIMIT 0, 12
	 * @param mixed $fields array('fieldName':1) 1 = include in result, 0 = exclude from result
	 * @param bool $assoc true|false to use the record _id as the index key
	 * @return mixed
	 */
	protected static function _find($collection, $filter=array(), $sort=array(), $limit=array(), $fields=array(), $assoc=false) {
		/* get the cursor */
		$resp = self::_cursor($collection, $filter, $sort, $limit, $fields);
		if ($resp['error']) {
			return $resp;
		}

		/* build the data */
		$data = array(
			'rows' => array(),
			'numrows' => 0,
			'totalrows' => 0
		);

		if($assoc){
			foreach($resp['data']['cursor'] as $row){
				$data['rows'][(string) $row['_id']] = $row;
			}
		}else{
			$data['rows'] = iterator_to_array($resp['data']['cursor'], $assoc);
		}


		/* update the count */
		$data['numrows'] = count($data['rows']);
		
		/* get the total rows */
		$sub = self::_count($collection, $filter);
		if (!$sub['error']) {
			$data['totalrows'] = $sub['data']['count'];
		}

		/* done */
		return App::success($data);
	}
	
	/**
	 * MDB::_count()
	 * 
	 * @param string $collection name
	 * @param mixed $filter array('fieldName'=>'') or array('fieldName'=>array('$gt'=>10, '$lte'=>25))
	 * @return mixed
	 */
	protected static function _count($collection, $filter=array()) {
		try {
			/* check for a db reference */
			list($db, $col) = self::_getColAndDb($collection);
			
			/* build the config */
			$config = [
				'count' => $col
			];
			
			/* add the filter to the config - this has to be left out if empty */
			if (!empty($filter)) {
				$config['query'] = $filter;
			}
			
			/* get the count */
			$resp = self::_command($db, $config);
			if ($resp['error']) {
				return $resp;
			}
			
			return App::success(array('count'=>$resp['data'][0]['n']));
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_index()
	 * 
	 * @param string $collection name
	 * @param mixed $indexes array('fieldName'=>1) or array('fieldName'=>1, 'fieldName'=>1)
	 * @param boolean $unique is a unique index
	 * @param mixed $opts additional index options
	 * @return mixed
	 */
	protected static function _index($collection, $indexes=array(), $unique=false, $opts=array()) {
		try {
			/* set the base options */
			$options = ['key' => $indexes, 'background' => true, 'sparse' => false];
			
			/* check for a unique flag */
			if ($unique) {
				$options['unique'] = true;
				$options['dropDups'] = true;
			}
			
			/* set the index name */
			$k = array_keys($indexes)[0];
			$options['name'] = strtolower($k).'_'.$indexes[$k];
			
			/* add in the manual opts */
			if (!empty($opts) && is_array($opts)) {
				$options = array_merge($options, $opts);
			}
			
			/* check for a db reference */
			list($db, $col) = self::_getColAndDb($collection);
			
			/* build the config */
			$config = [
				'createIndexes' => $col,
				'indexes' => [$options]
			];
			
			/* run the command */
			return self::_command($db, $config);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_deleteIndex()
	 * 
	 * Remove all indexes for the provided collection. This will not delete
	 * the main index on _id.
	 * 
	 * @param string $collection name
	 * @param string $index name or * for all
	 * @return mixed
	 */
	protected static function _deleteIndexes($collection, $index='*') {
		try {
			/* get the collection */
			list($db, $col) = self::_getColAndDb($collection);
			
			/* execute */
			return self::_command($db, [
				'dropIndexes' => $col,
				'index' => $index
			]);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_findAndModify()
	 * 
	 * @param string $collection name
	 * @param mixed $filter array('fieldName'=>'') or array('fieldName'=>array('$gt'=>10, '$lte'=>25))
	 * @param mixed $update array('$set'=>array('fieldName'=>'value', 'fieldName'=>'value'), '$inc'=>array('fieldName'=>1))
	 * @param mixed $sort array('fieldName'=>1, 'fieldName'=>-1) 1 = ASC, -1 = DESC
	 * @param bool $returnModified true to return the modified record; false to return the original
	 * @param bool $upser true to create a record if one isn't found
	 * @return mixed
	 */
	protected static function _findAndModify($collection, $filter, $update, $sort=array(), $returnModified=true, $upsert=false) {
		/* set the data */
		list($db, $col) = self::_getColAndDb($collection);
		$options = array(
			'findandmodify' => $col,
			'query' => $filter,
			'update' => $update,
			'upsert' => $upsert
		);
		
		/* add in the sorting */
		if (!empty($sort) && is_array($sort)) {
			$options['sort'] = $sort;
		}
		
		/* add in the new flag */
		if (is_bool($returnModified)) {
			$options['new'] = $returnModified;
		}
		
		/* create the new command object */
		$command = new \MongoDB\Driver\Command($options);
		
		try {
			/* run the command */
		    $cursor = MDB::$mongo->executeCommand($db, $command);
		    
		    /* set the typemap to give back an array */
			$cursor->setTypeMap(array(
				'root' => 'array',
				'document' => 'array'
			));
			
			/* get the data */
			$data = iterator_to_array($cursor, false);
			
			/* check for an ok flag */
			if (isset($data[0]['ok']) && $data[0]['ok'] != 1) {
				return App::error('Unknown database error.');
			}
			
			/* done */
			return App::success(array(
				'rows' => array($data[0]['value']),
				'numrows' => 1
			));
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch(\MongoDB\Driver\Exception $e) {
		    return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_update()
	 * 
	 * @param string $collection name
	 * @param mixed $filter array('fieldName'=>'') or array('fieldName'=>array('$gt'=>10, '$lte'=>25))
	 * @param mixed $updates array('$set'=>array('fieldName'=>'value', 'fieldName'=>'value'), '$inc'=>array('fieldName'=>1))
	 * @param boolean $upsert update if exists, insert if not
	 * @param boolean $multi update all matches
	 * @return mixed
	 */
	protected static function _update($collection, $filter, $updates, $upsert=false, $multi=false) {
		/* set the variables */
		$options = array(
			'multiple' => $multi,
			'upsert' => $upsert
		);
		
		try {
			/* check for a db reference */
			list($db, $col) = self::_getColAndDb($collection);
			
			/* build the update */
			$update = [
				'q' => (!empty($filter)) ? $filter : ['_id' => ['$exists' => true]],
				'u' => $updates,
				'upsert' => $upsert,
				'multi' => $multi
			];
			
			/* execute */
			$resp = self::_command($db, [
				'update' => $col,
				'updates' => [$update]
			]);
			
			/* error check */
			if ($resp['error']) {
				return $resp;
			}
			
			/* create the results data */
			return App::success(array(
				'updates' => $resp['data'][0]['nModified'],
				'upserts' => (isset($resp['data'][0]['upserted'])) ? count($resp['data'][0]['upserted']) : 0
			));
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_insert()
	 * 
	 * Insert a record into the provided collection.
	 * 
	 * @param string $collection name
	 * @param mixed $data
	 * @param bool $bulk set if data is an array of documents to insert
	 * @parma bool $ordered set if the operation should stop if an insert fails
	 * @return mixed
	 */
	protected static function _insert($collection, $data, $bulk=false, $ordered=false) {
		try {
			/* check for a db reference */
			list($db, $col) = self::_getColAndDb($collection);
			
			/* set the data's new id */
			if ($bulk) {
				foreach ($data as $i=>$d) {
					if (App::valid('_id', $data[$id])) {
                        $data[$i]['_id'] = self::id($data[$i]['_id']);
                        if (!$data[$i]['_id']) {
                            throw new DatabaseException('Invalid ID, "'.$data[$i]['_id'].'", provided');
                        }
                    } else {
                        $data[$i]['_id'] = new \MongoDB\BSON\ObjectID();
                    }
				}
			} else {
                if (App::valid('_id', $data)) {
				    $data['_id'] = self::id($data['_id']);
                    if (!$data['_id']) {
                        throw new DatabaseException('Invalid ID, "'.$data['_id'].'", provided');
                    }
                } else {
                    $data['_id'] = new \MongoDB\BSON\ObjectID();
                }
			}
			
			/* default the data to bulk form */
			$data = ($bulk) ? $data : [$data];
			
			/* run the command */
			$resp = self::_command($db, [
				'insert' => $col,
				'documents' => $data
			]);
			
			/* error check */
			if ($resp['error']) {
				return $resp;
			} elseif (App::valid('writeErrors', $resp['data'][0])) {
				// set the default error
				$errorMsg = '';
				
				foreach ($resp['data'][0]['writeErrors'] as $err) {
					$errorMsg .= 'Could not write _id ' . $data[$err['index']]['_id'] . ' due to the following error: ' . $err['errmsg'] . ' ';
				}
				
				return App::error($errorMsg);
			} elseif ($resp['data'][0]['n'] == 0) {
				return App::error('Could not write insert due to an unknown error.');
			}
			
			/* set the back the response */
			return App::success(['rows' => $data, 'numrows'=>1]);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_delete()
	 * 
	 * Delete the matching records from the provided collection.
	 * 
	 * @param string $collection name
	 * @param mixed $filter array('fieldName'=>'') or array('fieldName'=>array('$gt'=>10, '$lte'=>25))
	 * @return mixed
	 */
	protected static function _delete($collection, $filter) {
		try {
			/* check for a db reference */
			list($db, $col) = self::_getColAndDb($collection);
			
			/* set the delete command */
			$del = [
				'q' => (!empty($filter)) ? $filter : ['_id' => ['$exists' => true]],
				'limit' => 0
			];
			
			/* run the command */
			$resp = self::_command($db, [
				'delete' => $col,
				'deletes' => [$del]
			]);
			
			if ($resp['error']) {
				return $resp;
			}
			
			return App::success([
				'deletes' => $resp['data'][0]['n']
			]);
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_bulk()
	 * 
	 * Create a bulk transaction request that can consist of inserts, updates and deletes
	 * in a single, sequential list of transactions.
	 * 
	 * array(
	 * 	array('insert', array('field'=>1, 'field'=>2)),
	 * 	array('update', array('filter'=>1), array('$set'=>array('field'=>3))),
	 * 	array('delete', array('filter'=>1))
	 * )
	 * 
	 * @param string $collection name
	 * @param mixed $actions
	 * @return mixed
	 */
	protected static function _bulk($collection, $actions) {
		/* stop if not an array */
		if (empty($actions) || !is_array($actions)) {
			return App::error('Invalid list of write actions provided.');
		}
		
		try {
			/* create the bulk object */
			$bulk = new \MongoDB\Driver\BulkWrite(array('ordered' => true));
			
			/* process the list */
			foreach ($actions as $action) {
				/* check the type */
				if (empty($action) || !is_array($action) || !isset($action[0])) {
					continue;
				}
				
				/* process it */
				switch ($action[0]) {
					case 'insert':
						if (App::valid(1, $action)) {
							$bulk->insert($action[1]);
						}
						break;
					
					case 'update':
						if (App::valid(array(1, 2), $action)) {
							$bulk->update($action[1], $action[2]);
						}
						break;
					
					case 'delete':
						if (App::valid(1, $action)) {
							$bulk->delete($action[1]);
						}
						break;
					
					default:
						continue;
						break;
				}
			}
			
			/* stop here if empty */
			if (count($bulk) == 0) {
				return App::error('No valid write actions were provided. Please check the list and try again.');
			}
			
			/* check for a db reference */
			$col = (strpos($collection, '.') !== false) ? $collection : self::$db.'.'.$collection;
			
			/* run the write */
			$resp = self::$mongo->executeBulkWrite($col, $bulk);
			
			/* wrap it up */
			return App::success(array(
				'inserts' => $resp->getInsertedCount(),
				'updates' => $resp->getModifiedCount(),
				'deletes' => $resp->getDeletedCount()
			));
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return App::error($e->getMessage());
		} catch (\MongoDB\Driver\Exception $e) {
			return App::error($e->getMessage());
		}
	}
	
	/**
	 * MDB::_bulkInsert()
	 * 
	 * @param mixed $collection
	 * @param mixed $rows
	 * @param bool $ordered
	 * @return mixed
	 */
	protected static function _bulkInsert($collection, $rows, $ordered = true){
		return self::_insert($collection, $rows, true, $ordered);
	}
	
	/**
	 * MDB::switchDB()
	 * 
	 * Switch to another database.
	 * 
	 * @param string $db
	 * @return mixed
	 */
	protected static function switchDB($db) {
		if (self::$conString) {
			return self::connectWithString(self::$conString);
		}
		
		return self::connect(self::$host, $db, self::$port, self::$authDb, self::$user, self::$pass);
	}
	
	/**
	 * MDB::_getColAndDb()
	 * 
	 * This method is designed to standardize the process of getting the database
	 * and collection used in a transaction with the database
	 * 
	 * @param string $collection
	 * @return mixed [database, collection]
	 */
	protected static function _getColAndDb($collection) {
		if (strpos($collection, '.') !== false) {
			return explode('.', $collection);
		}
		
		return [self::$db, $collection];
	}
	
    /**
	 * MDB::simplify()
	 * 
	 * Convert the data in the array to json-ready values rather than
	 * including the mongo-specific objects.
	 * 
	 * PHP 7 has issues with serializing the mongo bson objects for
	 * storage in sessions. So this is needed when saving a record
	 * into session.
	 * 
	 * @param mixed $set
	 * @return mixed
	 */
	public static function simplify($set) {
		/* stop here if empty */
		if (empty($set)) {
			return $set;
		}
		
		/* handle based on type */
		switch (true) {
			case (is_array($set)):
				foreach ($set as $k=>$v) {
					$set[$k] = self::simplify($v);
				}
				return $set;
				break;
			
			case (is_object($set) && $set instanceof \MongoDB\BSON\ObjectID):
				return (string) $set;
				break;
			
			case (is_object($set) && $set instanceof \MongoDB\BSON\UTCDateTime):
				return self::sec($set);
				break;
			
			default:
				return $set;
				break;
		}
	}
    
	/**
	 * MDB::date()
	 * 
	 * Simplified wrapper for getting a mongo date object.
	 * 
	 * @param int $time stamp to use
	 * @return \MongoDB\BSON\UTCDateTime
	 */
	public static function date($time=0) {
		if (is_string($time) && (int) $time > 0) {
			$time = strtotime($time);
		} else {
			$time = (empty($time)) ? time() : (int) $time;
		}
		
		return new \MongoDB\BSON\UTCDateTime($time * 1000);
	}
	
	/**
	 * MDB::sec()
	 * 
	 * Get the seconds representation of a mongo date as an integer.
	 * 
	 * @param mixed $date
	 * @return int
	 */
	public static function sec($date) {
		/* first, use the object's internal __toString to get the seconds */
		$sec = (string) $date;
		
		/* convert to an integer */
		return ceil((int) $sec / 1000);
	}
	
	/**
	 * MDB::id()
	 * 
	 * Simplified wrapper for getting a mongo id object.
	 * 
	 * @param string $id
	 * @return \MongoDB\BSON\ObjectID
	 */
	public static function id($id='') {
		try {
			return new \MongoDB\BSON\ObjectID($id);
		} catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
			return false;
		} catch (\MongoDB\Driver\Exception\RuntimeException $e) {
			return false;
		} catch (\MongoDB\Driver\Exception $e) {
			return false;
		}
	}
	
    /**
	 * MDB::mergeMongoFilters()
	 *
	 * Merge two arrays of mongo filters together. This method simplifies
	 * merging filters that may containe $or/$and arrays.
	 *  
	 * @param mixed $filters1
	 * @param mixed $filters2
	 * @return mixed
	 */
	public static function mergeMongoFilters($filters1, $filters2) {
		/* set a base */
		$base = array();
		$ors = array();
		
		/* make sure both are arrays */
		if (empty($filters1) || !is_array($filters1)) {
			return $filters2;
		} elseif (empty($filters2) || !is_array($filters2)) {
			return $filters1;
		}
		
		/* build the sorted list */
		foreach (array($filters1, $filters2) as $filter) {
			foreach ($filter as $k=>$v) {
				switch (true) {
					case ($k == '$and'):
						if (!isset($base['$and'])) {
							$base['$and'] = array();
						}
						
						$base['$and'] = array_merge($base['$and'], $v);
						break;
					
					case ($k == '$or'):
						$ors[] = $v;
						break;
					
					default:
						$base[$k] = $v;
						break;
				}
			}
		}
		
		/* we need to add in the ors if we have some */
		if (!empty($ors)) {
			switch (true) {
				case (isset($base['$and'])):
					foreach ($ors as $or) {
						$base['$and'][] = array('$or'=>$or);
					}
					break;
				
				case (count($ors) > 1):
					$base['$and'] = array();
					foreach ($ors as $or) {
						$base['$and'][] = array('$or'=>$or);
					}
					break;
				
				default:
					$base['$or'] = $ors[0];
					break;
			}
		}
		
		/* done */
		return $base;
	}
	
}