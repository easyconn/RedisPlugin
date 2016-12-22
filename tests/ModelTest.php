<?php

require_once __DIR__ . "/../src/mvc/model/Criteria.php";
require_once __DIR__ . "/../src/mvc/Model.php";
require_once __DIR__ . "/../src/plugin/redis/Database.php";
require_once __DIR__ . "/model/MstIndex.php";
require_once __DIR__ . "/model/AdminUser.php";
require_once __DIR__ . "/model/AdminDbConfig.php";
require_once __DIR__ . "/model/User.php";
require_once __DIR__ . "/model/UserItem.php";
require_once __DIR__ . "/model/MstEqual.php";
require_once __DIR__ . "/model/MstNotEqual.php";
require_once __DIR__ . "/model/MstGreaterThan.php";
require_once __DIR__ . "/model/MstLessThan.php";
require_once __DIR__ . "/model/MstGreaterEqual.php";
require_once __DIR__ . "/model/MstLessEqual.php";
require_once __DIR__ . "/model/MstIsNull.php";
require_once __DIR__ . "/model/MstIsNotNull.php";
require_once __DIR__ . "/model/MstLike.php";
require_once __DIR__ . "/model/MstILike.php";



use \RedisPlugin\Mvc\Model;
use \RedisPlugin\Mvc\Model\Criteria;
use \RedisPlugin\Database;

class ModelTest extends \PHPUnit_Framework_TestCase
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
        $yml    = new \Phalcon\Config\Adapter\Yaml(__DIR__ . "/config/redis.yml");
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

        // meta data cache
        MstIndex::criteria()->find();
    }

    /**
     * test indexes pattern 1
     */
    public function testIndexes1()
    {
        $criteria = MstIndex::criteria()
            ->add("mode", 1)
            ->add("level", 2)
            ->add("id", 2);

        $param = $criteria->getConditions();
        $query = Model::buildParameters($param);

        $this->assertEquals($query[0], "[id] = :id: AND [mode] = :mode: AND [level] = :level:");
    }

    /**
     * test indexes pattern 2
     */
    public function testIndexes2()
    {
        $criteria = MstIndex::criteria()
            ->add("name", "test")
            ->add("mode", 10)
            ->add("type", 2);

        $param = $criteria->getConditions();
        $query = Model::buildParameters($param);

        $this->assertEquals($query[0], "[type] = :type: AND [name] = :name: AND [mode] = :mode:");
    }

    /**
     * test indexes pattern 3
     */
    public function testIndexes3()
    {
        $criteria = MstIndex::criteria()
            ->add("mode", 1)
            ->add("level", 2)
            ->add("name", "test");

        $param = $criteria->getConditions();
        $query = Model::buildParameters($param);

        $this->assertEquals($query[0], "[level] = :level: AND [mode] = :mode: AND [name] = :name:");
    }

    /**
     * test shard
     */
    public function testShard()
    {
        try {

            Database::beginTransaction();

            $totalGravity = AdminDbConfig::criteria()->sum("gravity");

            /** @var AdminDbConfig[] $adminDbConfigs */
            $adminDbConfigs = AdminDbConfig::criteria()->find();

            for ($i = 1; $i <= 10; $i++) {
                // 当選番号
                $prizeNo = mt_rand(0, $totalGravity);

                // 抽選
                $gravity  = 0;
                $configId = null;
                foreach ($adminDbConfigs as $adminDbConfig) {

                    $gravity += $adminDbConfig->getGravity();

                    if ($gravity >= $prizeNo) {
                        $configId = $adminDbConfig->getId();
                        break;
                    }
                }

                // 登録
                $adminUser = new AdminUser();
                $adminUser->setAdminDbConfigId($configId);
                $adminUser->save();

                $user = new User();
                $user->setId($adminUser->getId());
                $user->setName("test_user_". $i);
                $user->save();
            }

            Database::commit();

        } catch (\Exception $e) {

            Database::rollback($e);

        }

        sleep(1);

        /** @var User $user */
        $user = User::criteria()
            ->add("id", 1)
            ->findFirst();

        $this->assertEquals($user->getName(), "test_user_1");

        try {

            Database::beginTransaction();

            $user->setName("update_user_1");
            $user->save();

            Database::commit();

        } catch (\Exception $e) {

            Database::rollback($e);

        }

        sleep(1);

        /** @var User $user */
        $user = User::criteria()
            ->add("id", 1)
            ->findFirst();

        $this->assertEquals($user->getName(), "update_user_1");

        try {

            Database::beginTransaction();

            $userItem = new UserItem();
            $userItem->setUserId(1);
            $userItem->setItemId(10);
            $userItem->save();

            Database::commit();

        } catch (\Exception $e) {

            Database::rollback($e);

        }

        sleep(1);

        /** @var UserItem $userItem */
        $userItem = UserItem::criteria()
            ->add("user_id", 1)
            ->findFirst();

        $this->assertEquals($userItem->getItemId(), 10);
    }

    /**
     * test Equal
     */
    public function testEqual()
    {
        /** @var MstEqual $mstEqual */
        $mstEqual = MstEqual::criteria()
            ->add("id", 1)
            ->findFirst();

        $this->assertEquals($mstEqual->getId(), 1);

        /** @var MstEqual[] $mstEqual */
        $mstEqual = MstEqual::criteria()
            ->add("type", 2)
            ->find();

        $this->assertEquals(count($mstEqual), 3);

        foreach ($mstEqual as $key => $equal) {
            $this->assertEquals($equal->getType(), 2);
        }
    }

    /**
     * test Not Equal
     */
    public function testNotEqual()
    {
        /** @var MstNotEqual $mstNotEqual */
        $mstNotEqual = MstNotEqual::criteria()
            ->add("mode", 1, Criteria::NOT_EQUAL)
            ->findFirst();

        $this->assertEquals($mstNotEqual->getMode(), 0);

        /** @var MstNotEqual[] $mstNotEqual */
        $mstNotEqual = MstNotEqual::criteria()
            ->add("type", 1, Criteria::NOT_EQUAL)
            ->find();

        $this->assertEquals(count($mstNotEqual), 3);

        foreach ($mstNotEqual as $notEqual) {
            $this->assertEquals($notEqual->getType(), 2);
        }
    }

    /**
     * test GREATER_THAN
     */
    public function testGreaterThan()
    {
        /** @var MstGreaterThan $mstGreaterThan */
        $mstGreaterThan = MstGreaterThan::criteria()
            ->add("id", 5, Criteria::GREATER_THAN)
            ->findFirst();

        $this->assertEquals($mstGreaterThan->getId(), 6);

        /** @var MstGreaterThan[] $mstGreaterThan */
        $mstGreaterThan = MstGreaterThan::criteria()
            ->add("type", 0, Criteria::GREATER_THAN)
            ->find();

        $this->assertEquals(count($mstGreaterThan), 4);

        foreach ($mstGreaterThan as $greaterThan) {
            $this->assertEquals($greaterThan->getType(), 1);
        }
    }

    /**
     * test LESS_THAN
     */
    public function testLessThan()
    {
        /** @var MstLessThan $mstLessThan */
        $mstLessThan = MstLessThan::criteria()
            ->add("id", 2, Criteria::LESS_THAN)
            ->findFirst();

        $this->assertEquals($mstLessThan->getId(), 1);

        /** @var MstLessThan[] $mstLessThan */
        $mstLessThan = MstLessThan::criteria()
            ->add("type", 1, Criteria::LESS_THAN)
            ->find();

        $this->assertEquals(count($mstLessThan), 2);

        foreach ($mstLessThan as $lessThan) {
            $this->assertEquals($lessThan->getType(), 0);
        }
    }

    /**
     * test GREATER_EQUAL
     */
    public function testGreaterEqual()
    {
        /** @var MstGreaterEqual $mstGreaterEqual */
        $mstGreaterEqual = MstGreaterEqual::criteria()
            ->add("id", 6, Criteria::GREATER_EQUAL)
            ->findFirst();

        $this->assertEquals($mstGreaterEqual->getId(), 6);

        /** @var MstGreaterEqual[] $mstGreaterEqual */
        $mstGreaterEqual = MstGreaterEqual::criteria()
            ->add("type", 2, Criteria::GREATER_EQUAL)
            ->find();

        $this->assertEquals(count($mstGreaterEqual), 4);

        foreach ($mstGreaterEqual as $greaterEqual) {
            $this->assertEquals($greaterEqual->getType(), 2);
        }
    }

    /**
     * test LESS_EQUAL
     */
    public function testLessEqual()
    {
        /** @var MstLessEqual $mstLessEqual */
        $mstLessEqual = MstLessEqual::criteria()
            ->add("id", 1, Criteria::LESS_EQUAL)
            ->findFirst();

        $this->assertEquals($mstLessEqual->getId(), 1);

        /** @var MstLessEqual[] $mstLessEqual */
        $mstLessEqual = MstLessEqual::criteria()
            ->add("type", 1, Criteria::LESS_EQUAL)
            ->find();

        $this->assertEquals(count($mstLessEqual), 2);

        foreach ($mstLessEqual as $lessEqual) {
            $this->assertEquals($lessEqual->getMode(), 2);
        }
    }

    /**
     * test IS_NULL
     */
    public function testIsNull()
    {
        /** @var MstIsNull $mstIsNull */
        $mstIsNull = MstIsNull::criteria()
            ->isNull("type")
            ->findFirst();

        $this->assertEquals($mstIsNull->getId(), 1);

        /** @var MstIsNull[] $mstIsNull */
        $mstIsNull = MstIsNull::criteria()
            ->isNull("type")
            ->find();

        $this->assertEquals(count($mstIsNull), 3);

        foreach ($mstIsNull as $isNull) {
            $this->assertEquals($isNull->getMode(), 2);
        }
    }

    /**
     * test IS_NOT_NULL
     */
    public function testIsNotNull()
    {
        /** @var MstIsNotNull $mstIsNotNull */
        $mstIsNotNull = MstIsNotNull::criteria()
            ->isNotNull("type")
            ->findFirst();

        $this->assertEquals($mstIsNotNull->getId(), 4);

        /** @var MstIsNotNull[] $mstIsNotNull */
        $mstIsNotNull = MstIsNotNull::criteria()
            ->isNotNull("type")
            ->find();

        $this->assertEquals(count($mstIsNotNull), 3);

        foreach ($mstIsNotNull as $isNotNull) {
            $this->assertEquals($isNotNull->getMode(), 3);
        }
    }

    /**
     * test LIKE
     */
    public function testLike()
    {
        /** @var MstLike $mstLike */
        $mstLike = MstLike::criteria()
            ->add("name", "a", Criteria::LIKE)
            ->findFirst();

        $this->assertEquals($mstLike->getId(), 1);

        /** @var MstLike[] $mstLike */
        $mstLike = MstLike::criteria()
            ->add("name", "%d%", Criteria::LIKE)
            ->find();

        $this->assertEquals(count($mstLike), 3);

        foreach ($mstLike as $like) {
            $this->assertEquals($like->getMode(), 3);
        }
    }

    /**
     * test I_LIKE
     */
    public function testILike()
    {
        /** @var MstILike $mstILike */
        $mstILike = MstILike::criteria()
            ->add("name", "A", Criteria::I_LIKE)
            ->findFirst();

        $this->assertEquals($mstILike->getId(), 1);

        /** @var MstILike[] $mstILike */
        $mstILike = MstILike::criteria()
            ->add("name", "%D%", Criteria::I_LIKE)
            ->find();

        $this->assertEquals(count($mstILike), 3);

        foreach ($mstILike as $iLike) {
            $this->assertEquals($iLike->getMode(), 3);
        }
    }


}