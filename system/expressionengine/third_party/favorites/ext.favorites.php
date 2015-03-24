<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Extension
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @version		4.0.3
 * @filesource	favorites/ext.favorites.php
 */


require_once 'addon_builder/extension_builder.php';

class Favorites_ext extends Extension_builder_favorites
{

	public $settings		= array();

	public $name			= '';
	public $version			= '';
	public $description		= '';
	public $settings_exist	= 'n';
	public $docs_url		= '';
	public $collections     = array();

	public $required_by		= array('module');


	//dummy for legacy?
	//channel_entries_tagdata hook
	public function parse_favorites_date($tagdata, $row, $channel_obj)
	{
		return (ee()->extensions->last_call !== false) ?
					ee()->extensions->last_call :
					$tagdata;
	}


	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct($settings = array())
	{
		parent::__construct('favorites');

		// --------------------------------------------
		//  Settings
		// --------------------------------------------

		$this->settings = $settings;
	}
	// END Favorites_extension_base()


	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension enabled, they have to install the module.
	 *
	 * @access	public
	 * @return	null
	 */

	public function activate_extension()
	{
	}
	// END activate_extension()


	// --------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension disabled, they have to uninstall the module.
	 *
	 * @access	public
	 * @return	null
	 */

	public function disable_extension()
	{
	}
	// END disable_extension()


	// --------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * A required method that we actually ignore because this extension is updated by its module
	 * and no other place.  We cannot redirect to the module upgrade script because we require a
	 * confirmation dialog, whereas extensions were designed to update automatically as they will try
	 * to call the update script on both the User and CP side.
	 *
	 * @access	public
	 * @return	null
	 */

	public function update_extension()
	{

	}
	// END update_extension()


	// --------------------------------------------------------------------

	/**
	 * Error Page
	 *
	 * @access	public
	 * @param	string	$error	Error message to display
	 * @return	null
	 */

	public function error_page($error = '')
	{
		$this->cached_vars['error_message'] = $error;

		$this->cached_vars['page_title'] = lang('error');

		// -------------------------------------
		//  Output
		// -------------------------------------

		$this->ee_cp_view('error_page.html');
	}
	// END error_page()

	//---------------------------------------------------------------------------------------------------


	// --------------------------------------------------------------------

	/**
	 * This alters the $end variable for
	 * the SQL query that grabs weblog
	 * entries.
	 *
	 * @access	public
	 * @param	string	$end sql to modify to re-arrange output
	 * @return	string  modified sql
	 */

	public function modify_sql ( $end )
	{
		//	----------------------------------
		//	Set return end
		//	----------------------------------

		if ( isset( ee()->extensions->last_call ) AND
			 ! in_array(ee()->extensions->last_call, array(FALSE, '' )) )
		{
			$r_end	= ee()->extensions->last_call;
		}
		else
		{
			$r_end	= $end;
		}

		//	----------------------------------
		//	Should we even execute?
		//	----------------------------------

		if ( in_array(ee()->TMPL->fetch_param('orderby_favorites'), array(FALSE, '' )) )
		{
			return $r_end;
		}

		//	----------------------------------
		//	Is the favorites module running?
		//	----------------------------------

		$query	= ee()->db->query(
			"SELECT 	COUNT(*) AS count
			 FROM 		exp_modules
			 WHERE 		module_name = 'Favorites'"
		);

		if ( $query->row('count') == 0 )
		{
			return $r_end;
		}

		//	----------------------------------
		//	Modify order by
		//	----------------------------------

		if ( preg_match( "/(ORDER BY t.sticky desc,)/s", $r_end, $match ) )
		{
			$end_a	= "ORDER BY t.sticky desc, t.favorites_count_public desc,";

			$r_end	= str_replace( $match['1'], $end_a, $r_end );

			return $r_end;
		}

		return $r_end;
	}
	// End modify_sql()


	// --------------------------------------------------------------------

	/**
	 * This records a favorite whenever
	 * an entry is submitted.
	 *
	 * @access	public
	 * @param	string	entry_id from extension call
	 * @param	string	(ee1) data, (ee2) meta info
	 * @param	string  (ee1) null, (ee2) data
	 * @return	bool  	success
	 */

	public function add_favorite ( $entry_id, $data, $ee2_data = FALSE )
	{
		//trying a different hook with ee2_data
		if($ee2_data)
		{
			$data = $ee2_data;
		}

		// -------------------------------------------
		//  Fail out if not logged in or not enabled
		// -------------------------------------------

		if ( ee()->session->userdata['member_id'] == 0 OR
			! $this->check_yes( $this->settings('add_favorite') ) )
		{
			return FALSE;
		}

		$collection_on_save = $this->settings('collection_on_save');

		$collection_id = ! empty($collection_on_save) ? $this->settings('collection_on_save') : 1;

		// -------------------------------------------
		//  Fail out if favorite has already
		//	been recorded for member and collection.
		// -------------------------------------------

		$query		= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	exp_favorites
			 WHERE	favoriter_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'
			 AND 	item_id = '" . ee()->db->escape_str($entry_id) . "'
			 AND 	type = 'entry_id'
			 AND 	collection_id = " . ee()->db->escape_str($collection_id)
		);

		if ( $query->row('count') >= 1 )
		{
			return FALSE;
		}

		// -------------------------------------------
		//  Insert
		// -------------------------------------------

		ee()->db->query(
			ee()->db->insert_string(
				'exp_favorites',
				array(
					'author_id'      => ee()->session->userdata['member_id'],
					'item_id'        => $entry_id,
					'favoriter_id'   => ee()->session->userdata['member_id'],
					'site_id'        => ee()->config->item('site_id'),
					'favorited_date' => ee()->localize->now,
					'notes'          => '',
					'type'           => 'entry_id',
					'collection_id'  => $collection_id
				)
			)
		);

		// -------------------------------------------
		//  Return success
		// -------------------------------------------

		return TRUE;
	}
	//	End add_favorite()


	// --------------------------------------------------------------------

	/**
	 * This prunes the favorites table of
	 * members that no longer exist
	 *
	 * @access	public
	 * @return	null
	 */

	public function delete_members ()
	{
		$deleted_members	= array();

		// --------------------------------------------
		//  Retrieve No Longer Existing Authors and Members
		// --------------------------------------------

		$query = ee()->db->query(
			"SELECT		author_id
			 FROM 		exp_favorites
			 WHERE 		author_id NOT
			 IN 		( SELECT member_id FROM exp_members )"
		);

		foreach($query->result_array() as $row)
		{
			$deleted_members[]	= $row['author_id'];
		}

		$query = ee()->db->query(
			"SELECT 	favoriter_id
			 FROM 		exp_favorites
			 WHERE 		favoriter_id NOT
			 IN 		( SELECT member_id FROM exp_members )"
		);

		foreach($query->result_array() as $row)
		{
			$deleted_members[]	= $row['favoriter_id'];
		}

		if (sizeof($deleted_members) == 0) {return;}

		$deleted_members	= array_unique($deleted_members);

		// --------------------------------------------
		//  Remove Favorites
		// --------------------------------------------

		$query	= ee()->db->query(
			"DELETE
			 FROM 	exp_favorites
			 WHERE 	author_id
			 IN 	('" . implode("','", ee()->db->escape_str( $deleted_members )) . "')
			 OR 	favoriter_id
			 IN 	('" . implode("','", ee()->db->escape_str( $deleted_members )) . "')"
		);

	}
	//	End delete_members()


	// --------------------------------------------------------------------

	/**
	 * This prunes the favorites table of
	 * an entry that no longer exists
	 *
	 * @access	public
	 * @param	string 	entry_id
	 * @param	string 	weblog_id
	 * @return	null
	 */

	public function delete_entry ( $entry_id, $weblog_id )
	{
		// --------------------------------------------
		//  Find Affected Members
		// --------------------------------------------

		$members = array();

		$query = ee()->db->query(
			"SELECT favorites_id
			 FROM 	exp_favorites
			 WHERE 	item_id = '" . ee()->db->escape_str( $entry_id ) . "'
			 AND type = 'entry_id'"
		);

		if ($query->num_rows() == 0) {return;}

		foreach($query->result_array() as $row)
		{
			$favorite_id[] = $row['favorites_id'];
		}

		$favorites_id = array_unique($favorite_id);

		// --------------------------------------------
		//  Now Delete
		// --------------------------------------------

		$query = ee()->db->query(
			"DELETE
			 FROM 	exp_favorites
			 WHERE 	favorites_id IN (" . rtrim(implode(',', ee()->db->escape_str( $favorites_id )), ',')  . ")"
		);

	}
	//	End delete_entry


	// --------------------------------------------------------------------

	/**
	 * Parse Favorites Date in Weblog Entry
	 *
	 * @access	public
	 * @param	string 	tagdata
	 * @param	string 	row to parse
	 * @param	string 	object data
	 * @return	null
	 */

	function parse_favorites_data ( $tagdata, $row, $obj )
	{

		if (ee()->extensions->last_call !== FALSE)
		{
			$tagdata = ee()->extensions->last_call;
		}

		//no date? GTFO
		if ( ! isset($obj->favorited_date) OR
			 $obj->favorited_date !== TRUE)
		{
			return $tagdata;
		}

		//	----------------------------------------
		//	Get total favorites from cache
		//	----------------------------------------

		$total_favorites = ee()->session->cache('favorites', 'total_favorites') ? ee()->session->cache('favorites', 'total_favorites') : array();

		$absolute_count = ee()->session->cache('favorites', 'absolute_count') ? ee()->session->cache('favorites', 'absolute_count') : array();

		$row['total_favorites'] = isset($total_favorites[$row['entry_id']]) ? $total_favorites[$row['entry_id']] : 0;

		$row['absolute_count'] = isset($absolute_count[$row['entry_id']]) ? $absolute_count[$row['entry_id']] : $row['absolute_count'];

		$row['absolute_results'] = ee()->session->cache('favorites', 'absolute_results') ? ee()->session->cache('favorites', 'absolute_results') : $row['absolute_results'];

		//code was ugly
		//This makes: 	$cache_fav_date[$marker]
		//the same as: 	ee()->session->cache['favorites']['favorites_date'][ee()->TMPL->marker]
		$cache_fav_date =& ee()->session->cache['favorites']['favorites_date'];
		$marker			=& ee()->TMPL->marker;

		if ( ! isset( $cache_fav_date[$marker] ) )
		{
			//cache all dates matched in the template
			if (preg_match_all("/" . LD . "favorites:date\s+format=[\"'](.*?)[\"']" . RD . "/s", $tagdata, $matches))
			{
				for ($i = 0, $l = count($matches[0]); $i < $l; $i++)
				{
					$matches[0][$i] = str_replace(array(LD,RD), '', $matches['0'][$i]);

					$cache_fav_date[$marker][$matches[0][$i]] = $this->fetch_date_params($matches[1][$i]);
				}
			}
		}

		//replace each data var out of the template with the correctly formated date
		if(isset($cache_fav_date[$marker]))
		{
			foreach($cache_fav_date[$marker] as $key => $format)
			{
				if ( ! isset(ee()->TMPL->var_single[$key])) continue;

				$val = ee()->TMPL->var_single[$key];

				if ( ! isset($row['favorites_date']))
				{
					$tagdata = ee()->TMPL->swap_var_single($key, '', $tagdata);
					//skip
					continue;
				}

				foreach ($format as $dvar)
				{
					$val = str_replace(
						$dvar,
						$this->convert_timestamp(
							$dvar,
							$row['favorites_date'],
							TRUE
						),
						$val
					);
				}

				$tagdata = ee()->TMPL->swap_var_single($key, $val, $tagdata);
			}
		}

		//	----------------------------------------
		//	Result parsing with favorites: prefix
		//	----------------------------------------

		$this->collections = ee()->favorites_collections->collections('entry_id');
		$collection_name = isset($this->collections[$row['collection_id']]) ? $this->collections[$row['collection_id']]['collection_name'] : '';

		$tagdata = str_replace( array(
				LD.'favorites:total_results'.RD,
				LD.'favorites:absolute_results'.RD,
				LD.'favorites:count'.RD,
				LD.'favorites:total_favorites'.RD,
				LD.'favorites:relevance'.RD,
				LD.'favorites:absolute_count'.RD,
				LD.'favorites:collection'.RD,
				LD.'favorites:notes'.RD,
			),
			array(
				$row['total_results'],
				$row['absolute_results'],
				$row['count'],
				$row['total_favorites'],
				$row['total_favorites'],
				$row['absolute_count'],
				$collection_name,
				$row['notes'],
			),
			$tagdata);

		return $tagdata;
	}
	//	End favorites_date()


	// --------------------------------------------------------------------

	/**
	 * Settings
	 *
	 * @access	public
	 * @param	mixed $setting	setting to find, or boolean false
	 * @return	mixed			setting or array of settings
	 */

	public function settings($setting = FALSE)
	{
		//no cache?
		if ( ! isset($this->cached['settings']))
		{
			ee()->load->model('favorites_preference_model');

			$this->cached['settings'] = ee()->favorites_preference_model->get_preferences();
		}

		if ($setting !== FALSE)
		{
			return isset($this->cached['settings'][$setting]) ?
					$this->cached['settings'][$setting] :
					FALSE;
		}

		return $this->cached['settings'];
	}
	//END settings
}
// END Class Favorites_extension