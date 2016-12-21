CREATE DATABASE IF NOT EXISTS `admin`;
CREATE DATABASE IF NOT EXISTS `common`;
CREATE DATABASE IF NOT EXISTS `user1`;
CREATE DATABASE IF NOT EXISTS `user2`;



CREATE TABLE IF NOT EXISTS `admin`.`admin_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_db_config_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `admin`.`admin_db_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `gravity` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

INSERT INTO `admin`.`admin_db_config` (`id`, `name`, `gravity`) VALUES
(1, 'dbUser1', 50),
(2, 'dbUser2', 50);




CREATE TABLE IF NOT EXISTS `common`.`mst_index` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `level` int(10) unsigned NOT NULL,
  `mode` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `common`.`mst_index`
  ADD KEY `type_idx` (`type`) USING BTREE,
  ADD KEY `level_mode_idx` (`level`, `mode`) USING BTREE;




CREATE TABLE IF NOT EXISTS `common`.`mst_database` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

INSERT INTO `common`.`mst_database` (`id`, `name`) VALUES
  (1, 'database_1'),
  (2, 'database_2'),
  (3, 'database_3'),
  (4, 'database_4'),
  (5, 'database_5'),
  (6, 'database_6');



CREATE TABLE IF NOT EXISTS `common`.`mst_equal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `common`.`mst_equal`
  ADD KEY `type_idx` (`type`) USING BTREE;

INSERT INTO `common`.`mst_equal` (`id`, `name`, `type`) VALUES
  (1, 'equal1', 1),
  (2, 'equal2', 1),
  (3, 'equal3', 1),
  (4, 'equal4', 2),
  (5, 'equal5', 2),
  (6, 'equal6', 2);




CREATE TABLE IF NOT EXISTS `common`.`mst_not_equal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `mode` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `common`.`mst_not_equal`
  ADD KEY `type_idx` (`type`) USING BTREE,
  ADD KEY `mode_idx` (`mode`) USING BTREE;

INSERT INTO `common`.`mst_not_equal` (`id`, `name`, `type`, `mode`) VALUES
  (1, 'not_equal1', 1, 0),
  (2, 'not_equal2', 1, 1),
  (3, 'not_equal3', 1, 1),
  (4, 'not_equal4', 2, 1),
  (5, 'not_equal5', 2, 1),
  (6, 'not_equal6', 2, 1);


CREATE TABLE IF NOT EXISTS `common`.`mst_greater_than` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `common`.`mst_greater_than`
  ADD KEY `type_idx` (`type`) USING BTREE;

INSERT INTO `common`.`mst_greater_than` (`id`, `name`, `type`) VALUES
  (1, 'greater_than1', 0),
  (2, 'greater_than2', 0),
  (3, 'greater_than3', 1),
  (4, 'greater_than4', 1),
  (5, 'greater_than5', 1),
  (6, 'greater_than6', 1);



CREATE TABLE IF NOT EXISTS `common`.`mst_less_than` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `common`.`mst_less_than`
  ADD KEY `type_idx` (`type`) USING BTREE;

INSERT INTO `common`.`mst_less_than` (`id`, `name`, `type`) VALUES
  (1, 'less_than1', 0),
  (2, 'less_than2', 0),
  (3, 'less_than3', 1),
  (4, 'less_than4', 1),
  (5, 'less_than5', 1),
  (6, 'less_than6', 1);


CREATE TABLE IF NOT EXISTS `user1`.`user` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `user1`.`user_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `user1`.`user_item`
  ADD KEY `user_id_idx` (`user_id`) USING BTREE;



CREATE TABLE IF NOT EXISTS `user2`.`user` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `user2`.`user_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `user2`.`user_item`
  ADD KEY `user_id_idx` (`user_id`) USING BTREE;