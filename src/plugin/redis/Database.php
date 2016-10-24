<?php

namespace RedisPlugin;

use \Exception;
use RedisPlugin\Exception\RedisPluginException;

class Database implements DatabaseInterface
{

    /**
     * @var \Phalcon\Mvc\Model[]
     */
    protected static $models = array();

    /**
     * @var bool
     */
    protected static $isTransaction = false;

    /**
     * @var \Phalcon\Mvc\Model\TransactionInterface[]
     */
    protected static $transactions = array();

    /**
     * @param \Phalcon\Mvc\Model $model
     */
    public static function addModel(\Phalcon\Mvc\Model $model)
    {
        self::$models[] = $model;
    }

    /**
     * @return \Phalcon\Mvc\Model[]
     */
    private static function getModels()
    {
        return self::$models;
    }

    /**
     * @return \Phalcon\Mvc\Model\TransactionInterface
     */
    public static function getTransaction(\Phalcon\Mvc\Model $model)
    {
        $service = self::getServiceName($model);
        if (!isset(self::$transactions[$service])) {
            /** @var \Phalcon\Mvc\Model\Transaction\Manager $manager */
            $manager = \Phalcon\DI::getDefault()->getShared($service);
            self::$transactions[$service] = $manager->get();
        }
        return self::$transactions[$service];
    }

    /**
     * @param  string     $name
     * @return Connection
     */
    private static function getConnection($name)
    {
        $config = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("redis")
            ->get("server")
            ->get($name)
            ->toArray();

        return Connection::getInstance()->connect($config);
    }


    /**
     * @param  string $name
     * @return \Redis
     */
    private static function getRedis($name)
    {
        return self::getConnection($name)->getRedis();
    }


    /**
     * beginTransaction
     */
    public static function beginTransaction()
    {
        self::$isTransaction = true;
    }

    /**
     * @return bool
     */
    public static function isTransaction()
    {
        return self::$isTransaction;
    }

    /**
     * commit
     */
    public static function commit()
    {
        if (self::isTransaction()) {

            foreach (self::$transactions as $transaction) {

                if (!$transaction->isValid()) {
                    continue;
                }

                $transaction->commit();
            }
        }

        // reset
        self::$transactions  = array();
        self::$isTransaction = false;

        // redis clear
        self::autoClear();
    }

    /**
     * autoClear
     */
    private static function autoClear()
    {
        $models = self::getModels();

        $columns = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("redis")
            ->get("prefix")
            ->get("columns");

        $databases = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("database");

        foreach ($models as $model) {

            foreach ($columns as $property) {

                if (!property_exists($model, $property)) {
                    continue;
                }

                $prefix = $model->{$property};

                foreach ($databases as $db => $arguments) {

                    $key = self::getCacheKey($model, $arguments, $prefix);
                    self::getRedis($db)->delete($key);

                }
            }

            // local cache clear
            $model::localCacheClear();

        }

        // reset
        self::$models = array();
    }

    /**
     * @param  \Phalcon\Mvc\Model $model
     * @param  array              $arguments
     * @param  mixed              $prefix
     * @return string
     */
    private static function getCacheKey(\Phalcon\Mvc\Model $model, $arguments, $prefix)
    {
        $key  = $arguments["dbname"] .":". $arguments["host"] .":". $arguments["port"];
        $key .= ":". $model->getSource();
        $key .= ":". $prefix;
        return $key;
    }

    /**
     * @param  \Phalcon\Mvc\Model $model
     * @return string
     */
    private static function getServiceName(\Phalcon\Mvc\Model $model)
    {
        // config
        $c = \Phalcon\DI::getDefault()
            ->get("config")
            ->get("database")
            ->get($model->getReadConnectionService());

        return $c["dbname"] .":". $c["host"] .":". $c["port"];
    }

    /**
     * rollback
     * @param Exception $e
     */
    public static function rollback(Exception $e)
    {
        $rollback = false;

        foreach (self::$transactions as $service => $transaction) {

            /** @var \Phalcon\Mvc\Model\Transaction\Manager $manager */
            $manager = \Phalcon\DI::getDefault()->getShared($service);

            if ($manager->has()) {

                try {
                    $transaction->rollback();
                } catch (RedisPluginException $e) {
                    array_shift(self::$transactions);
                    self::rollback($e);
                }

                $rollback = true;
            }
        }

        // reset
        self::$models        = array();
        self::$transactions  = array();
        self::$isTransaction = false;

        // no rollback execute
        if ($rollback === false) {
            return;
        }

        error_log(
            "[rollback] MESSAGE:". $e->getMessage()
            ." - FILE:".$e->getFile()
            ." - LINE:".$e->getLine()
            .$e->getTraceAsString()
        );
    }


    /**
     * @param  \Phalcon\Mvc\Model $model
     * @throws RedisPluginException
     */
    public static function outputErrorMessage(\Phalcon\Mvc\Model $model)
    {
        $messages = "";
        foreach ($model->getMessages() as $message) {
            $messages .= $message;
        }
        throw new RedisPluginException($messages);
    }
}