<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Control Panel
 *
 * The control panel master class that handles all of the CP requests and displaying.
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @version		4.0.3
 * @filesource	favorites/mcp.favorites.php
 */

require_once 'addon_builder/module_builder.php';

class Favorites_mcp extends Module_builder_favorites
{

	public $TYPE;

	public $type 					= 'entry_id';
	public $types 					= array('entry_id', 'member');
	public $types_lang 				= array();
	public $return_data				= '';
	public $entry_id				= '';
	public $member_id				= '';
	public $reserved_cat_segment	= '';
	public $cat_request				= '';

	// CP column display

	public $cp_cols_entry 			= array('item_id', 'title', 'collection_name', 'total');
	public $cp_cols_member 			= array('item_id', 'screen_name', 'collection_name', 'total');
	public $cp_cols_entry_filtered 	= array('item_id', 'title', 'collection_name', 'notes', 'favorited_date');
	public $cp_cols_member_filtered = array('item_id', 'screen_name', 'collection_name', 'notes', 'favorited_date');

	// Pagination variables

	public $paginate				= FALSE;
	public $pagination_links		= '';
	public $page_next				= '';
	public $page_previous			= '';
	public $current_page			= 1;
	public $total_pages				= 1;
	public $total_rows				= 0;
	public $p_limit					= '';
	public $p_page					= '';
	public $basepath				= '';
	public $uristr					= '';

	public $messages				= array();
	public $mfields					= array();

	public $prefs					= array();

	public $clean_site_id			= 0;


	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	bool		Enable calling of methods based on URI string
	 * @return	string
	 */

	public function __construct( $switch = TRUE )
	{

		parent::__construct();

		if ((bool) $switch === FALSE) return; // Install or Uninstall Request

		ee()->load->helper(array('text', 'form', 'url', 'security', 'string'));

		// --------------------------------------------
		//  Module Menu Items
		// --------------------------------------------

		$menu	= array(
			'module_favorite_entries'		=> array(
				'link'  => $this->base,
				'title' => lang('favorite_entries')
			),
			'module_favorite_members'		=> array(
				'link'  => $this->base . "&method=members",
				'title' => lang('favorite_members')
			),
			'module_statistics'		=> array(
				'link'  => $this->base . "&method=stats",
				'title' => lang('statistics')
			),
			'module_collections'		=> array(
				'link'  => $this->base . "&method=collections",
				'title' => lang('collections')
			),
			'module_preferences'	=> array(
				'link'  => $this->base . "&method=preferences",
				'title' => lang('preferences')
			),
			'module_demo_templates'	=> array(
				'link'  => $this->base . "&method=code_pack",
				'title' => lang('demo_templates')
			),
			'module_documentation'	=> array(
				'link'  => FAVORITES_DOCS_URL,
				'title' => lang('online_documentation')
			),
		);

		//$this->cached_vars['module_menu_highlight'] = 'module_home';
		$this->cached_vars['lang_module_version'] 	= lang('favorites_module_version');
		$this->cached_vars['module_version'] 		= FAVORITES_VERSION;
		$this->cached_vars['module_menu'] 			= $menu;

		ee()->cp->load_package_css('favorites_cp');
		ee()->cp->load_package_js('favorites_cp');
		ee()->load->model('favorites_members');
		ee()->load->model('favorites_collections');

		//saves a few function calls
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));
		foreach($this->types as $type)
		{
			$types_lang[$type] = lang('fav_type_'.$type);
		}
		$this->types_lang =		 $types_lang;
	}
	// END Favorites_cp_base()


	// --------------------------------------------------------------------
	// cp views
	// --------------------------------------------------------------------


	// --------------------------------------------------------------------

	/**
	 * Module's Main Homepage
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function index($message='')
	{
		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		$title_types = array('entry_id' => 'favorite_entries', 'member' => 'favorite_members');
		$this->add_crumb(lang($title_types[$this->type]));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_'.$title_types[$this->type];

		$this->cached_vars['version']         = FAVORITES_VERSION;

		//	----------------------------------------
		//	Get raw Favorites data
		//	----------------------------------------

		$this->cached_vars['selected_member_id']  = ee()->input->get_post('member_id', TRUE);
		$this->cached_vars['selected_collection'] = ee()->input->get_post('collection_id', TRUE);
		$this->cached_vars['member_cols']         = ee()->input->get_post('member_id', TRUE) ? $this->cp_cols_member_filtered : $this->cp_cols_member;
		$this->cached_vars['entry_cols']          = ee()->input->get_post('member_id', TRUE) ? $this->cp_cols_entry_filtered : $this->cp_cols_entry;
		$this->cached_vars['favorites']           = ee()->favorites_members->get_favorite_data(
			array(
				'favoriter_id' => $this->cached_vars['selected_member_id'],
				'type'         => $this->type
				)
			);

		//	----------------------------------------
		//	Convert date data
		//	----------------------------------------

		foreach($this->cached_vars['favorites'] as $type => $favorites)
		{
			foreach($favorites as $key => $data)
			{
				$this->cached_vars['favorites'][$type][$key]['favorited_date'] = $this->human_time($data['favorited_date']);
			}
		}

		// -----------------------------------------
		// Collect the collections
		//	----------------------------------------

		$collections = ee()->favorites_collections->collections($this->type);
		$collection_data = array();
		foreach($collections as $id => $data)
		{
			$collection_data[$id] = $data['collection_name'];
		}
		$this->cached_vars['collections']         = array('' => lang('filter_by_collection')) + $collection_data;

		//	----------------------------------------
		//	Favoriters
		//	----------------------------------------

		$this->cached_vars['favoriters']          = array('' => lang('filter_by_member')) + ee()->favorites_members->get_favoriters();
		$this->cached_vars['modify_url']   		  = 'C=addons_modules&M=show_module_cp&module=favorites&method=modify&type='.$this->type;

		switch($this->type)
		{
			case 'member':
				$this->cached_vars['search_url']   = 'C=addons_modules&M=show_module_cp&module=favorites&method=members';
				$this->cached_vars['current_page'] = AJAX_REQUEST ? $this->send_ajax_response($this->ee_cp_view('members.html')) : $this->ee_cp_view('members.html');
			break;
			case 'entry_id': default:
				$this->cached_vars['search_url']   = 'C=addons_modules&M=show_module_cp&module=favorites';
				$this->cached_vars['current_page'] = AJAX_REQUEST ? $this->send_ajax_response($this->ee_cp_view('home.html')) : $this->ee_cp_view('home.html');
			break;
		}

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');

	}
	// END index()

	// --------------------------------------------------------------------


	/**
	 * Edit or Delete Favorites
	 * @return string Section View
	 */
	public function modify()
	{

		//	----------------------------------------
		//	Get the old XID
		//	----------------------------------------

		if(version_compare(APP_VER, '2.7', '>=') && version_compare(APP_VER, '2.8', '<'))
		{
			ee()->security->restore_xid();
		}

		$action  = ee()->input->get_post('action', TRUE);
		$fav_ids = ee()->input->get_post('toggle', TRUE);
		$this->type    = ee()->input->get_post('type', TRUE);

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		$title_types = array('entry_id' => 'favorite_entries', 'member' => 'favorite_members');
		$this->add_crumb(lang($title_types[$this->type]));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_'.$title_types[$this->type];

		$this->cached_vars['version']         = FAVORITES_VERSION;

		if($action == 'delete')
		{
			$this->cached_vars['type']            = $this->type;
			$this->cached_vars['form_uri']        = $this->base.'&method=delete_favorite';
			$this->cached_vars['delete']          = $fav_ids;
			$this->cached_vars['delete_question'] = lang('delete_question');

			return $this->ee_cp_view('delete_confirm.html');
		}

		if($action == 'edit')
		{
			switch($this->type)
			{
				case 'member':
					$this->cached_vars['cols'] = $this->cp_cols_member_filtered;
				break;
				default:
					$this->cached_vars['cols'] = $this->cp_cols_entry_filtered;
				break;
			}
			$this->cached_vars['type']            = $this->type;
			$this->cached_vars['edit_page_title'] = lang('edit_'.$this->type);

			//	----------------------------------------
			//	Prep collection dropdown
			//	----------------------------------------
			$collections = ee()->favorites_collections->collections($this->type);
			$collection_dropdown = array();
			foreach ($collections as $id => $data)
			{
				$collection_dropdown[$id] = $data['collection_name'];
			}
			$this->cached_vars['collections']     = array('' => lang('no_collection')) + $collection_dropdown;

			$this->cached_vars['form_uri']        = $this->base.'&method=update_favorite';
			$this->cached_vars['favorites']       = ee()->favorites_members->get_favorite_data(
				array(
					'favs_ids' => $fav_ids,
					'type'     => $this->type
					)
				);
			$this->cached_vars['current_page']    = $this->ee_cp_view('edit.html');

			return $this->ee_cp_view('index.html');
		}

	} // END modify()

	// --------------------------------------------------------------------

	/**
	 * Delete Favorites
	 * @return void Redirect
	 */
	public function delete_favorite()
	{
		$delete_ids = ee()->input->get_post('delete', TRUE);

		foreach($delete_ids as $id)
		{
			ee()->db->query("DELETE FROM exp_favorites WHERE favorites_id = " . ee()->db->escape_str($id));
		}

		$this->type = ee()->input->get_post('type', TRUE);

		switch($this->type)
		{
			case 'member':
				$method = '&method=members';
			break;
			default:
				$method = '';
			break;
		}

		ee()->functions->redirect($this->base . $method);
	} // END delete_favorite

	// --------------------------------------------------------------------


	/**
	 * Update Favorites
	 * @return void Redirect
	 */
	public function update_favorite()
	{
		$fav_ids     = ee()->input->get_post('favorites_id', TRUE);
		$collections = ee()->input->get_post('collection_id', TRUE);
		$notes       = ee()->input->get_post('notes', TRUE);

		foreach($fav_ids as $id)
		{
			$data['collection_id'] = isset($collections[$id]) ? $collections[$id] : '';
			$data['notes']      = isset($notes[$id]) ? $notes[$id] : '';
			ee()->db->update('exp_favorites', $data, 'favorites_id = ' . ee()->db->escape_str($id));
		}

		$this->type = ee()->input->get_post('type', TRUE);

		switch($this->type)
		{
			case 'member':
				$method = '&method=members';
			break;
			default:
				$method = '';
			break;
		}

		ee()->functions->redirect($this->base . $method);
	} // END update_favorite

	// --------------------------------------------------------------------


	/**
	 * Module's Statistics Page
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function stats($message='')
	{
		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('statistics'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_statistics';
		//--------------------------------------
		//  lang
		//--------------------------------------

		$this->cached_vars['lang_no_entries_saved']			= lang('no_entries_saved');
		$this->cached_vars['lang_no_members_saved']			= lang('no_members_saved');
		$this->cached_vars['lang_statistics']				= lang('statistics');
		$this->cached_vars['lang_statistics_entries']		= lang('statistics_entries');
		$this->cached_vars['lang_statistics_members']		= lang('statistics_members');
		$this->cached_vars['lang_total_favorites']			= str_replace(' ', '&nbsp;', lang('total_favorites'));
		$this->cached_vars['lang_total_entries_saved']		= str_replace(' ', '&nbsp;', lang('total_entries_saved'));
		$this->cached_vars['lang_total_members_saved']		= str_replace(' ', '&nbsp;', lang('total_members_saved'));
		$this->cached_vars['lang_percent_entries_saved']	= str_replace(' ', '&nbsp;', lang('percent_entries_saved'));
		$this->cached_vars['lang_percent_members_saved']	= str_replace(' ', '&nbsp;', lang('percent_members_saved'));
		$this->cached_vars['lang_top_5']					= lang('top_5');

		//--------------------------------------
		//  Entry stat data
		//--------------------------------------

		$favorites	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	exp_favorites
			 WHERE	site_id = '{$this->clean_site_id}'
			 AND  	type = 'entry_id'"
		);

		$total_favorites = $favorites->row('count');

		$t_entries	= ee()->db->query(
			"SELECT 	favorites_id
			 FROM 		exp_favorites
			 WHERE  	site_id = '{$this->clean_site_id}'
			 AND 		type = 'entry_id'
			 GROUP BY 	item_id"
		);

		$t_entries	= $t_entries->num_rows();

		$entries	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	{$this->sc->db->channel_titles}
			 WHERE 	site_id = '{$this->clean_site_id}'"
		);

		$p_entries	= ( $entries->row('count') != 0 ) ?
						round( $t_entries / $entries->row('count') * 100, 2) : 0;

		$top5		= ee()->db->query(
			"SELECT 	t.title, COUNT(*) AS count
			 FROM 		exp_favorites	AS f
			 LEFT JOIN 	{$this->sc->db->channel_titles}	AS t
			 ON 		f.item_id 		= t.entry_id
			 WHERE 		f.site_id 		= '{$this->clean_site_id}'
			 AND 		f.type 			= 'entry_id'
			 GROUP BY 	f.item_id
			 ORDER BY 	count DESC
			 LIMIT 5"
		);

		$ranked		= array();

		if ( $top5->num_rows() > 0 )
		{
			foreach ( $top5->result_array() as $row )
			{
				$ranked[] = $row['title'];
			}
		}

		//	----------------------------------------
		//	Member stat data
		//	----------------------------------------

		$favorites	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM  	exp_favorites
			 WHERE 	site_id = '{$this->clean_site_id}'
			 AND   	type = 'member'"
		);

		$total_favorites_members = $favorites->row('count');

		$t_members	= ee()->db->query(
			"SELECT   	favorites_id
			 FROM 		exp_favorites
			 WHERE    	site_id = '{$this->clean_site_id}'
			 AND  		type = 'member'
			 GROUP BY 	item_id"
		);

		$t_members	= $t_members->num_rows();

		$members	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM	exp_members"
		);

		$p_members	= ( $members->row('count') != 0 ) ?
						round( $t_members / $members->row('count') * 100, 2) : 0;

		$top5		= ee()->db->query(
			"SELECT   	m.screen_name, COUNT(*) AS count
			 FROM 		exp_favorites	AS f
			 LEFT JOIN	exp_members	AS m
			 ON   		f.item_id 		= m.member_id
			 WHERE		f.site_id 		= '{$this->clean_site_id}'
			 AND  		f.type 			= 'member'
			 GROUP BY 	f.item_id
			 ORDER BY 	count DESC
			 LIMIT 5"
		);

		$ranked_members		= array();

		if ( $top5->num_rows() > 0 )
		{
			foreach ( $top5->result_array() as $row )
			{
				$ranked_members[] = $row['screen_name'];
			}
		}

		$this->cached_vars['version']         = FAVORITES_VERSION;

		//	----------------------------------------
		//	Entry stat values
		//	----------------------------------------

		$this->cached_vars['total_favorites'] = $total_favorites;
		$this->cached_vars['t_entries']       = $t_entries;
		$this->cached_vars['p_entries']       = $p_entries . NBS . '%';
		$this->cached_vars['ranked']          = $ranked;

		//	----------------------------------------
		//	Member stat values
		//	----------------------------------------

		$this->cached_vars['total_favorites_members'] = $total_favorites_members;
		$this->cached_vars['t_members']               = $t_members;
		$this->cached_vars['p_members']               = $p_members . NBS . '%';
		$this->cached_vars['ranked_members']          = $ranked_members;

		$this->cached_vars['current_page']    = $this->view('statistics.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	// END index()


	// --------------------------------------------------------------------

	/**
	 * Module's members page
	 *
	 * @access	public
	 * @param	string message
	 * @return	null
	 */

	public function members($message='')
	{
		$this->type = 'member';

		return $this->index();
	}
	// END members()


	// --------------------------------------------------------------------

	/**
	 * Module's individual member page
	 *
	 * @access	public
	 * @param	string message
	 * @return	null
	 */
	public function member($message='')
	{
		// -------------------------------------------
		//  Member id?
		// -------------------------------------------

		if ( ! ee()->input->get_post('member_id') )
		{
			return $this->show_error('no_member_id');
		}

		// -------------------------------------------
		//  Message
		// -------------------------------------------

		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('members'), $this->base . AMP . 'method=members');
		$this->add_crumb(lang('member'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_favorite_members';

		//--------------------------------------
		//  lang
		//--------------------------------------

		$this->cached_vars['lang_members']					= lang('members');
		$this->cached_vars['lang_no_member']				= lang('no_member');
		$this->cached_vars['lang_member']					= lang('member');
		$this->cached_vars['lang_total_favorites_saved']	= lang('total_favorites_saved');
		$this->cached_vars['lang_title']					= lang('title');
		$this->cached_vars['lang_favorited_date']			= lang('favorited_date');
		$this->cached_vars['lang_favorites']				= lang('members_who_favorited');
		$this->cached_vars['lang_member_id']				= lang('member_id');
		$this->cached_vars['lang_name']						= lang('name');
		$this->cached_vars['lang_join_date']				= lang('join_date');

		//--------------------------------------
		//  data
		//--------------------------------------

		$row_limit		= 50;
		$paginate		= '';
		$row_count		= 0;
		$member_data	= array();
		$member_id		= ee()->db->escape_str(ee()->input->get_post('member_id'));
		$member_stats 	= array();

		//individual member stats?
		$stat_query	= ee()->db->query(
			"SELECT member_id, screen_name, join_date
			 FROM 	exp_members
			 WHERE 	member_id = '$member_id'
			 LIMIT 	1"
		);

		if ($stat_query->num_rows() > 0)
		{
			$stat_query_result 			= $stat_query->result_array();
			$member_stats 				= $stat_query_result[0];

			//make date readable
			$member_stats['join_date'] 	= $this->human_time( $member_stats['join_date'] );
		}

		//get all members and favs associated with
		$sql	= "SELECT 		f.*, t.title
				   FROM 		exp_favorites AS f
				   LEFT JOIN 	{$this->sc->db->channel_titles} AS t
				   ON 			f.item_id 	= t.entry_id
				   WHERE 		f.site_id 	= '{$this->clean_site_id}'
				   AND 			f.favoriter_id	= '$member_id'
				   GROUP BY 	f.item_id
				   ORDER BY 	f.favorited_date DESC";
		$sql	= "SELECT 		f.*, m.screen_name, COUNT(*) AS count
				   FROM 		exp_favorites 	AS f
				   LEFT JOIN 	exp_members 	AS m
				   ON 			f.favoriter_id	= m.member_id
				   WHERE 		f.site_id 	= '{$this->clean_site_id}'
				   AND 			f.item_id 		= '$member_id'
				   GROUP BY 	m.member_id
				   ORDER BY 	f.favorited_date 	DESC";

		$query	= ee()->db->query($sql);

		//no data? kill here
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['member_stats']			= $member_stats;
			$this->cached_vars['member_data']			= $member_data;
			$this->cached_vars['paginate']				= $paginate;
			$this->cached_vars['current_page']			= $this->view('member.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}

		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ?
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=member' .
															AMP . 'member_id=' . $member_id;
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);
		}

		//load favorites data
		foreach ($query->result_array() as $row)
		{
			$item					= array();
			$item['row_count']      = ++$row_count;
			$item['url']            = $this->base . AMP . 'method=member' . AMP . 'member_id=' . $row['favoriter_id'];
			$item['screen_name']    = $row['screen_name'];
			$item['favorited_date']	= $this->human_time( $row['favorited_date'] );

			$member_data[]	= $item;
		}

		//PEW PEW PEW (cache data for view)
		$this->cached_vars['member_stats']			= $member_stats;
		$this->cached_vars['member_data']			= $member_data;
		$this->cached_vars['paginate']				= $paginate;

		$this->cached_vars['current_page']			= $this->view('member.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	// END member()


	// --------------------------------------------------------------------

	/**
	 * favorites entries
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function entries($message='')
	{
		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('entries'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_entries';

		//--------------------------------------
		//  lang
		//--------------------------------------

		$this->cached_vars['lang_no_entries']				= lang('no_entries');
		$this->cached_vars['lang_no_favorites']				= lang('no_favorites');
		$this->cached_vars['lang_entries']					= lang('entries');
		$this->cached_vars['lang_title']					= lang('title');
		$this->cached_vars['lang_total_favorites']			= lang('total_favorites');

		//--------------------------------------
		//  data
		//--------------------------------------

		$row_limit		= 50;
		$paginate		= '';
		$row_count		= 0;
		$entries   		= array();

		// -------------------------------------------
		//  Query
		// -------------------------------------------

		$sql	= "SELECT 		f.*, t.title, COUNT(*) AS count
				   FROM 		exp_favorites AS f
				   LEFT JOIN 	{$this->sc->db->channel_titles} AS t
				   ON 			t.entry_id 	= f.entry_id
				   WHERE 		f.site_id 	= '{$this->clean_site_id}'
				   GROUP BY 	f.entry_id
				   ORDER BY 	count DESC";

		$query	= ee()->db->query($sql);

		//no data? kill here
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['entries']			= $entries;
			$this->cached_vars['paginate']			= $paginate;
			$this->cached_vars['current_page']		= $this->view('entries.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}

		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ?
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=entries';
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);
		}

		foreach ( $query->result_array() as $row )
		{
			// The Entry Got Deleted Somehow
			// Likely through an API or a Weblog Being Axed
			// So, we remove it from the Favorites table and move on.

			if ($row['title'] == NULL)
			{
				ee()->db->query(
					"DELETE FROM 	exp_favorites
					 WHERE 			entry_id = '" . ee()->db->escape_str( $row['entry_id'] ) . "'"
				);
				continue;
			}

			$item				= array();

			$item['row_count']	= ++$row_count;
			$item['title']		= $row['title'];
			if ( $row['count'] != 0 )
			{
				$item['url']		= $this->base . AMP . 'method=entry' .
													AMP . 'entry_id=' . $row['entry_id'];
			}
			$item['count']		= $row['count'];

			$entries[]			= $item;
		}

		$this->cached_vars['entries']				= $entries;
		$this->cached_vars['paginate']				= $paginate;
		$this->cached_vars['current_page']			= $this->view('entries.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	// END entries()


	// --------------------------------------------------------------------

	/**
	 * favorites entry
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function entry($message='')
	{
		// -------------------------------------------
		//  entry id?
		// -------------------------------------------

		if ( ! ee()->input->get_post('entry_id') )
		{
			return $this->show_error('no_entry_id');
		}

		//message
		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('entries'), $this->base . AMP . 'method=entries');
		$this->add_crumb(lang('entry'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_favorite_entries';

		//--------------------------------------
		//  lang
		//--------------------------------------

		$this->cached_vars['lang_no_entries']     = lang('no_entries');
		$this->cached_vars['lang_entry']          = lang('entry');
		$this->cached_vars['lang_entry_id']       = lang('entry_id');
		$this->cached_vars['lang_title']          = lang('title');
		$this->cached_vars['lang_author']         = lang('author');
		$this->cached_vars['lang_no_entry']       = lang('no_entry');
		$this->cached_vars['lang_favorites']      = lang('members_who_favorited');
		$this->cached_vars['lang_member']         = lang('member');
		$this->cached_vars['lang_entry_date']     = lang('entry_date');
		$this->cached_vars['lang_favorited_date'] = lang('favorited_date');

		//--------------------------------------
		//  data
		//--------------------------------------

		$row_limit		= 50;
		$paginate		= '';
		$row_count		= 0;
		$entry_id		= ee()->db->escape_str(ee()->input->get_post('entry_id'));
		$entry_data		= array();
		$favorites		= array();

		// -------------------------------------------
		//  Query
		// -------------------------------------------

		$entry_query = ee()->db->query(
			"SELECT DISTINCT	t.*, m.screen_name
			 FROM 				{$this->sc->db->channel_titles} AS t
			 LEFT JOIN 			exp_members AS m
			 ON 				t.author_id = m.member_id
			 WHERE 				t.entry_id 	= '$entry_id'"
		);

		if ( $entry_query->num_rows() > 0 )
		{
			$entry_result				= $entry_query->result_array();
			$entry_data 				= $entry_result[0];

			//make date readable
			$entry_data['entry_date'] 	= $this->human_time( $entry_data['entry_date'] );
		}

		// -------------------------------------------
		//  favorites
		// -------------------------------------------

		$sql	= "SELECT 		f.*, m.screen_name, COUNT(*) AS count
				   FROM 		exp_favorites 	AS f
				   LEFT JOIN 	exp_members 	AS m
				   ON 			f.favoriter_id	= m.member_id
				   WHERE 		f.item_id 		= '$entry_id'
				   GROUP BY 	m.member_id
				   ORDER BY 	f.favorited_date 	DESC";

		$query	= ee()->db->query($sql);

		//no data? kill here
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['favorites']			= $favorites;
			$this->cached_vars['entry_data']		= $entry_data;
			$this->cached_vars['current_page']		= $this->view('entry.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}

		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ?
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=entry' .
															AMP . 'entry_id=' . $entry_id;
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);
		}

		foreach ( $query->result_array() as $row )
		{
			$item					= array();
			$item['row_count']      = ++$row_count;
			$item['url']            = $this->base . AMP . 'method=member' . AMP . 'member_id=' . $row['favoriter_id'];
			$item['screen_name']    = $row['screen_name'];
			$item['favorited_date'] = $this->human_time( $row['favorited_date'] );

			$favorites[]			= $item;
		}

		$this->cached_vars['favorites']			= $favorites;
		$this->cached_vars['entry_data']		= $entry_data;
		$this->cached_vars['paginate']			= $paginate;
		$this->cached_vars['current_page']		= $this->view('entry.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	// END entry()


	// --------------------------------------------------------------------

	/**
	 * favorites preferences
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function preferences($message='')
	{
		//message
		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('preferences'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_preferences';

		//--------------------------------------
		//  lang
		//--------------------------------------
		$site_label	= $this->check_yes(ee()->config->item('multiple_sites_enabled')) ?
								ee()->config->item('site_label') . ' :: ' : '';

		$this->cached_vars['lang_header']				= $site_label . lang('preferences');
		$this->cached_vars['lang_preferences']			= lang('preferences');
		$this->cached_vars['lang_update']				= lang('update');
		$this->cached_vars['lang_add_favorite']			= lang('add_favorite');
		$this->cached_vars['lang_yes']					= lang('yes');
		$this->cached_vars['lang_no']					= lang('no');
		$this->cached_vars['lang_collection_on_save']	= lang('collection_on_save');
		$this->cached_vars['lang_general_preferences']	= lang('general_preferences');

		//--------------------------------------
		//  data
		//--------------------------------------

		ee()->load->model('favorites_preference_model');

		$prefs = ee()->favorites_preference_model->get_preferences(
			$this->clean_site_id
		);

		$hidden_values	= array(
			'pref_id'	=> $prefs['pref_id'],
			'site_id'	=> $prefs['site_id'],
			'language'	=> $prefs['language'],
			'member_id' => ee()->session->userdata['member_id']
		);

		$selected 								= ' checked="checked" ';
		$this->cached_vars['add_favorite_yes'] 	= ($prefs['add_favorite'] == 'y') ?
														$selected : '';
		$this->cached_vars['add_favorite_no'] 	= ($prefs['add_favorite'] != 'y') ?
														$selected : '';
		$this->cached_vars['collections']         = ee()->favorites_collections->collections('entry_id');
		foreach($this->cached_vars['collections'] as $collection_id => $collection_arr)
		{
			if($prefs['collection_on_save'] == $collection_id)
			{
				$this->cached_vars['collections'][$collection_id]['selected'] = ' selected="selected"';
			}
			else
			{
				$this->cached_vars['collections'][$collection_id]['selected'] = '';
			}
		}

		//don't want these shown
		$exclude	= array(
			'pref_id',
			'member_id',
			'site_id',
			'language',
			'add_favorite'
		);

		$this->cached_vars['hidden_values']		= $hidden_values;
		$this->cached_vars['form_url']			= $this->base . AMP . 'method=update_preferences';
		$this->cached_vars['current_page']		= $this->view('preferences.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	// END preferences()


	// --------------------------------------------------------------------
	// END cp views
	// --------------------------------------------------------------------


	// --------------------------------------------------------------------

	/**
	 * Module Upgrading
	 *
	 * This function is not required by the 1.x branch of ExpressionEngine by default.  However,
	 * as the install and deinstall ones are, we are just going to keep the habit and include it
	 * anyhow.
	 *		- Originally, the $current variable was going to be passed via parameter, but as there might
	 *		  be a further use for such a variable throughout the module at a later date we made it
	 *		  a class variable.
	 *
	 *
	 * @access	public
	 * @return	bool
	 */

	public function favorites_module_update()
	{
		if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
		{
			$this->add_crumb(lang('update_favorites_module'));
			$this->build_crumbs();
			$this->cached_vars['form_url'] = $this->base . '&msg=update_successful';
			return $this->ee_cp_view('update_module.html');
		}

		require_once $this->addon_path . 'upd.favorites.php';

		$U = new Favorites_upd();

		if ($U->update() !== TRUE)
		{
			return ee()->functions->redirect($this->base . AMP . 'msg=update_failure');
		}
		else
		{
			return ee()->functions->redirect($this->base . AMP . 'msg=update_successful');
		}
	}
	// END favorites_module_update()


	// --------------------------------------------------------------------

	/**
	 * update preferences
	 *
	 * @access	public
	 * @return	null
	 */

	public function update_preferences()
	{
		ee()->load->model('favorites_preference_model');

		$defaults = ee()->favorites_preference_model->default_preferences;

		$save = array();
		foreach ($defaults as $key => $value)
		{
			$save[$key] = ee()->input->post($key, TRUE);
		}

		ee()->favorites_preference_model->save_preferences($save);

		return ee()->functions->redirect(
			$this->base . '&method=preferences&msg=prefs_updated'
		);
	}

	//	End update prefs


	// --------------------------------------------------------------------

	public function collections()
	{
		//	----------------------------------------
		//	Get the old XID
		//	----------------------------------------

		if(version_compare(APP_VER, '2.7', '>=') && version_compare(APP_VER, '2.8', '<'))
		{
			ee()->security->restore_xid();
		}

		$action  = ee()->input->get_post('action', TRUE);
		$fav_ids = ee()->input->get_post('toggle', TRUE);
		$type    = ee()->input->get_post('type', TRUE);

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		$this->add_crumb(lang('collections'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_collections';

		$this->cached_vars['version']         = FAVORITES_VERSION;

		$this->cached_vars['collections']        = ee()->favorites_collections->collections();
		$this->cached_vars['collections_member'] = ee()->favorites_collections->collections('member');
		$this->cached_vars['modify_url']   = 'C=addons_modules&M=show_module_cp&module=favorites&method=modify_collection';
		$this->cached_vars['create_url']   = $this->base.'&method=create_collection';
		$this->cached_vars['current_page'] = $this->ee_cp_view('collections.html');

		return $this->ee_cp_view('index.html');

	}

	//---------------------------------------------------------------------

	/**
	 * Create Collections
	 * @return string Section View
	 */
	public function create_collection()
	{
		$this->cached_vars['action']       = 'create';
		$this->cached_vars['types']        = $this->types_lang;
		$this->cached_vars['form_uri']     = $this->base.'&method=modify_collection';
		$this->cached_vars['current_page'] = $this->ee_cp_view('collections_create.html');

		return $this->ee_cp_view('index.html');
	}

	/**
	 * Edit or Delete Collections
	 * @return string Section View
	 */
	public function modify_collection()
	{

		//	----------------------------------------
		//	Get the old XID
		//	----------------------------------------

		if(version_compare(APP_VER, '2.7', '>=') && version_compare(APP_VER, '2.8', '<'))
		{
			ee()->security->restore_xid();
		}

		$action  = ee()->input->get_post('action', TRUE);
		$collection_ids = ee()->input->get_post('toggle', TRUE);

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		$this->add_crumb(lang('collections'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_collections';

		$this->cached_vars['version']         = FAVORITES_VERSION;

		if($action == 'delete')
		{
			if( ! ee()->input->get_post('toggle'))
			{
				return $this->show_error('no_collections_selected');
			}

			$this->cached_vars['form_uri']        = $this->base.'&method=delete_collection';
			$this->cached_vars['delete']          = $collection_ids;
			$this->cached_vars['delete_question'] = lang('delete_question_collection');
			$this->cached_vars['extra_questions'] = lang('delete_question_collection_choice').BR.form_dropdown('action',
				array(
					'convert_to_default' => lang('delete_question_collection_convert_to_default'),
					'delete_favorites' => lang('delete_question_collection_delete_favorites')
				)
			);

			return $this->ee_cp_view('delete_confirm.html');
		}

		if($action == 'edit')
		{
			if( ! ee()->input->get_post('toggle'))
			{
				return $this->show_error('no_collections_selected');
			}

			$collections     = ee()->favorites_collections->collections($this->types);

			foreach($collection_ids as $key => $id)
			{
				if(isset($collections[$id]))
				{
					$this->cached_vars['collections'][$id] = $collections[$id];
				}
			}

			$this->cached_vars['form_uri']        = $this->base.'&method=update_collection';
			$this->cached_vars['current_page']    = $this->ee_cp_view('collections_edit.html');

			return $this->ee_cp_view('index.html');
		}

		if($action == 'create')
		{
			$data['collection_name'] = ee()->input->get_post('collection_name', TRUE);
			$data['type']	= ee()->input->get_post('type', TRUE);

			if( ee()->favorites_collections->is_duplicate($data) === FALSE )
			{
				ee()->db->insert('exp_favorites_collections', $data);
				ee()->functions->redirect($this->base . '&method=collections');
			}
			else
			{
				show_error(lang('collection_name_empty_or_already_exists'));
			}
		}

		return $this->ee_cp_view('index.html');

	} // END modify_collection()


	// --------------------------------------------------------------------


	/**
	 * Update Collections
	 * @return void Redirect
	 */
	public function update_collection()
	{
		$collection_ids = ee()->input->get_post('collection_id', TRUE);
		$collections    = ee()->input->get_post('collection_name', TRUE);

		foreach($collection_ids as $id)
		{
			$data['collection_name'] = isset($collections[$id]) ? $collections[$id] : '';

			if( ! empty($data['collection_name']) && ee()->favorites_collections->is_duplicate($data) === FALSE )
			{
				ee()->db->update('exp_favorites_collections', $data, 'collection_id = ' . ee()->db->escape_str($id));
			}
		}

		ee()->functions->redirect($this->base . '&method=collections');
	} // END update_collection

	// --------------------------------------------------------------------


	/**
	 * Delete Collection
	 * @return void Redirect
	 */
	public function delete_collection()
	{
		$delete_ids          = ee()->input->get_post('delete', TRUE);
		$action              = ee()->input->get_post('action', TRUE);
		$default_collections = ee()->favorites_collections->default_collections();
		$collections         = ee()->favorites_collections->collections($this->types);

		foreach($delete_ids as $id)
		{
			if( isset($collections[$id]['type']) && isset($default_collections[$collections[$id]['type']]) )
			{
				$default_collection_id = $default_collections[$collections[$id]['type']]['collection_id'];

				if( in_array($action, array('delete_favorites', 'convert_to_default')) )
				{
					ee()->db->query("DELETE FROM exp_favorites_collections WHERE `default` != 'y' AND collection_id = " . ee()->db->escape_str($id));
				}

				// Delete Favorites assocaited with collection
				if($action == 'delete_favorites' && $id != $default_collection_id)
				{
					ee()->db->query("DELETE FROM exp_favorites WHERE collection_id = " . ee()->db->escape_str($id));
				}

				// Change collection to default
				if($action == 'convert_to_default')
				{
					//	----------------------------------------
					//	Find favorites with default collection id
					//	----------------------------------------

					$default_favs = array();

					$sql = "SELECT * FROM exp_favorites WHERE collection_id = " . ee()->db->escape_str($default_collection_id);

					$sql = ee()->db->query($sql);

					if($sql->num_rows() > 0)
					{
						foreach($sql->result_array() as $row)
						{
							$default_favs[$row['favorites_id']]['favoriter_id'] = $row['favoriter_id'];
							$default_favs[$row['favorites_id']]['item_id'] = $row['item_id'];
							$default_favs[$row['favorites_id']]['collection_id'] = $row['collection_id'];
						}
					}

					//	----------------------------------------
					//	Find favorites with the collection_id to be converted
					//	----------------------------------------

					$collection_delete_favs = array();

					$sql = "SELECT * FROM exp_favorites WHERE collection_id = " . ee()->db->escape_str($id);

					$sql = ee()->db->query($sql);

					if($sql->num_rows() > 0)
					{
						foreach($sql->result_array() as $row)
						{
							$collection_delete_favs[$row['favorites_id']]['favoriter_id'] = $row['favoriter_id'];
							$collection_delete_favs[$row['favorites_id']]['item_id']      = $row['item_id'];
						}
					}

					//	----------------------------------------
					//	Go through each favorite whose collection would
					//	get converted and delete if a default is already there,
					//	or update if it isn't
					//	----------------------------------------

					foreach($collection_delete_favs as $fav_id => $fav_data)
					{
						$delete = FALSE;

						foreach($default_favs as $default_fav_id => $default_fav_data)
						{
							if(
								$fav_data['favoriter_id'] == $default_fav_data['favoriter_id'] &&
							   	$fav_data['item_id'] == $default_fav_data['item_id']
							  )
							{
								$delete = TRUE;
							}
						}

						if($delete !== FALSE)
						{
							ee()->db->query("DELETE FROM exp_favorites WHERE collection_id = " . ee()->db->escape_str($id) . " AND favorites_id = " . ee()->db->escape_str($fav_id));
						}
						else
						{
							ee()->db->query("UPDATE exp_favorites SET collection_id = " . ee()->db->escape_str($default_collection_id) . " WHERE collection_id = " . ee()->db->escape_str($id) . " AND favorites_id = " . ee()->db->escape_str($fav_id));
						}
					}
				} // if($action == 'convert_to_default')
			}
		}

		$this->type = ee()->input->get_post('type', TRUE);

		$method = '&method=collections';

		ee()->functions->redirect($this->base . $method);
	} // END delete_collection


	// --------------------------------------------------------------------

	/**
	 * show_error
	 * @access	public
	 * @param	(string) error string
	 * @param	(bool) 	 is the string a lang pointer?
	 * @return	(string) returns html string of error page
	 */

	public function show_error($str, $do_lang = TRUE)
	{
		$this->cached_vars['error_message'] = $do_lang ? lang($str) : $str;
		return $this->ee_cp_view('error_page.html');
	}
	//END show_error


	// -----------------------------------------------------------------

	/**
	 * Code pack installer page
	 *
	 * @access public
	 * @param	string	$message	lang line for update message
	 * @return	string				html output
	 */

	public function code_pack($message = '')
	{
		//--------------------------------------------
		//	message
		//--------------------------------------------

		if ($message == '' AND ee()->input->get_post('msg') !== FALSE)
		{
			$message = lang(ee()->input->get_post('msg'));
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//	load vars from code pack lib
		// -------------------------------------

		$lib_name = str_replace('_', '', $this->lower_name) . 'codepack';
		$load_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->lower_name))) . 'CodePack';

		ee()->load->library($load_name, $lib_name);
		ee()->$lib_name->autoSetLang = true;

		$cpt = ee()->$lib_name->getTemplateDirectoryArray(
			$this->addon_path . 'code_pack/'
		);

		$screenshot = ee()->$lib_name->getCodePackImage(
			$this->sc->addon_theme_path . 'code_pack/',
			$this->sc->addon_theme_url . 'code_pack/'
		);

		$this->cached_vars['screenshot'] = $screenshot;

		$this->cached_vars['prefix'] = $this->lower_name . '_';

		$this->cached_vars['code_pack_templates'] = $cpt;

		$this->cached_vars['form_url'] = $this->base . '&method=code_pack_install';

		//--------------------------------------
		//  menus and page content
		//--------------------------------------

		$this->cached_vars['module_menu_highlight'] = 'module_demo_templates';

		$this->add_crumb(lang('demo_templates'));

		//$this->cached_vars['current_page'] = $this->view('code_pack.html', NULL, TRUE);

		//---------------------------------------------
		//  Load Homepage
		//---------------------------------------------

		return $this->ee_cp_view('code_pack.html');
	}
	//END code_pack


	// --------------------------------------------------------------------

	/**
	 * Code Pack Install
	 *
	 * @access public
	 * @param	string	$message	lang line for update message
	 * @return	string				html output
	 */

	public function code_pack_install()
	{
		$prefix = trim((string) ee()->input->get_post('prefix'));

		if ($prefix === '')
		{
			ee()->functions->redirect($this->base . '&method=code_pack');
		}

		// -------------------------------------
		//	load lib
		// -------------------------------------

		$lib_name = str_replace('_', '', $this->lower_name) . 'codepack';
		$load_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->lower_name))) . 'CodePack';

		ee()->load->library($load_name, $lib_name);
		ee()->$lib_name->autoSetLang = true;

		// -------------------------------------
		//	¡Las Variables en vivo! ¡Que divertido!
		// -------------------------------------

		$variables = array();

		$variables['code_pack_name']	= $this->lower_name . '_code_pack';
		$variables['code_pack_path']	= $this->addon_path . 'code_pack/';
		$variables['prefix']			= $prefix;

		// -------------------------------------
		//	install
		// -------------------------------------

		$details = ee()->$lib_name->getCodePackDetails($this->addon_path . 'code_pack/');

		$this->cached_vars['code_pack_name'] = $details['code_pack_name'];
		$this->cached_vars['code_pack_label'] = $details['code_pack_label'];

		$return = ee()->$lib_name->installCodePack($variables);

		$this->cached_vars = array_merge($this->cached_vars, $return);

		//--------------------------------------
		//  menus and page content
		//--------------------------------------

		$this->cached_vars['module_menu_highlight'] = 'module_demo_templates';

		$this->add_crumb(lang('demo_templates'), $this->base . '&method=code_pack');
		$this->add_crumb(lang('install_demo_templates'));

		//$this->cached_vars['current_page'] = $this->view('code_pack_install.html', NULL, TRUE);

		//---------------------------------------------
		//  Load Homepage
		//---------------------------------------------

		return $this->ee_cp_view('code_pack_install.html');
	}
	//END code_pack_install


	//---------------------------------------------------------------------

	/**
	 * _build_right_link
	 * @access	public
	 * @param	(string)	lang string
	 * @param	(string)	html link for right link
	 * @return	(null)
	 */

	function _build_right_link($lang_line, $link)
	{
		$msgs 		= array();
		$links 		= array();
		$ee2_links 	= array();

		if (is_array($lang_line))
		{
			for ($i = 0, $l= count($lang_line); $i < $l; $i++)
			{

				$ee2_links[$lang_line[$i]] = $link[$i];
			}
		}
		else
		{

			$ee2_links[$lang_line] = $link;
		}

		ee()->cp->set_right_nav($ee2_links);
	}
	// END _build_right_link()

	//---------------------------------------------------------------------
}
// END CLASS Favorites