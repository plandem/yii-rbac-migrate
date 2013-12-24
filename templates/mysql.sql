DELIMITER ;
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `{{trackPrefix}}track_child`;
DELIMITER //
CREATE PROCEDURE `{{trackPrefix}}track_child`(IN `_parent` VARCHAR(64), IN `_child` VARCHAR(64), IN `_operation` INT)
BEGIN
INSERT INTO {{trackPrefix}}child_log (parent, child, operation) VALUES (_parent, _child, _operation);
END//

DELIMITER ;
DROP PROCEDURE IF EXISTS `{{trackPrefix}}track_item`;
DELIMITER //
CREATE PROCEDURE `{{trackPrefix}}track_item`(IN `_name` VARCHAR(64), IN `_name_old` VARCHAR(64), IN `_operation` INT)
BEGIN
INSERT INTO {{trackPrefix}}item_log (name, name_old, operation) VALUES (_name, _name_old, _operation);
END//

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `{{trackPrefix}}child_log`
--

CREATE TABLE IF NOT EXISTS `{{trackPrefix}}child_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent` varchar(64) NOT NULL,
  `child` varchar(64) NOT NULL,
  `operation` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `{{trackPrefix}}item_log`
--

CREATE TABLE IF NOT EXISTS `{{trackPrefix}}item_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `name_old` varchar(64) DEFAULT NULL,
  `operation` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--
-- Triggers `auth_item`
--
DROP TRIGGER IF EXISTS `{{trackPrefix}}item_delete_trigger`;
DELIMITER //
CREATE TRIGGER `{{trackPrefix}}item_delete_trigger` AFTER DELETE ON `{{itemTable}}`
 FOR EACH ROW BEGIN
CALL {{trackPrefix}}track_item(OLD.{{itemName}}, NULL, 3);
END
//
DELIMITER ;
DROP TRIGGER IF EXISTS `{{trackPrefix}}item_insert_trigger`;
DELIMITER //
CREATE TRIGGER `{{trackPrefix}}item_insert_trigger` AFTER INSERT ON `{{itemTable}}`
 FOR EACH ROW BEGIN
CALL {{trackPrefix}}track_item(NEW.{{itemName}}, NULL, 1);
END
//
DELIMITER ;
DROP TRIGGER IF EXISTS `{{trackPrefix}}item_update_trigger`;
DELIMITER //
CREATE TRIGGER `{{trackPrefix}}item_update_trigger` AFTER UPDATE ON `{{itemTable}}`
 FOR EACH ROW BEGIN
CALL {{trackPrefix}}track_item(NEW.{{itemName}}, OLD.{{itemName}}, 2);
END
//
DELIMITER ;

--
-- Triggers `auth_item_child`
--
DROP TRIGGER IF EXISTS `{{trackPrefix}}child_delete`;
DELIMITER //
CREATE TRIGGER `{{trackPrefix}}child_delete` AFTER DELETE ON `{{itemChildTable}}`
 FOR EACH ROW BEGIN
CALL {{trackPrefix}}track_child(OLD.{{itemParent}}, OLD.{{itemChild}}, 3);
END
//
DELIMITER ;
DROP TRIGGER IF EXISTS `{{trackPrefix}}child_insert`;
DELIMITER //
CREATE TRIGGER `{{trackPrefix}}child_insert` AFTER INSERT ON `{{itemChildTable}}`
 FOR EACH ROW BEGIN
CALL {{trackPrefix}}track_child(NEW.{{itemParent}}, NEW.{{itemChild}}, 1);
END
//
DELIMITER ;
