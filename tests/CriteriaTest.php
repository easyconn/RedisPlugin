<?php

require_once __DIR__ . "/../src/mvc/model/Criteria.php";
require_once __DIR__ . "/../src/plugin/redis/Service.php";
require_once __DIR__ . "/../src/mvc/model/metadata/Redis.php";
require_once __DIR__ . "/MstItem.php";

class CriteriaTest extends \PHPUnit_Framework_TestCase
{

    /**
     * set up
     */
    public function setUp()
    {
//        // DI
//        $di = new Phalcon\Di\FactoryDefault();
//
//        // config
//        $config = new \Phalcon\Config();
//        $yml    = new \Phalcon\Config\Adapter\Yaml(__DIR__ . "/redis.yml");
//        $config->merge($yml->get("test"));
//
//        $di->set("config", function () use ($config) { return $config; }, true);
//
//        $dbService = new \RedisPlugin\Service();
//        $dbService->registration();
//
//        $di["modelsMetadata"] = function () use ($config)
//        {
//            var_dump($config->get("redis")->get("metadata")->toArray());
//            return new \RedisPlugin\Mvc\Model\Metadata\Redis(
//                $config->get("redis")->get("metadata")->toArray()
//            );
//        };
    }

    /**
     * test find first
     */
    public function testFindFirst()
    {
//        /** @var MstItem $mstItem */
//        $mstItem = MstItem::criteria()
//            ->add("id", 1)
//            ->findFirst();
//
//        $this->assertEquals($mstItem->getName(), "test_1");
    }

    /**
     * test find
     */
    public function testFind()
    {
//        /** @var MstItem[] $mstItem */
//        $mstItem = MstItem::criteria()
//            ->add("level", 1)
//            ->find();
//
//        $this->assertEquals(count($mstItem), 3);
//
//        $names = array("item_1", "item_5", "item_5");
//        foreach ($mstItem as $idx => $item) {
//            $this->assertEquals($item->getName(), $names[$idx]);
//        }
    }
}