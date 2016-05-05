<?php

namespace AAF\Database;

use AAF\App as App;
use AAF\Exceptions\DatabaseException as DatabaseException;

/**
 * MYDB
 *
 * MySQL/MariaDB handler via PDO. This class is designed to provide a common
 * interface for all database interaction to the same tune as the MDB class.
 *
 * @package AAF
 * @author Mitchell Cannon
 * @copyright 2016
 * @access public
 */
class MYDB {

    public static $initialized = false;
    public static $pdo = false;

    /**
     * MYDB::__callStatic()
     *
     * This method will self-initialize the database connection if it hasn't
     * been done before every call.
     *
     * @param string $method
     * @param mixed $params
     * @return mixed
     */
    public static function __callStatic($method, $params) {
        if (!self::$initialized || !self::$pdo) {
            /* make sure we have database settings */
            if (!App::valid('database', App::$env) || !App::valid(['host', 'port', 'db'], App::$env['database'])) {
                throw new DatabaseException('Invalid or missing parameters for MYDB. Required: host, port, db');
            }

            /* set a shortcut */
            $p = App::$env['database'];

            /* try to connect */
            self::connect($p['host'], $p['db'], $p['port'], $p['user'], $p['pass']);
        }

        /* prefix the method with an underscore to match the actual method name */
        $method = '_'.$method;

        /* make sure the method exists */
        if (!method_exists(__CLASS__, $method)) {
            throw new DatabaseException('Invalid method, '.ltrim($method, '_').', requested from MYDB.');
        }

        /* run the method */
        return call_user_func_array([__CLASS__, $method], $params);
    }

    /**
     * MDB::connect()
     *
     * @param mixed $host
     * @param mixed $db
     * @param string $port
     * @param string $user
     * @param string $pass
     * @return bool
     */
    public static function connect($host, $db='', $port='3306', $user='', $pass='') {
        /* build the dsn string */
        $dsn = "mysql:host=$host;port=$port;dbname=$db";

        /* try to connect */
        try {
            self::$pdo = new \PDO($dsn, $user, $pass);
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage());
        }

        /* set some drive attributes */
        self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        /* flag as initialized */
        self::$initialized = true;

        /* done */
        return true;
    }

    /**
     * MYDB::_query
     *
     * Query the database with support for bind variables. Bind variables should be
     * provided as an associative array ONLY when using named bind parameters in the
     * query. Otherwise, indexed arrays should be used.
     *
     * @param string $sql SQL to execute
     * @param array $params to bind to the executed query
     * @param boolean $debug to add in a filled query for debugging
     * @return array standard AAF response
     */
    protected static function _query($sql, $params=[], $debug=false) {
        /* validate the sql */
        if (empty($sql)) {
            return App::error('Empty SQL statement provided.');
        }

        /* create the statement */
        try {
            $stmt = self::$pdo->prepare($sql);
        } catch (\PDOException $e) {
            return App::error($e->getMessage());
        }

        /* check for an associative array for named bind variables */
        if (!empty($params) && array_keys($params) !== range(0, count($params) - 1)) {
            /* bind the variables by name */
            foreach ($params as $k=>$v) {
                $stmt->bindParam(':'.$k, $v);
            }

            /* clear out the params array because we no longer need to pass them into the execute call */
            $params = [];
        }

        /* execute the statement */
        try {
            if (!$stmt->execute($params)) {
                return App::error($stmt->errorInfo());
            }
        } catch (\PDOException $e) {
            return App::error($e->getMessage());
        }

        /* handle the response accordingly */
        if ($stmt->columnCount() == 0) {
            /* insert, update, delete */
            return App::success(['numrows' => $stmt->rowCount()]);
        } else {
            /* select */
            $data = [
                'numrows' => 0,
                'rows' => $stmt->fetchAll()
            ];

            /* get the number of rows reliably - some databases don't like rowCount for select queries */
            $data['numrows'] = count($data['rows']);

            /* check if we need the debug statement */
            if ($debug) {
                $data['sql'] = self::_getCompleteSQL($sql, $params);
            }

            /* check for found rows */
            if (preg_match('/SQL_CALC_FOUND_ROWS/i', $sql)) {
                try {
                    $data['totalrows'] = self::$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
                } catch (\PDOException $e) {
                    return App::error($e->getMessage());
                }
            }

            /* done */
            return App::success($data);
        }
    }

    /**
     * MYDB::_startTransaction()
     *
     * Start a transaction for the database connection.
     *
     * @return array
     */
    protected function _startTransaction() {
        try {
            self::$pdo->beginTransaction();
        } catch (\PDOException $e) {
            return App::error($e->getMessage());
        }

        return App::success();
    }

    /**
     * MYDB::_commitTransaction()
     *
     * Commit the transaction statements/executes.
     *
     * @return array
     */
    protected function _commitTransaction() {
        try {
            self::$pdo->commit();
        } catch (\PDOException $e) {
            return App::error($e->getMessage());
        }

        return App::success();
    }

    /**
     * MYDB::_rollbackTransaction()
     *
     * Rollback statements/execs in the transaction.
     *
     * @return array
     */
    protected function _rollbackTransaction() {
        try {
            self::$pdo->rollBack();
        } catch (\PDOException $e) {
            return App::error($e->getMessage());
        }

        return App::success();
    }

    /**
     * MYDB::_getCompleteSQL
     *
     * Get the full sql statement after insert bound variables.
     *
     * @param $sql
     * @param array $params
     * @return string
     */
    protected function _getCompleteSQL($sql, $params=[]) {
        /* stop and return the sql as-is if the params are empty */
        if (empty($params)) {
            return $sql;
        }

        /* flag the params as named instead of indexed */
        $named = (array_keys($params) !== range(0, count($params) - 1)) ? true : false;

        /* set default */
        $set = [];

        /* process the list */
        foreach ($params as $k=>$v) {
            switch (true) {
                /* skip all objects except for dates */
                case (is_object($v)):
                    if ($v instanceof \DateTime) {
                        $v = $v->format('Y-m-d H:i:s');
                    } else {
                        continue;
                    }
                    break;

                case ($v === null):
                    $v = 'NULL';
                    break;

                case (is_array($v)):
                    $v = implode(',', $v);
                    break;
            }

            if ($named) {
                $set[':'.$k] = $v;
            } else {
                $sql = preg_replace('/\?/', $v, $sql, 1);
            }
        }

        /* do the replacement for named params */
        if ($named) {
            $sql = str_replace(array_keys($set), array_values($set), $sql);
        }

        return $sql;
    }

}