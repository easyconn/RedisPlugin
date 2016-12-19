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
        // FactoryDefault
        $di = new Phalcon\Di\FactoryDefault();
        \Phalcon\DI::setDefault($di);

        // config
        $config = new \Phalcon\Config();
        $yml    = new \Phalcon\Config\Adapter\Yaml(__DIR__ . "/redis.yml");
        $config->merge($yml->get("test"));
        $di->set("config", function () use ($config) { return $config; }, true);

        // service
        $service = new \RedisPlugin\Service();
        $service->registration();

        // modelsMetadata
        $di->setShared("modelsMetadata", function () use ($di) {
            return new \RedisPlugin\Mvc\Model\Metadata\Redis(
                $this->getConfig()->get("redis")->get("metadata")->toArray()
            );
        });
    }

    /**
     * test find first
     */
    public function testFindFirst()
    {
        /** @var MstItem $mstItem */
        $mstItem = MstItem::criteria()
            ->add("id", 1)
            ->findFirst();

        $this->assertEquals($mstItem->getName(), "test_1");
    }

    /**
     * test find
     */
    public function testFind()
    {
        /** @var MstItem[] $mstItem */
        $mstItem = MstItem::criteria()
            ->add("level", 1)
            ->find();

        $this->assertEquals(count($mstItem), 3);

        $names = array("item_1", "item_3", "item_5");
        foreach ($mstItem as $idx => $item) {
            $this->assertEquals($item->getName(), $names[$idx]);
        }
    }

    /**
     * update test
     */
    public function testUpdate()
    {
        MstItem::criteria()
            ->set("name", "update")
            ->add("level", 1)
            ->update();

        /** @var MstItem[] $mstItem */
        $mstItem = MstItem::criteria()
            ->add("level", 1)
            ->find();

        $this->assertEquals(count($mstItem), 3);
        foreach ($mstItem as $idx => $item) {
            $this->assertEquals($item->getName(), "update");
        }
    }

    /**
     * test delete
     */
    public function testDelete()
    {
        MstItem::criteria()
            ->add("level", 1)
            ->delete();

        /** @var MstItem[] $mstItem */
        $mstItem = MstItem::criteria()
            ->add("level", 1)
            ->find();

        $this->assertEquals(count($mstItem), 0);
    }

}