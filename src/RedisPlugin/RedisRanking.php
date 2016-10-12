<?php


namespace RedisPlugin;


use \RedisPlugin\RedisConnection;


class RedisRanking extends RedisConnection implements RedisRankingInterface
{

    /**
     * @var \RedisPlugin\RedisRanking
     */
    private static $instance = null;

    /**
     * @return RedisRanking
     */
    static function getInstance()
    {
        return (self::$instance === null)
            ? self::$instance = new static
            : self::$instance;
    }

    /**
     * @param  string $key
     * @param  mixed  $memberId
     * @param  string $option
     * @return int
     */
    public function getRank($key, $memberId, $option = "+inf")
    {
        $score = $this->getRedis()->zScore($key, $memberId) + 1;
        return $this->getRedis()->zCount($key, $score, $option) + 1;
    }

    /**
     * @param  string $key
     * @param  mixed  $memberId
     * @return bool
     */
    public function isRank($key, $memberId)
    {
        return ($this->getRedis()->zRank($key, $memberId) !== false);
    }

}