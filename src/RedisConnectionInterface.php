<?php


namespace RedisPlugin;


interface RedisConnectionInterface
{
    /**
     * @var string
     */
    const HOST = "127.0.0.1";

    /**
     * @var int
     */
    const PORT = 6379;

    /**
     * @var int
     */
    const SELECT = 0;

    /**
     * @return RedisConnection
     */
    static function getInstance();

    /**
     * @param  array $config
     * @return $this
     */
    public function connect($config = array());

    /**
     * @param  string $key
     * @return bool
     */
    public function hasConnections($key);

    /**
     * @param  string $host
     * @param  int    $port
     * @param  int    $select
     * @return \Redis
     */
    public function createClient($host = self::HOST, $port = self::PORT, $select = self::SELECT);

    /**
     * @return \Phalcon\DiInterface
     */
    public function getDI();

    /**
     * @return \Redis
     */
    public function getRedis();

}