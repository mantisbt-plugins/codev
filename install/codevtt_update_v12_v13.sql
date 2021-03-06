
-- this script is to be executed to update CodevTT DB v12 to v13.
SET SQL_MODE='ANSI';
-- -----------------

-- add WBS root element id to Command

ALTER TABLE "codev_command_table" ADD "wbs_id" int(11) DEFAULT NULL AFTER "team_id";


-- --------------------------------------------------------

--
-- Structure de la table "codev_wbs_table"
--

CREATE TABLE IF NOT EXISTS "codev_wbs_table" (
  "id" int(11) unsigned NOT NULL auto_increment,
  "root_id" int(11) unsigned default NULL,
  "parent_id" int(11) unsigned default NULL,
  "order" int(11) NOT NULL,
  "bug_id" int(11) default NULL,
  "expand" tinyint(4) NOT NULL DEFAULT '0',
  "title" varchar(255) default NULL,
  "icon" varchar(64) default NULL,
  "font" varchar(64) default NULL,
  "color" varchar(64) default NULL,
  PRIMARY KEY  ("id"),
  KEY "bug_id" ("bug_id"),
  KEY "parent_id" ("parent_id"),
  KEY "order" ("order")

) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- tag version
UPDATE "codev_config_table" SET "value"='13' WHERE "config_id"='database_version';
