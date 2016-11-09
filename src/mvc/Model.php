<?php

namespace RedisPlugin\Mvc;

use RedisPlugin\Connection;
use RedisPlugin\Database;
use RedisPlugin\Mvc\Model\Criteria;
use RedisPlugin\Exception\RedisPluginException;


class Model extends \Phalcon\Mvc\Model
{

    /**
     *  @var string
     */
    const DEFAULT_PREFIX = "all";

    /**
     * @var string
     */
    const DEFAULT_NAME = "db";

    /**
     * @var int
     */
    const DEFAULT_EXPIRE = 3600;

    /** operator list */
    const EQUAL         = "=";
    const NOT_EQUAL     = "<>";
    const GREATER_THAN  = ">";
    const LESS_THAN     = "<";
    const GREATER_EQUAL = ">=";
    const LESS_EQUAL    = "<=";
    const IS_NULL       = "IS NULL";
    const IS_NOT_NULL   = "IS NOT NULL";
    const LIKE          = "LIKE";
    const I_LIKE        = "ILIKE";
    const IN            = "IN";
    const NOT_IN        = "NOT IN";
    const BETWEEN       = "BETWEEN";
    const ADD_OR        = "OR";
    const ASC           = "ASC";
    const DESC          = "DESC";

    /**
     * @var null
     */
    private static $_prefix = null;

    /**
     * @var array
     */
    private static $_keys = array();

    /**
     * @var array
     */
    private static $_bind = array();

    /**
     * @var array
     */
    private static $_cache = array();

    /**
     * @var \Phalcon\Mvc\Model
     */
    private static $_current_model = null;

    /**
     * @var array
     */
    private static $_admin_class_cache = array();

    /**
     * @var array
     */
    private static $_config_class_cache = array();

    /**
     * @var array
     */
    private static $_admin_query = array();

    /**
     * @var array
     */
    private static $_config_query = array();


    /**
     * initialize
     */
    public function initialize()
    {
        // mysql connection
        $this->setReadConnectionService($this->getServiceNames());

        // stack model
        self::setCurrentModel($this);
    }

    /**
     * @return \Phalcon\Mvc\Model
     */
    public static function getCurrentModel()
    {
        return self::$_current_model;
    }

    /**
     * @param \Phalcon\Mvc\Model $model
     */
    public static function setCurrentModel(\Phalcon\Mvc\Model $model)
    {
        self::$_current_model = $model;
    }

    /**
     * redisを取得
     * @return |Redis
     */
    private static function getRedis()
    {
        return self::getConnection()->getRedis();
    }

    /**
     * @return Connection
     */
    private static function getConnection()
    {
        $config = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("redis")
            ->get("server")
            ->get(self::getCurrentModel()->getReadConnectionService())
            ->toArray();

        return Connection::getInstance()->connect($config);
    }

    /**
     * @return Criteria
     */
    public static function criteria()
    {
        return new Criteria(new static());
    }

    /**
     * @param  string $field
     * @return mixed
     */
    private static function getLocalCache($field)
    {
        // cache key
        $key = self::getCacheKey();

        // init
        if (!isset(self::$_cache[$key])) {
            self::$_cache[$key] = [];
        }

        return isset(self::$_cache[$key][$field])
            ? self::$_cache[$key][$field]
            : null;
    }

    /**
     * @param string $field
     * @param mixed  $value
     */
    private static function setLocalCache($field, $value)
    {
        // cache key
        $key = self::getCacheKey();

        // init
        if (!isset(self::$_cache[$key])) {
            self::$_cache[$key] = [];
        }

        self::$_cache[$key][$field] = $value;
    }

    /**
     * @return string
     */
    private static function getCacheKey()
    {
        $key  = self::getServiceName();
        $key .= ":". self::getCurrentModel()->getSource();
        if (self::getPrefix()) {
            $key .= ":". self::getPrefix();
        }
        return $key;
    }

    /**
     * @return string
     */
    private static function getServiceName()
    {
        $service = self::getCurrentModel()->getReadConnectionService();

        // config
        $c = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("database")
            ->get($service);

        return $c["dbname"] .":". $c["host"] .":". $c["port"];
    }

    /**
     * @param  null|string|array $parameters
     * @return \Phalcon\Mvc\Model
     */
    public static function findFirst($parameters = null)
    {
        // parent
        if (!is_array($parameters) || !isset($parameters["query"])) {
            return parent::findFirst($parameters);
        }

        $results = self::find($parameters);
        return (isset($results[0])) ? $results[0] : null;
    }

    /**
     * @param  null|string|array $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function find($parameters = null)
    {
        // parent
        if (!is_array($parameters) || !isset($parameters["query"])) {
            return parent::find($parameters);
        }

        // params
        $params = self::buildParameters($parameters);

        // prefix
        self::$_prefix = self::DEFAULT_PREFIX;
        if (isset($params["bind"])) {
            self::setPrefix($params["bind"]);
        }

        // field key
        $field = self::buildFieldKey($params);
        if (isset($params["keys"])) {
            unset($params["keys"]);
        }

        // redis
        $result = self::findRedis($field);

        // database
        if (!$result) {
            // cache on or off
            $_cache = \Phalcon\DI::getDefault()
                ->get("config")
                ->get("redis")
                ->get("enabled");

            if (isset($params["cache"])) {
                $_cache = $params["cache"];
                unset($params["cache"]);
            }

            $expire = self::DEFAULT_EXPIRE;
            if (isset($params["expire"])) {
                $expire = $params["expire"];
                unset($params["expire"]);
            }

            $result = parent::find($params);
            if (!$result) {
                $result = array();
            }

            // cache on
            if ($_cache) {
                self::setHash($field, $result, $expire);
            }
        }

        return $result;
    }

    /**
     * @param  string $field
     * @return mixed
     */
    private static function findRedis($field)
    {
        // local cache
        $_cache = self::getLocalCache($field);

        // redis
        if (!$_cache) {
            $_cache = self::getRedis()->hGet(self::getCacheKey(), $field);
            self::setLocalCache($field, $_cache);
        }

        return $_cache;
    }

    /**
     * @param string $field
     * @param mixed  $value
     * @param int    $expire
     */
    private static function setHash($field, $value, $expire = 0)
    {
        // cache key
        $key = self::getCacheKey();

        // set redis
        $redis = self::getRedis();
        $redis->hSet($key, $field, $value);

        // local cache
        self::setLocalCache($field, $value);

        // EXPIRE
        $expire = (!$expire) ? self::DEFAULT_EXPIRE : $expire;
        if ($expire > 0 && !self::getConnection()->isTimeout($key)) {
            $redis->setTimeout($key, $expire);
        }
    }

    /**
     * @param  array $parameters
     * @return string
     */
    private static function buildFieldKey($parameters)
    {
        // base
        $key = self::DEFAULT_PREFIX;
        if (isset($parameters["keys"])) {
            $key = self::buildBaseKey($parameters["keys"]);
        }

        $addKeys = array();

        // order by
        if (isset($parameters["order"])) {

            $addKeys[] = "order";

            $fields = explode(",", $parameters["order"]);

            foreach ($fields as $field) {

                $field = trim($field);

                $values = explode(" ", $field);

                if (count($values) === 2) {

                    $addKeys[] = $values[0];
                    $addKeys[] = strtoupper($values[1]);

                } else {

                    $addKeys[] = $field;

                }
            }
        }

        // limit
        if (isset($parameters["limit"])) {

            $addKeys[] = "limit";

            if (is_array($parameters["limit"])) {

                foreach ($parameters["limit"] as $value) {

                    $addKeys[] = $value;

                }

            } else {

                $addKeys[] = $parameters["limit"];

            }
        }

        // group by
        if (isset($parameters["group"])) {

            $addKeys[] = "group";

            $fields = explode(",", $parameters["group"]);

            foreach ($fields as $field) {

                $addKeys[] = trim($field);

            }
        }

        if ($addKeys) {

            $key .= "_" . implode("_", $addKeys);

        }

        return $key;
    }

    /**
     * @param  array  $_keys
     * @return string
     */
    private static function buildBaseKey($_keys = array())
    {
        $array = array();

        if (count($_keys) > 0) {
            foreach ($_keys as $key => $value) {

                if (is_array($key)) {

                    foreach ($key as $col => $val) {

                        $array[] = $col . $val;

                    }

                    continue;
                }

                $array[] = $key . $value;
            }
        }

        return implode("_", $array);
    }

    /**
     * @param  array $parameters
     * @return array
     */
    private static function buildParameters($parameters)
    {
        // init
        $indexQuery  = array();
        $where       = array();
        self::$_keys  = array();
        self::$_bind  = isset($parameters["bind"]) ? $parameters["bind"] : array();

        // 設定確認・個別確認
        $autoIndex = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("redis")
            ->get("autoIndex");

        if (isset($parameters["autoIndex"])) {
            $autoIndex = $parameters["autoIndex"];
            unset($parameters["autoIndex"]);
        }

        $query = $parameters["query"];
        if ($autoIndex) {

            $indexes = self::getIndexes();

            if ($indexes) {

                // 一番マッチするindexにあわせてクエリを発行(PRIMARY優先)
                foreach ($indexes as $key => $index) {

                    $columns = $index->getColumns();

                    if (!isset($query[$columns[0]]))
                        continue;

                    $chkQuery = array();
                    foreach ($columns as $column) {

                        if (!isset($query[$column]))
                            break;

                        $chkQuery[$column] = $query[$column];
                    }

                    if (count($chkQuery) > count($indexQuery)) {
                        $indexQuery = $chkQuery;
                    }

                    // PRIMARY優先
                    if ($key === 0)
                        break;
                }
            }

            $query = array_merge($indexQuery, $query);
        }

        // クエリを発行
        foreach ($query as $column => $value) {

            $where[] = implode(" ", self::buildQuery($column, $value));

        }

        if (count($where) > 0) {

            $parameters[0] = implode(" AND ", $where);

            $parameters["bind"] = self::$_bind;

            ksort(self::$_keys);

            $parameters["keys"] = self::$_keys;

        }

        unset($parameters["query"]);

        return $parameters;
    }

    /**
     * @param  mixed  $column
     * @param  mixed  $value
     * @return array
     */
    private static function buildQuery($column, $value)
    {
        if (count($aliased = explode(".", $column)) > 1) {

            $named_place = $aliased[1];
            $column = sprintf("[%s].[%s]", $aliased[0], $aliased[1]);

        } else if (is_int($column)) {

            $column = "";
            $value["operator"] = Criteria::OR;

        } else {

            $named_place = $column;
            $column = sprintf("[%s]", $column);

        }

        if (is_array($value)) {

            if (isset($value["operator"])) {

                $operator  = $value["operator"];
                $_bindValue = $value["value"];

                switch ($operator) {
                    case $operator === Criteria::IS_NULL:
                    case $operator === Criteria::IS_NOT_NULL:

                        $_keys[$named_place] = str_replace(" ", "_", $operator);

                        $query = "";

                        break;

                    case $operator === Criteria::IN:
                    case $operator === Criteria::NOT_IN:

                        $len = count($_bindValue);

                        $placeholders = array();
                        for ($i = 0; $i < $len; $i++) {

                            $placeholders[] = sprintf(":%s:", $named_place.$i);

                            self::$_bind[$named_place.$i] = $_bindValue[$i];

                        }

                        self::$_keys[$named_place] =
                            str_replace(" ", "_", $operator)
                            . implode("_", $_bindValue);

                        $query = sprintf("(%s)", implode(",", $placeholders));

                        break;

                    case $operator === Criteria::BETWEEN:

                        self::$_bind[$named_place."0"] = $_bindValue[0];
                        self::$_bind[$named_place."1"] = $_bindValue[1];

                        self::$_keys[$named_place] = $operator . implode("_", $_bindValue);

                        $query = sprintf(":%s: AND :%s:", $_bindValue[0], $_bindValue[1]);

                        break;

                    case $operator === Criteria::OR:

                        self::$_keys[] = $operator;

                        $operator = "";

                        $queryStrings = array();
                        foreach ($value as $col => $val) {

                            $queryStrings[] = implode(" ", self::buildQuery($col, $val));

                        }

                        $query = "(" . implode(" OR ", $queryStrings) . ")";

                        break;

                    default:

                        self::$_bind[$named_place] = $_bindValue;

                        self::$_keys[$named_place] = $operator.$_bindValue;

                        $query = sprintf(":%s:", $named_place);

                        break;
                }

            } else {

                $operator = self::IN;

                $placeholders = array();
                $len = count($value);

                for ($i = 0; $i < $len; $i++) {

                    $placeholders[] = sprintf(":%s:", $named_place.$i);

                    self::$_bind[$named_place.$i] = $value[$i];

                }

                self::$_keys[$named_place] = str_replace(" ", "_", $operator) . implode("_", $value);

                $query = sprintf("(%s)", implode(",", $placeholders));
            }

        } else {

            if ($value === null) {

                $operator = Criteria::ISNULL;

                self::$_keys[$named_place] = "IS_NULL";

                $query = "";

            } else if (is_array($value)) {

                $operator = "";

                $queryStrings = array();
                foreach ($value as $col => $val) {

                    $queryStrings[] = implode(" ", self::buildQuery($col, $val));

                }

                $query = "(" . implode(" OR ", $queryStrings) . ")";

            } else {

                $operator = Criteria::EQUAL;

                self::$_bind[$named_place] = $value;

                self::$_keys[$named_place] = "=".$value;

                $query = sprintf(":%s:", $named_place);

            }

        }

        return array(
            "column"   => $column,
            "operator" => $operator,
            "query"    => $query
        );
    }

    /**
     * @param  \Phalcon\Mvc\Model|null $model
     * @return mixed
     */
    private static function getIndexes(\Phalcon\Mvc\Model $model = null)
    {
        /** @var \Phalcon\Mvc\Model $model */
        $model = ($model) ? : self::getCurrentModel();
        return $model->getModelsMetaData()->readIndexes($model->getSource());
    }

    /**
     * @return string
     */
    private static function getPrefix()
    {
        return self::$_prefix;
    }

    /**
     * @param  null|array $_keys
     * @throws RedisPluginException
     */
    private static function setPrefix($_keys = null)
    {
        self::$_prefix = null;

        $columns = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("redis")
            ->get("prefix")
            ->get("columns");

        if (!$columns) {
            throw new RedisPluginException("not found prefix columns");
        }

        $model = self::getCurrentModel();
        foreach ($columns as $column) {

            $property = trim($column);

            if ($_keys) {

                if (!isset($_keys[$property])) {
                    continue;
                }

                self::$_prefix = $_keys[$property];

            } else {

                if (!property_exists($model, $property)) {
                    continue;
                }

                self::$_prefix = $model->{$property};

            }

            break;
        }

        if (!self::$_prefix) {
            self::$_prefix = self::DEFAULT_PREFIX;
        }
    }

    /**
     * @return string
     */
    public function getShardServiceName()
    {
        $mode = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("redis")
            ->get("shard")
            ->get("enabled");

        $prefix = self::getPrefix();

        if ($mode && $prefix) {

            $adminClass = self::getAdminClass($prefix);
            if ($adminClass) {

                $column = \Phalcon\DI::getDefault()
                    ->get("config")
                    ->get("redis")
                    ->get("admin")
                    ->get("column");

                if (!property_exists($adminClass, $column)) {
                    return self::getMemberConfigName($adminClass->{$column});
                }
            }
        }

        return self::DEFAULT_NAME;
    }

    /**
     * @param  mixed $primary_key
     * @return string
     */
    public function getMemberConfigName($primary_key)
    {
        // local cache
        if (isset(self::$_config_class_cache[$primary_key])) {
            return self::$_config_class_cache[$primary_key];
        }

        // local cache
        $_prefix        = self::getPrefix();
        $_current_model = self::getCurrentModel();

        $config = $this->getDI()
            ->get("config")
            ->get("redis")
            ->get("shard")
            ->get("control");

        $class = $config->get("model");
        if (!isset(self::$_config_query["query"])) {

            $primary = "id";
            $indexes = self::getIndexes(new $class);

            if (isset($indexes["PRIMARY"])) {

                $primary = $indexes["PRIMARY"]->getColumns()[0];

            }

            self::$_config_query = array(
                "query" => array($primary => $primary_key)
            );

        }

        // config
        $configClass = $class::findFirst(self::$_config_query);

        // local cache
        self::$_config_class_cache[$primary_key] = ($configClass)
            ? $configClass->{$config->get("column")}
            : self::DEFAULT_NAME;

        // reset
        self::$_prefix = $_prefix;
        self::setCurrentModel($_current_model);

        return self::$_config_class_cache[$primary_key];
    }

    /**
     * @param  mixed $_prefix
     * @return \Phalcon\Mvc\Model
     * @throws RedisPluginException
     */
    public function getAdminClass($_prefix)
    {
        // local cache
        if (isset(self::$_admin_class_cache[$_prefix])) {
            return self::$_admin_class_cache[$_prefix];
        }

        // local cache
        $_prefix = self::getPrefix();
        $_current_model = self::getCurrentModel();

        $class = $this->getDI()
            ->get("config")
            ->get("redis")
            ->get("admin")
            ->get("model");

        if (!isset(self::$_admin_query["query"])) {

            $primary = "id";
            $indexes = self::getIndexes(new $class);

            if (isset($indexes["PRIMARY"])) {

                $primary = $indexes["PRIMARY"]->getColumns()[0];

            }

            self::$_admin_query = array("query" => array($primary => $_prefix));

        }

        $adminClass = $class::findFirst(self::$_admin_query);
        if (!$adminClass) {
            throw new RedisPluginException("Not Created Admin Member");
        }

        self::$_admin_class_cache[$_prefix] = $adminClass;

        // reset
        self::$_prefix = $_prefix;
        self::setCurrentModel($_current_model);

        return $adminClass;
    }

    /**
     * @param  null               $parameters
     * @param  \Phalcon\Mvc\Model $model
     * @return \Phalcon\Mvc\Model\QueryInterface
     * @throws RedisPluginException
     */
    public static function queryUpdate($parameters = null, \Phalcon\Mvc\Model $model)
    {
        if (!is_array($parameters) || !isset($parameters["query"])) {
            throw new RedisPluginException("parameters array only.");
        }

        // initialize
        $model->initialize();

        // build parameters
        $params = self::buildParameters($parameters);

        // replace
        $where = $params[0];
        $where = str_replace("[", "`", $where);
        $where = str_replace("]", "`", $where);

        // bind
        $bind  = $params["bind"];
        foreach ($bind as $column => $value) {
            $where = str_replace(":".$column.":", $value, $where);
        }

        // update
        $update = $params["update"];
        $sets   = [];
        foreach ($update as $column => $value) {
            if (is_string($value)) {
                $value = "\"".$value."\"";
            } else if ($value === null) {
                $value = "NULL";
            }

            $sets[] = $column ." = ". $value;
        }
        $set = implode(",", $sets);

        // execute
        $service = $model->getReadConnectionService();
        $adapter = \Phalcon\DI::getDefault()->getShared($service);
        $result  = $adapter->execute(
            "UPDATE "  . $model->getSource()
            ." SET "   . $set
            ." WHERE " . $where
        );

        // cache delete
        self::cacheAllDelete($model);

        return $result;
    }

    /**
     * @param null $parameters
     * @param \Phalcon\Mvc\Model $model
     * @return mixed
     * @throws RedisPluginException
     */
    public static function queryDelete($parameters = null, \Phalcon\Mvc\Model $model)
    {
        if (!is_array($parameters) || !isset($parameters["query"])) {
            throw new RedisPluginException("parameters array only.");
        }

        // initialize
        $model->initialize();

        // build parameters
        $params = self::buildParameters($parameters);

        // replace
        $where = $params[0];
        $where = str_replace("[", "", $where);
        $where = str_replace("]", "", $where);

        // bind
        $bind  = $params["bind"];
        foreach ($bind as $column => $value) {
            $where = str_replace(":".$column.":", $value, $where);
        }

        // execute
        $service = $model->getReadConnectionService();
        $adapter = \Phalcon\DI::getDefault()->getShared($service);
        $result  = $adapter->execute(
            "DELETE FROM " . $model->getSource()
            ." WHERE "     . $where
        );

        // cache delete
        self::cacheAllDelete($model);

        return $result;
    }

    /**
     * @param \Phalcon\Mvc\Model $model
     */
    private static function cacheAllDelete(\Phalcon\Mvc\Model $model)
    {
        // cache all delete
        $databases = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("database");

        foreach ($databases as $db => $arguments) {

            $key  = Database::getCacheKey($model, $arguments, null);
            $keys = Database::getRedis($db)->keys($key.":*");
            foreach ($keys as $cKey) {
                Database::getRedis($db)->delete($cKey);
            }

        }
    }

    /**
     * @return string
     */
    public function getServiceNames()
    {
        switch (true) {
            case $this->isCommon():
                $configName = $this->getCommonServiceName();
                break;
            case $this->isAdmin():
                $configName = $this->getAdminServiceName();
                break;
            default:
                $configName = $this->getShardServiceName();
                break;
        }

        $slaveName  = $configName;
        $slaveName .= (Database::isTransaction()) ? "Master" : "Slave";
        return $slaveName;
    }

    /**
     * @return bool
     */
    public function isCommon()
    {
        $config = $this->getDI()
            ->get("config")
            ->get("redis")
            ->get("common");

        $enabled = $config->get("enabled");
        if (!$enabled) {
            return false;
        }

        $dbs = $config->get("dbs")->toArray();
        if (!$dbs || !is_array($dbs)) {
            return false;
        }

        return $this->isMatch($dbs);
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        $config = $this->getDI()
            ->get("config")
            ->get("redis");

        $enabled = $config->get("shard")->get("enabled");
        if (!$enabled) {
            return false;
        }

        $dbs = $config->get("admin")->get("dbs")->toArray();
        if (!$dbs || !is_array($dbs)) {
            return false;
        }

        return $this->isMatch($dbs);
    }

    /**
     * @param  array $databases
     * @return bool
     */
    public function isMatch($databases = array())
    {
        $source = $this->getSource();
        foreach ($databases as $name) {
            $name = trim($name);
            if (substr($source, 0, strlen($name)) !== $name) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getCommonServiceName()
    {
        return $this->getDI()
            ->get("config")
            ->get("redis")
            ->get("common")
            ->get("service")
            ->get("name");
    }

    /**
     * @return string
     */
    public function getAdminServiceName()
    {
        return $this->getDI()
            ->get("config")
            ->get("redis")
            ->get("admin")
            ->get("service")
            ->get("name");
    }

    /**
     * @param  null $data
     * @param  null $whiteList
     * @return bool
     */
    public function save($data = null, $whiteList = null)
    {
        // pre
        $this->_pre();

        // execute
        if (!parent::save($data, $whiteList)) {
            Database::outputErrorMessage($this);
            return false;
        }

        // post
        $this->_post();

        return true;
    }

    /**
     * @param null $data
     * @param null $whiteList
     * @return bool
     */
    public function create($data = null, $whiteList = null)
    {
        return $this->save($data, $whiteList);
    }

    /**
     * @param  null $data
     * @param  null $whiteList
     * @return bool
     */
    public function update($data = null, $whiteList = null)
    {
        return $this->save($data, $whiteList);
    }

    /**
     * @return bool
     */
    public function delete()
    {
        // pre
        $this->_pre();

        if (!parent::delete()) {
            Database::outputErrorMessage($this);
            return false;
        }

        // post
        $this->_post();

        return true;
    }

    /**
     * pre
     */
    private function _pre()
    {
        $this->initialize();
        $this->setTransaction(Database::getTransaction($this));
    }

    /**
     *  post
     */
    private function _post()
    {
        Database::addModel($this);
    }

    /**
     * local cache clear
     */
    public static function localCacheClear()
    {
        self::$_prefix              = null;
        self::$_keys               = array();
        self::$_bind               = array();
        self::$_cache              = array();
        self::$_current_model      = null;
        self::$_admin_class_cache  = array();
        self::$_config_class_cache = array();
        self::$_admin_query        = array();
    }

    /**
     * @param  null|\Phalcon\Mvc\Model $caller
     * @param  array $options
     * @return array
     */
    public function toViewArray($caller = null, $options = array())
    {

        $ignore = [];
        if (isset($options["ignore"])) {
            $ignore = $options["ignore"];
        }

        $obj = [];

        $attributes = $this->getModelsMetaData()->getAttributes($this);

        foreach ($attributes as $attribute) {

            if (in_array($attribute, $ignore)) {
                continue;
            }

            $method = "get" . ucfirst(str_replace(" ", "", ucwords(str_replace("_", " ", $attribute))));

            if (!method_exists($this, $method)) {
                continue;
            }

            $obj[$attribute] = call_user_func([$this, $method]);
        }

        return $obj;
    }
}