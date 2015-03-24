CREATE TABLE IF NOT EXISTS `exp_favorites` (
	  `favorites_id` 		int(10) unsigned 		NOT NULL AUTO_INCREMENT,
	  `collection_id` 		int(10) unsigned 		NOT NULL DEFAULT '0',
	  `favoriter_id` 			int(10) unsigned 		NOT NULL DEFAULT '0',
  	  `type` 				varchar(16) 			NOT NULL DEFAULT 'entry_id',
	  `author_id` 			int(10) unsigned 		NOT NULL DEFAULT '0',
	  `entry_id` 			int(10) unsigned 		NOT NULL DEFAULT '0',
	  `item_id`				int(10) unsigned 		NOT NULL DEFAULT '0',
	  `site_id` 			smallint(3) unsigned 	NOT NULL DEFAULT '1',
	  `favorited_date` 		int(10) unsigned 		NOT NULL DEFAULT '0',
	  `notes` 				text,
  	  PRIMARY KEY 			(`favorites_id`),
	  KEY 					`collection_id`			(`collection_id`),
	  KEY 					`favoriter_id`			(`favoriter_id`),
	  KEY 					`type`					(`type`),
	  KEY 					`author_id` 			(`author_id`),
	  KEY 					`entry_id` 				(`entry_id`),
	  KEY 					`item_id`				(`item_id`),
	  KEY 					`site_id` 				(`site_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci;;

CREATE TABLE IF NOT EXISTS `exp_favorites_collections` (
	  `collection_id` 		int(10) unsigned 		NOT NULL AUTO_INCREMENT,
	  `collection_name` 	varchar(250) 			NOT NULL DEFAULT '',
	  `type` 				varchar(16) 			NOT NULL DEFAULT 'entry_id',
	  `default` 			char(1) 				NOT NULL DEFAULT 'n',
	  PRIMARY KEY 			(`collection_id`),
	  KEY 					`collection_name`		(`collection_name`),
	  KEY 					`type`					(`type`)
) CHARACTER SET utf8 COLLATE utf8_general_ci;;

CREATE TABLE IF NOT EXISTS `exp_favorites_prefs` (
	`pref_id` 				int(10) unsigned 		NOT NULL AUTO_INCREMENT,
	`language` 				varchar(20) 			NOT NULL DEFAULT '',
	`member_id` 			int(10) unsigned 		NOT NULL DEFAULT '0',
	`site_id` 				smallint(3) unsigned 	NOT NULL DEFAULT '1',
	`no_string` 			varchar(100) 			NOT NULL DEFAULT '',
	`no_login` 				varchar(100) 			NOT NULL DEFAULT '',
	`no_id` 				varchar(100) 			NOT NULL DEFAULT '',
	`id_not_found` 			varchar(100) 			NOT NULL DEFAULT '',
	`no_duplicates` 		varchar(100) 			NOT NULL DEFAULT '',
	`no_favorites` 			varchar(100) 			NOT NULL DEFAULT '',
	`no_delete` 			varchar(100) 			NOT NULL DEFAULT '',
	`success_add` 			varchar(100) 			NOT NULL DEFAULT '',
	`success_delete` 		varchar(100) 			NOT NULL DEFAULT '',
	`success_delete_all` 	varchar(100) 			NOT NULL DEFAULT '',
	`add_favorite` 			char(1) 				NOT NULL DEFAULT 'n',
	`collection_on_save` 	int(10) unsigned 		NOT NULL DEFAULT '0',
	PRIMARY KEY 			(`pref_id`),
	KEY 					`site_id` 				(`site_id`),
	KEY 					`member_id` 			(`member_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci;;

CREATE TABLE IF NOT EXISTS `exp_favorites_params` (
	`params_id`						int(10) unsigned	NOT NULL AUTO_INCREMENT,
	`entry_date`					int(10) unsigned	NOT NULL DEFAULT 0,
	`data`							text,
	PRIMARY KEY						(`params_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;