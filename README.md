RedisPlugin for Phalcon (The correspondence of MySQL sharding.)
======

[![Latest Stable Version](https://poser.pugx.org/ienaga/phalcon-redis-plugin/v/stable)](https://packagist.org/packages/ienaga/phalcon-redis-plugin) [![Total Downloads](https://poser.pugx.org/ienaga/phalcon-redis-plugin/downloads)](https://packagist.org/packages/ienaga/phalcon-redis-plugin) [![Latest Unstable Version](https://poser.pugx.org/ienaga/phalcon-redis-plugin/v/unstable)](https://packagist.org/packages/ienaga/phalcon-redis-plugin) [![License](https://poser.pugx.org/ienaga/phalcon-redis-plugin/license)](https://packagist.org/packages/ienaga/phalcon-redis-plugin)

# Composer

```json
{
    "require": {
       "ienaga/phalcon-redis-plugin": "*"
    }
}
```


## Version

PHP 5.x/7.x

Phalcon 1.x/2.x/3.x 


## phpredis and YAML

```linux
sudo yum install libyaml libyaml-devel php-pecl-yaml php-pecl-redis
```

## app/config/config.php

### @see [PhalconConfig](https://github.com/ienaga/PhalconConfig)

```php
$configLoader = new \PhalconConfig\Loader();
return $configLoader
    ->setIgnore(["routing"]) // ignore yml names
    ->setEnvironment("stg") // default dev
    ->setBasePath(realpath(dirname(__FILE__) . "/../.."))
    ->load();
```

## app/config/database.yml

```yaml
prd:
stg:
dev:
  database:
    dbAdminMaster:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3301
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: true
    dbAdminSlave:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3311
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: false
    dbCommonMaster:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3301
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: false
    dbCommonSlave:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3311
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: false
    dbMember1Master:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3306
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: true
    dbMember1Slave:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3316
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: false
    dbMember2Master:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3307
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: true
    dbMember2Slave:
      adapter:  Mysql
      host:     127.0.0.1
      port:     3317
      username: root
      password: XXXXX
      dbname:   XXXXX
      charset:  utf8
      transaction: false
```

## app/config/redis.yml

```yaml
prd:
stg:
dev:
  redis:
    enabled:   true # false => cache off
    autoIndex: true # false => auto index off

    # 対象のカラムがModelに存在したら使用。上から順に優先。存在が確認できた時点でbreak
    prefix:
      columns:  # e.g. user_id, id, social_id, [account, password]
        - user_id
        - social_id
        - [account, password]
        - id

    # 共通のマスタがあれば登録「table_」と共有部分だけの記載はtable_*と同義
    # common
    common:
      enabled: false
      service:
        name: dbCommon

      dbs: # e.g.  master_, access_log
        - master_

    # Sharding設定
    shard:
      enabled: true # Shardingを使用しない時はfalse

    # Shardingのマスタ設定
    admin:
      service:
        name: dbAdmin
        
    # Shardingのマスタ設定
    admin:
      service:
        name: dbAdmin
      # ユーザマスタ
      # e.g.
      #    CREATE TABLE IF NOT EXISTS `admin_user` (
      #      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      #      `social_id` varchar(255) NOT NULL COMMENT 'ソーシャルID',
      #      `admin_config_db_id` tinyint(3) unsigned NOT NULL COMMENT 'AdminConfigDb.ID',
      #      `admin_flag` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=一般、1=管理者',
      #      `status_number` tinyint(3) unsigned NOT NULL DEFAULT '0',
      #      `created_at` datetime NOT NULL,
      #      `updated_at` datetime NOT NULL,
      #      PRIMARY KEY (`id`),
      #      UNIQUE KEY `social_id` (`social_id`)
      #    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
      model: # e.g. AdminUser or namespace \Project\AdminUser
      column: # e.g. admin_config_db_id

      # ユーザマスタの登録「table_」と共有部分だけの記載はtable_*と同義
      dbs: # e.g. admin_, user_ranking
        - admin_
        
      # Shardingをコントロールするテーブルとカラム
      #
      # e.g.
      #    CREATE TABLE IF NOT EXISTS `admin_config_db` (
      #      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      #      `name` varchar(50) NOT NULL COMMENT 'DBコンフィグ名',
      #      `gravity` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '重み(振り分け用)',
      #      `status_number` tinyint(3) unsigned NOT NULL DEFAULT '0',
      #      PRIMARY KEY (`id`)
      #    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
      #    INSERT INTO `admin_config_db` (`id`, `name`, `gravity`, `status_number`) VALUES
      #    (1, 'dbUser1', 50, 0),
      #    (2, 'dbUser2', 50, 0);
      # shard config master
      control:
        model:  # e.g. AdminConfigDb or namespace \Project\AdminConfigDb
        column: # e.g. name

    # schemaをキャッシュ
    metadata:
      host:   XXXXX
      port:   6379
      select: 0
      
    # servers
    server:
      dbMaster:
        host: XXXXX
        port: 6379
        select: 1 # redis select [データベースインデックス]
      dbSlave:
        host: XXXXX
        port: 6379
        select: 1
      dbCommonMaster:
        host: XXXXX
        port: 6379
        select: 0
      dbCommonSlave:
        host: XXXXX
        port: 6379
        select: 0
      dbMember1Master:
        host: XXXXX
        port: 6379
        select: 2
      dbMember1Slave:
        host: XXXXX
        port: 6379
        select: 2
      dbMember2Master:
        host: XXXXX
        port: 6379
        select: 3
      dbMember2Slave:
        host: XXXXX
        port: 6379
        select: 3
```


## app/config/services.php

```php
/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$dbService = new \RedisPlugin\Service();
$dbService->registration();

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->setShared('modelsMetadata', function () {
    return new \RedisPlugin\Mvc\Model\Metadata\Redis(
        $this->getConfig()->get("redis")->get("metadata")->toArray()
    );
});
```


## Criteria

```php
class Robot extends \RedisPlugin\Mvc\Model
{

    public static function findFirst($id, $type)
    {
        return self::criteria()
            ->add('id', $id)
            ->add('type', $type, Criteria::NOT_EQUAL)
            ->group('type')
            ->findFirst();
    }

    public static function find($id, $start, $end)
    {
         return self::criteria()
            ->add('id', array($id), Criteria::IN)
            ->add('type', array($start, $end), Criteria::BETWEEN)
            ->limit(10, 5) // limit, offset
            ->order('type DESC')
            ->find();
    }

    // ->cache($boolean)でキャッシュをコントロール
    public static function no_cache($id, $start, $end)
    {
         return self::criteria()
            ->add('id', array($id), Criteria::IN)
            ->add('type', array($start, $end), Criteria::BETWEEN)
            ->limit(10, 30)
            ->order('type DESC')
            ->cache(false)
            ->find();
    }

    // ->autoIndex($boolean)でautoIndexをコントロール
    public static function no_autoIndex($id, $start, $end)
    {
         return self::criteria()
            ->add('id', array($id), Criteria::IN)
            ->add('type', array($start, $end), Criteria::BETWEEN)
            ->limit(10, 30)
            ->order('type DESC')
            ->autoIndex(false)
            ->find();
    }
}
```


## save

```php
class Robot extends \RedisPlugin\Mvc\Model {}

$robot = new Robot;
$robot->setId($id);
$robot->setType($type);
$robot->save();

```

## update

```php
class Robot extends \RedisPlugin\Mvc\Model {}

Robot::criteria()
    ->add("user_status", 1)
    ->add("power", 100)
    ->set("status", 2)
    ->update();
```

```mysql
UPDATE `robot` SET `status` = 2 WHERE `user_status` = 1 AND `power` = 100;
```


## delete

```php
class Robot extends \RedisPlugin\Mvc\Model {}

Robot::criteria()
    ->add("user_status", 1)
    ->add("power", 100, Robot::GREATER_EQUAL)
    ->delete();
```

```mysql
DELETE FROM `robot` WHERE `user_status` = 1 AND `power` >= 100;
```

## count

```php
class Robot extends \RedisPlugin\Mvc\Model {}

Robot::criteria()
    ->add("user_status", 1)
    ->add("power", 100)
    ->add("status", 2)
    ->count();
```

## sum

```php
class Robot extends \RedisPlugin\Mvc\Model {}

Robot::criteria()
    ->add("user_status", 1)
    ->sum("price");
```


## autoIndex

※autoIndexをtrueにする事で、PRIMARYもしくはINDEXに一番マッチするクエリに並び替えて発行。

```php
class Robot extends \Phalcon\Mvc\Model
{
    // e.g. PRIMARY = type, INDEX = id, status

    public static function find($id, $status, $name)
    {
        return self::criteria()
            ->limit(10)
            ->add('name', $name)
            ->group('type')
            ->add('id', $id)
            ->order('id DESC')
            ->add('status', $status)
            ->find();
    }
}
```

```mysql
SELECT * FROM `table` 
WHERE `id` = :id: 
AND `status_number` = :status: 
AND `type` = :type:
GROUP BY `type`
ORDER BY `id` DESC
LIMIT 10
```

