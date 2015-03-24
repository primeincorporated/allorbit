<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Updater
 *
 * In charge of the install, uninstall, and updating of the module.
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @version		4.0.3
 * @filesource	favorites/upd.favorites.php
 */

require_once 'addon_builder/module_builder.php';

class Favorites_upd extends Module_builder_favorites
{

	public $module_actions		= array();
	public $hooks				= array();
	public $clean_site_id		= 0;

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------
		//  Module Actions
		// --------------------------------------------

		$this->module_actions = array(
			'save_favorites',
			'save_collection'
		);

		// --------------------------------------------
		//  Extension Hooks
		// --------------------------------------------

		$this->default_settings = array();

		$default = array(
			'class'			=> $this->extension_name,
			'settings'		=> '',
			'priority'		=> 10,
			'version'		=> FAVORITES_VERSION,
			'enabled'		=> 'y'
		);

		$this->hooks = array(
			array_merge($default, array(
				'method'		=> 'modify_sql',
				'hook'  		=> 'channel_module_alter_order',
				'priority'		=> 7,
			)),
			array_merge($default, array(
				'method'		=> 'add_favorite',
				'hook'  		=> 'entry_submission_end',
				'priority'		=> 7,
			)),
			array_merge($default, array(
				'method'		=> 'delete_members',
				'hook'  		=> 'cp_members_member_delete_end',
				'priority'		=> 7,
			)),
			array_merge($default, array(
				'method'		=> 'delete_entry',
				'hook'  		=> 'delete_entries_loop',
				'priority'		=> 7,
			)),
			array_merge($default, array(
				'method'		=> 'parse_favorites_data',
				'hook'  		=> 'channel_entries_tagdata',
			)),
		);

		//saves a few function calls
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));

	}
	// END Favorites_updater_base()


	// --------------------------------------------------------------------

	/**
	 * Module Installer
	 *
	 * @access	public
	 * @return	bool
	 */

	function install()
	{
		// Already installed, let's not install again.
		if ($this->database_version() !== FALSE)
		{
			return FALSE;
		}

		//clean up any old _ext form hooks left over in exp_extensions
		ee()->db->where('class', $this->extension_name)->delete('extensions');

		// --------------------------------------------
		//  Our Default Install
		// --------------------------------------------

		if ($this->default_module_install() == FALSE)
		{
			return FALSE;
		}

		// --------------------------------------------
		//  Module Install
		// --------------------------------------------

		ee()->db->insert(
			'exp_modules',
			array(
				'module_name'		=> $this->class_name,
				'module_version'	=> FAVORITES_VERSION,
				'has_cp_backend'	=> 'y'
			)
		);

		ee()->load->model('favorites_preference_model');

		//fill prefs tables with default prefs
		if ( ee()->db->table_exists('exp_sites') === TRUE )
		{
			$query	= ee()->db->select('site_id')->get('sites');

			foreach ( $query->result_array() as $row )
			{
				ee()->favorites_preference_model
					->set_default_site_prefs($row['site_id']);
			}
		}
		else
		{
			ee()->favorites_preference_model
				->set_default_site_prefs(1);
		}

		$std_collections[] = array(
			'collection_name' => 'Default',
			'type'            => 'entry_id',
			'default'         => 'y'
		);
		$std_collections[] = array(
			'collection_name' => 'Default',
			'type'            => 'member',
			'default'         => 'y'
		);

		foreach($std_collections as $data)
		{
			ee()->db->insert('exp_favorites_collections', $data);
		}

		return TRUE;
	}
	// END install()


	// --------------------------------------------------------------------

	/**
	 * Module Uninstaller
	 *
	 * @access	public
	 * @return	bool
	 */

	public function uninstall()
	{
		// Cannot uninstall what does not exist, right?
		if ($this->database_version() === FALSE)
		{
			return FALSE;
		}

		// --------------------------------------------
		//  Default Module Uninstall
		// --------------------------------------------

		if ($this->default_module_uninstall() == FALSE)
		{
			return FALSE;
		}

		return TRUE;
	}
	// END uninstall()


	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 *
	 * @access	public
	 * @return	bool
	 */

	function update()
	{

		//get this info BEFORE we run install_module_sql() so we can see where data
		// needs to be inserted
		$prefs_existed 		= ee()->db->table_exists( 'exp_favorites_prefs' );
		$favorites_existed 	= ee()->db->table_exists( 'exp_favorites' );

		// --------------------------------------------
		//  Default Module Update
		// --------------------------------------------

		//remove any old EXTs before doing update

		//clean up any old _ext form hooks left over in exp_extensions
		ee()->db->where('class', $this->extension_name)->delete('extensions');

		$this->default_module_update();

		//runs sql file and install tables that are missing
		$this->install_module_sql();

		// --------------------------------------------
		//  Database Table Adjustments
		// --------------------------------------------

		$sql	= array();

		if ( $this->column_exists( 'site_id', 'exp_favorites' ) === FALSE )
		{
			ee()->db->query("ALTER TABLE 	exp_favorites
					   ADD 		   	site_id smallint(3) unsigned NOT NULL default 1
					   AFTER 		member_id");
		}

		if ( $this->column_exists( 'type', 'exp_favorites' ) === FALSE )
		{
			ee()->db->query("ALTER TABLE 	exp_favorites
					   ADD 			type varchar(16) NOT NULL default 'entry_id'
					   AFTER 		favorites_id");
		}

		if ( $this->version_compare($this->database_version(), '<', '2.5.3') )
		{
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`author_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`entry_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`member_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`site_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`public`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`type`)");
		}

		//if the prefs were already there, set defaults and check for updates
		if ( $prefs_existed )
		{
			if ( $this->column_exists( 'site_id', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	exp_favorites_prefs
						   ADD 			site_id smallint(3) unsigned NOT NULL default 1
						   AFTER 		member_id");
			}

			if ( $this->column_exists( 'add_favorite', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	exp_favorites_prefs
						   ADD 			`add_favorite` char(1) NOT NULL DEFAULT 'n'");
			}

			if ( $this->column_exists( 'success_delete_all', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	`exp_favorites_prefs`
						   ADD 			`success_delete_all` VARCHAR(100) NOT NULL
						   AFTER 		`success_delete`");

				ee()->db->query("UPDATE 		`exp_favorites_prefs`
						   SET 			`success_delete_all` = " .
										"'All of your Favorites have been successfully deleted.'");
			}

			if( $this->column_exists( 'collection_on_save', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE exp_favorites_prefs ADD collection_on_save int(10) unsigned NOT NULL DEFAULT '0' AFTER add_favorite");
			}


			// -------------------------------------------
			//  Insert prefs for each site
			// -------------------------------------------

			ee()->load->add_package_path(PATH_THIRD . 'favorites');

			ee()->load->model('favorites_preference_model');

			if ( ee()->db->table_exists( 'exp_sites' ) === TRUE )
			{
				$query	= ee()->db->select('site_id')->get('sites');

				foreach ( $query->result_array() as $row )
				{
					ee()->favorites_preference_model
							->set_default_site_prefs($row['site_id']);
				}
			}
		}
		else
		{
			ee()->load->model('favorites_preference_model');

			if ( ee()->db->table_exists( 'exp_sites' ) === TRUE )
			{
				$query	= ee()->db->query( "SELECT site_id FROM exp_sites" );

				foreach ( $query->result_array() as $row )
				{
					ee()->favorites_preference_model
							->set_default_site_prefs($row['site_id']);
				}
			}
			else
			{
				ee()->favorites_preference_model
							->set_default_site_prefs(1);
			}
		}

		//run all stored queries
		foreach ($sql as $query)
		{
			ee()->db->query($query);
		}

		if ( $this->version_compare($this->database_version(), '<', '4.0.0') )
		{
			// -------------------------------------------
			//	Update exp_weblog_titles/exp_channel_titles
			//	to insert appropriate counts.
			// -------------------------------------------

			$sql	= array();

			$query	= ee()->db->query(
				"SELECT 	entry_id, COUNT(*) AS count
				 FROM 		exp_favorites
				 WHERE 		site_id = '{$this->clean_site_id}'
				 GROUP BY 	entry_id"
			);

			if ( $query->num_rows() > 0 )
			{
				foreach ( $query->result_array() as $row )
				{
					$sql[]	= ee()->db->update_string(
						$this->sc->db->channel_titles,
						array( 'favorites_count' => $row['count'] ),
						array( 'entry_id' 		 => $row['entry_id'] )
					);
				}
			}

			$query	= ee()->db->query(
				"SELECT 	entry_id, COUNT(*) AS count
				 FROM 		exp_favorites
				 WHERE 		site_id = '{$this->clean_site_id}'
				 AND 		public = 'y'
				 GROUP BY 	entry_id" );

			if ( $query->num_rows() > 0 )
			{
				foreach ( $query->result_array() as $row )
				{
					$sql[]	= ee()->db->update_string(
						$this->sc->db->channel_titles,
						array( 'favorites_count_public' => $row['count'] ),
						array( 'entry_id' 				=> $row['entry_id'] )
					);
				}
			}
		}

		// --------------------------------------------
		//  clean up site prefs
		// --------------------------------------------

		$site_id_query = ee()->db->query(
			"SELECT DISTINCT site_id
			 FROM 			 exp_favorites_prefs"
		);

		foreach($site_id_query->result_array() as $row)
		{
			$id_query = ee()->db->query(
				"SELECT *
				 FROM 	exp_favorites_prefs
				 WHERE 	site_id = '" . ee()->db->escape_str($row['site_id']) . "'"
			);

			//too many settings?
			if ($id_query->num_rows() > 1)
			{
				$first_count = TRUE;

				foreach($id_query->result_array() as $row_2)
				{
					//skip first item, we want to keep it
					if ($first_count)
					{
						$first_count = FALSE;
						continue;
					}

					ee()->db->query(
						"DELETE
						 FROM 	exp_favorites_prefs
						 WHERE 	pref_id = '" . ee()->db->escape_str($row_2['pref_id']) . "'"
					);
				}
			}
		}

		//remove the rogue column it if exists. Who put that there?
		if ($this->column_exists( 'auto_add_favorites', 'exp_favorites_prefs' ))
		{
			ee()->db->query("ALTER TABLE `exp_favorites_prefs` DROP `auto_add_favorites`");
		}

		//	----------------------------------------
		//	Version-specific updates
		//	----------------------------------------

		if ( $this->version_compare($this->database_version(), '<', '4.0.0') )
		{

			//	----------------------------------------
			//	Add collection column
			//	----------------------------------------

			if ( $this->column_exists( 'collection', 'exp_favorites' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	`exp_favorites`
						   		ADD 			`collection_id` INT(10) unsigned NOT NULL DEFAULT '0'
						   		AFTER 		`favorites_id`");
			}

			//	----------------------------------------
			//	Move member_id's column order.
			//	member_id is the ID of the currently logged in member
			//	----------------------------------------

			ee()->db->query("ALTER TABLE 	`exp_favorites`
						   		MODIFY 		`member_id` int(10) unsigned NOT NULL DEFAULT '0'
						   		AFTER 		`collection_id`");

			//	----------------------------------------
			//	Add favorited_member_id column, for
			//	favoriting member_ids
			//	----------------------------------------

			if ( $this->column_exists( 'favorited_member_id', 'exp_favorites' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	`exp_favorites`
						   		ADD 			`item_id` int(10) unsigned 		NOT NULL DEFAULT '0'
						   		AFTER 		`entry_id`");
			}

			//	----------------------------------------
			//	Convert public and private column to collections
			//	----------------------------------------

			$this->convert_to_collection();

			$sql	= array();

			//	----------------------------------------
			//	Change hook name, since it parses more than date now
			//	----------------------------------------

			$sql[] 	= "UPDATE exp_extensions
				SET method = 'parse_favorites_data'
				WHERE class = 'Favorites_ext'
				AND method = 'parse_favorites_date'
				AND hook = 'channel_entries_tagdata'";

			//	----------------------------------------
			//	Remove columns in native EE tables
			//	----------------------------------------

			$sql[]	= "ALTER TABLE {$this->sc->db->channel_titles} DROP favorites_count";

			$sql[]	= "ALTER TABLE {$this->sc->db->channel_titles} DROP favorites_count_public";

			$sql[]	= "ALTER TABLE exp_members DROP favorites_count";

			$sql[]	= "ALTER TABLE exp_members DROP favorites_count_public";

			foreach ($sql as $query)
			{
				ee()->db->query($query);
			}

			//	----------------------------------------
			//	Unifying favorited ids in one column: item_id
			//	----------------------------------------
			ee()->db->query("UPDATE 	`exp_favorites`
							SET item_id = entry_id
							WHERE type = 'entry_id'");

			ee()->db->query("ALTER TABLE exp_favorites DROP entry_id");

			//	----------------------------------------
			//	Create exp_favorites_collections table
			//	----------------------------------------

			ee()->db->query("CREATE TABLE IF NOT EXISTS `exp_favorites_collections` (
								  `collection_id` 		int(10) unsigned 		NOT NULL AUTO_INCREMENT,
								  `collection_name` 	varchar(250) 			NOT NULL DEFAULT '',
								  `type` 				varchar(16) 			NOT NULL DEFAULT 'entry_id',
								  `default` 			char(1) 				NOT NULL DEFAULT 'n',
								  PRIMARY KEY 			(`collection_id`),
								  KEY 					`collection_name`		(`collection_name`),
								  KEY 					`type`					(`type`)
							) CHARACTER SET utf8 COLLATE utf8_general_ci;;");

			//	----------------------------------------
			//	Populate exp_favorites_collections table
			//	with public and private collections
			//	----------------------------------------

			$data[] = array(
				'collection_name' => 'Public',
				'type'            => 'entry_id',
			);
			$data[] = array(
				'collection_name' => 'Private',
				'type'            => 'entry_id',
			);
			$data[] = array(
				'collection_name' => 'Default',
				'type'            => 'member',
			);

			foreach($data as $data)
			{
				ee()->db->insert('exp_favorites_collections', $data);
			}

			ee()->db->query("ALTER TABLE exp_favorites CHANGE member_id favoriter_id INT(10)");
			ee()->db->query("ALTER TABLE exp_favorites CHANGE entry_date favorited_date INT(10)");

			//	----------------------------------------
			//	Set default collections.
			//	First ID for each type of collection.
			//	----------------------------------------

			ee()->db->query("UPDATE exp_favorites_collections SET `default` = 'y'
							WHERE `type` = 'entry_id'
							ORDER BY collection_id
							LIMIT 1");
			ee()->db->query("UPDATE exp_favorites_collections SET `default` = 'y'
							WHERE `type` = 'member'
							ORDER BY collection_id
							LIMIT 1");
		}


		// --------------------------------------------
		//  Version Number Update - LAST!
		// --------------------------------------------

		ee()->db->update(
			'exp_modules',
			array(
				'module_version'	=> FAVORITES_VERSION
			),
			array(
				'module_name'		=> $this->class_name
			)
		);

		return TRUE;
	}
	// END update()

	// --------------------------------------------------------------------

	/**
	 * convert_to_collection
	 *
	 * @access	private
	 * @return	null
	 */
	private function convert_to_collection()
	{
		// Convert Public and Private Favorites to collection_ids
		ee()->db->query("UPDATE `exp_favorites` SET collection_id = 1 WHERE public = 'y'");
		ee()->db->query("UPDATE `exp_favorites` SET collection_id = 2 WHERE public = 'n' OR public = ''");

		// Drop the public column like a hot potato
		if ($this->column_exists( 'public', 'exp_favorites' ))
		{
			ee()->db->query("ALTER TABLE `exp_favorites` DROP `public`");
		}
	}
	// END convert_to_collection()

	// --------------------------------------------------------------------

}