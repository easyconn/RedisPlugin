<?php


namespace RedisPlugin;


interface RankingInterface
{
    /**
     * @return Ranking
     */
    static function getInstance();

    /**
     * @param  string $key
     * @param  mixed  $memberId
     * @param  string $option
     * @return int
     */
    public function getRank($key, $memberId, $option = "+inf");

    /**
     * @param  string $key
     * @param  mixed  $memberId
     * @return bool
     */
    public function isRank($key, $memberId);
}