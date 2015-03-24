<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Preference Model
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @filesource	favorites/models/favorites_preference_model.php
 */

require_once rtrim(realpath(rtrim(dirname(__FILE__), '/') . '/../'), '/') .
				'/addon_builder/addon_builder.php';

class Favorites_preference_model
{
	/**
	 * Default prefs
	 *
	 * @var array
	 * @see set_default_site_prefs
	 */
	public $default_preferences = array(
		//'pref_id' 				=> '',
		'site_id'				=> 1,
		'language'				=> 'english',
		//'member_id'             => '',
		'no_string'				=> 'We do not have a proper string.',
		'no_login'				=> 'You must be logged in before you can add or view favorites.',
		'no_id'					=> 'An entry id must be provided.',
		'id_not_found'			=> 'No entry was found for that entry id.',
		'no_duplicates'			=> 'This favorite has already been recorded.',
		'no_favorites'			=> 'No favorites have been recorded.',
		'no_delete'				=> 'That favorite does not exist.',
		'success_add'			=> 'Your favorite has been successfully added.',
		'success_delete'		=> 'Your Favorite has been successfully deleted.',
		'success_delete_all'	=> 'All of your Favorites have been successfully deleted',
		'add_favorite'			=> 'n',
		'collection_on_save'	=> ''
	);

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access public
	 */

	public function __construct()
	{
		$this->EE =& get_instance();
	}
	//END __construct


	// --------------------------------------------------------------------

	/**
	 * set_default_site_prefs
	 *
	 * @access	public
	 * @param	int		site id number to add defaults to
	 * @return	null
	 */

	public function set_default_site_prefs($site_id = -1)
	{
		if ($site_id < 0)
		{
			$site_id = ee()->config->item('site_id');
		}

		//no settings for this site yet?
		if (ee()->db
				->where('site_id', ceil($site_id))
				->count_all_results('favorites_prefs') == 0
		)
		{
			$prefs = $this->default_preferences;
			$prefs['site_id'] = ceil($site_id);
			ee()->db->insert('favorites_prefs', $prefs);
		}
		//END if
	}
	//END set_default_site_prefs


	// --------------------------------------------------------------------

	/**
	 * Get Preferences if any or set defaults to DB and get after
	 *
	 * @access public
	 * @param  integer $site_id	site_id for preferences (Default current site)
	 * @return array			array of preferences
	 */

	public function get_preferences($site_id = -1)
	{
		if ($site_id < 0)
		{
			$site_id = ee()->config->item('site_id');
		}

		$query	= ee()->db
						->where('site_id', ceil($site_id))
						->get('favorites_prefs');

		if ($query->num_rows() == 0)
		{
			$this->set_default_site_prefs($site_id);

			$query	= ee()->db
						->where('site_id', ceil($site_id))
						->get('favorites_prefs');
		}

		return $query->row_array();
	}
	//END get_preferences


	// --------------------------------------------------------------------

	/**
	 * Save preferences
	 *
	 * @access	public
	 * @param	array	$data		incoming data to save
	 * @param	integer	$site_id	site_id of prefs to save
	 * @return	boolean				success
	 */

	public function save_preferences($data = array(), $site_id = -1)
	{
		if ($site_id < 0)
		{
			$site_id = ee()->config->item('site_id');
		}

		if (empty($data))
		{
			return FALSE;
		}

		// -------------------------------------------
		//	Prep data
		// -------------------------------------------

		$prefs	= $save = $this->get_preferences($site_id);

		foreach ($prefs as $key => $val)
		{
			$save_key = isset($data[$key]) ? $data[$key] : FALSE;

			if ( $key != 'pref_id' AND
				 ! in_array($save_key, array(FALSE, ''), TRUE))
			{
				//make sure that add_favorite is correct
				if ($key == 'add_favorite' AND
					$save_key !== 'n')
				{
					$save_key = 'y';
				}

				$save[$key]	= $save_key;
			}
		}

		// -------------------------------------------
		//	Update
		// -------------------------------------------


		ee()->db->update(
			'favorites_prefs',
			$save,
			array('site_id' => $site_id)
		);

		return (ee()->db->affected_rows() > 0);
	}
	//END save_preferences
}
//END Favorites_preference_model