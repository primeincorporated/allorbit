<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - User Side
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @version		4.0.3
 * @filesource	favorites/mod.favorites.php
 */

require_once 'addon_builder/module_builder.php';

class Favorites extends Module_builder_favorites
{

	public $return_data				= '';

	public $disabled				= FALSE;

	public $params					= array();

	public $TYPE;

	public $type 					= 'entry_id';
	public $types 					= array('entry_id', 'member');
	public $collections 			= array();
	public $default_collections		= array();
	public $collection_id 			= 0;
	public $edit_mode 				= FALSE;
	public $entry_id				= '';
	public $member 					= '';
	public $member_fields 			= array();
	public $item_id					= '';
	public $member_id				= '';
	public $reserved_cat_segment	= '';
	public $cat_request				= '';
	public $saved 					= FALSE;
	public $not_saved 				= TRUE;

	// Pagination variables
	public $paginate				= FALSE;
	public $pagination_links		= '';
	public $page_next				= '';
	public $page_previous			= '';
	public $current_page			= 1;
	public $total_pages				= 1;
	public $total_rows				= 0;
	public $p_limit					= 20;
	public $p_page					= 0;
	public $basepath				= '';
	public $uristr					= '';

	public $messages				= array();
	public $mfields					= array();

	public $clean_site_id			= 0;

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct()
	{
		parent::__construct('favorites');

		ee()->load->helper(array('text', 'form', 'url', 'security', 'string'));

		ee()->load->model('favorites_collections');
		ee()->load->model('favorites_members');

		//saves a few function calls
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));
	}
	// END Favorites()


	// --------------------------------------------------------------------

	/**
	 * Theme Folder URL
	 *
	 * Mainly used for codepack
	 *
	 * @access	public
	 * @return	string	theme folder url with ending slash
	 */

	public function theme_folder_url()
	{
		return $this->sc->addon_theme_url;
	}
	//END theme_folder_url


	// --------------------------------------------------------------------


	/**
	 * Entry count
	 * This fetches a count of favorites for an entry.
	 *
	 * @access	public
	 * @return	null
	 */

	public function entry_count()
	{
		return $this->count();
	}

	// --------------------------------------------------------------------

	/**
	 * Favorites Count
	 * This fetches a count of favorites.
	 *
	 * @access	public
	 * @param   $type  The type of favorite to count
	 * @return	null
	 */

	public function count()
	{
		$tagdata	= ee()->TMPL->tagdata;

		$this->type = '';

		if( ee()->TMPL->fetch_param('entry_id') )
		{
			$this->item_id = ee()->TMPL->fetch_param('entry_id');
		}

		if( ee()->TMPL->fetch_param('type') )
		{
			$this->type = ee()->TMPL->fetch_param('type') == 'member' ? 'member' : 'entry_id';
		}

		//	----------------------------------------
		//	Set type to "member" if member_id or username
		//	parameter is present, and overriding
		//	entry_id parameter isn't
		//	----------------------------------------

		if( (ee()->TMPL->fetch_param('member_id') || ee()->TMPL->fetch_param('username')) && ! ee()->TMPL->fetch_param('entry_id'))
		{
			$this->type = "member";

			$this->item_id = ee()->TMPL->fetch_param('member_id') ? ee()->TMPL->fetch_param('member_id') : ee()->favorites_members->get_member_id_from_name($this->param('username'));

		}

		$sql = "/* Favorites count */ SELECT count(favorites_id) AS favorites_count
				FROM exp_favorites f
				WHERE 	f.site_id
				IN 		('" . implode("','", ee()->db->escape_str( ee()->TMPL->site_ids) ) . "')";

		if( ! empty($this->type) )
		{
			$sql .= " AND f.type = '" . ee()->db->escape_str( $this->type ) . "'";
		}

		//	----------------------------------------
		//	Add item_id query if an item_id is provided
		//	----------------------------------------

		if ( ctype_digit($this->item_id) )
		{
			$sql .= " AND f.item_id = " . ee()->db->escape_str( $this->item_id );
		}

		//	----------------------------------------
		//	Query favorites count for a specific memeber
		//	----------------------------------------

		if( ee()->TMPL->fetch_param('favorites_member_id') || ee()->TMPL->fetch_param('favorites_username') )
		{
			$favorites_member_id = ee()->TMPL->fetch_param('favorites_member_id') ? ee()->TMPL->fetch_param('favorites_member_id') : ee()->favorites_members->get_member_id_from_name($this->param('favorites_username'));
			$favorites_member_id = ctype_digit($favorites_member_id) ? $favorites_member_id : ee()->session->userdata['member_id'];
			$sql .= " AND f.favoriter_id = " . ee()->db->escape_str( $favorites_member_id );
		}

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('collection'))
		{
			$this->collections = ee()->favorites_collections->collections($this->type);

			$sql_collection = ' AND f.collection_id = 0';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		$query		= ee()->db->query( $sql );

		$result		= $query->result_array();

		if(ee()->TMPL->fetch_param('mode') != "legacy")
		{
			return $result[0]['favorites_count'];
		}
		else
		{
			//	----------------------------------------
			//	Secret Legacy mode: {exp:favorites:count} used as a tag pair.
			// 	Replace underscore by colon ":" for {favorites:count} style tags.
			// 	Hey, since the above isn't destroyed, legacy favorites_count ALSO works :D
			//	----------------------------------------
			foreach($result_row as $key => $val)
			{
				$result_row[str_replace('_', ':', $key)] = $val;
			}

			$cond		= $result_row;

			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

			foreach ( $result_row as $key => $value )
			{
				$tagdata	= str_replace( LD . $key . RD, $value, $tagdata );
			}

			return $tagdata;
		}
	}
	// END entry_count()


	// --------------------------------------------------------------------

	/**
	 * Member count
	 * This fetches the number of times that a
	 * member has been favorited.
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function member_count()
	{
		$this->type = 'member';
		return $this->count();
	}
	// END member_count()


	// --------------------------------------------------------------------

	/**
	 * Favorites count (deprecated)
	 * This is an alias of entry count
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function favorites_count()
	{
		return $this->entry_count();
	}
	// END favorites_count()


	// --------------------------------------------------------------------

	/**
	 * Author popularity
	 * This fetches a ranked count of authors by
	 * the number of favorites attributed to
	 * articles.
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function author_popularity()
	{

		ee()->load->model('favorites_members');

		$this->member_fields = ee()->favorites_members->get_member_fields();

		$r			= '';

		// -------------------------------------------
		//  Prep SQL
		// -------------------------------------------

		$sql		= "SELECT 		SQL_CALC_FOUND_ROWS m.*, md.*, COUNT(f.favorites_id) AS total_favorites,
									f.favorited_date
					   FROM 		exp_favorites 	AS f
					   LEFT JOIN 	exp_members 	AS m
					   ON 			m.member_id 	= f.author_id
					   LEFT JOIN 	exp_member_data AS md
					   ON 			f.author_id 	= md.member_id
					   WHERE 		f.site_id IN ('" . implode("','", ee()->TMPL->site_ids) . "')
					   AND 			m.member_id != 0 ";

		// ----------------------------------------------------
		//  Limit query by date range given in tag parameters
		// ----------------------------------------------------

		if (ee()->TMPL->fetch_param('favorites_start_on'))
		{
			$sql .= "AND f.favorited_date >= '" .
					$this->string_to_timestamp(
						ee()->TMPL->fetch_param('favorites_start_on')
					) .
					"' ";
		}

		if (ee()->TMPL->fetch_param('favorites_stop_before'))
		{
			$sql .= "AND f.favorited_date < '" .
					$this->string_to_timestamp(
						ee()->TMPL->fetch_param('favorites_stop_before')
					) .
					"' ";
		}

		// -------------------------------------------
		//  Members
		// -------------------------------------------

		if ( $this->_numeric( ee()->TMPL->fetch_param('member_id') ) === TRUE )
		{
			$sql	.= ee()->functions->sql_andor_string(
							ee()->TMPL->fetch_param('member_id'),
							'm.member_id'
					   );
		}
		elseif ( ee()->TMPL->fetch_param('username') )
		{
			$sql	.= ee()->functions->sql_andor_string(
							ee()->TMPL->fetch_param('username'),
							'm.username'
					   );
		}

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('collection'))
		{
			$this->collections = ee()->favorites_collections->collections('entry_id');

			$sql_collection = ' AND f.collection_id = 0 ';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		// -------------------------------------------
		//  Order By, My Little Monkeys!  FLY!!! FLY!!!
		// -------------------------------------------

		$sql	.= " GROUP BY f.author_id";

		switch (ee()->TMPL->fetch_param('orderby'))
		{
			case 'favorited_date'		: $sql .= " ORDER BY favorited_date";
				break;
			default						: $sql .= " ORDER BY total_favorites";
				break;
		}

		// -------------------------------------------
		//  Sort...you know, if you wanna...
		// -------------------------------------------

		switch (ee()->TMPL->fetch_param('sort'))
		{
			case 'asc'	: $sql .= " ASC";
				break;
			case 'desc'	: $sql .= " DESC";
				break;
			default		: $sql .= " DESC";
				break;
		}

		$this->parse_tagdata($sql);

		return $this->return_data;
	}
	// END author_popularity()


	// --------------------------------------------------------------------

	/**
	 * Author rank (deprecated)
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function rank_authors()
	{
		return $this->author_popularity();
	}
	//	END author_rank()

	// --------------------------------------------------------------------


	/**
	 * Favorite Info
	 *
	 * @access	public
	 * @return	string tagdata
	 */
	public function info()
	{
		ee()->load->model('favorites_members');
		ee()->load->model('favorites_collections');

		$this->params['type']		= $this->type;

		//	----------------------------------------
		//	Parameters
		//	----------------------------------------

		if(ee()->TMPL->fetch_param('member_id'))
		{
			$this->params['item_id']	= ee()->TMPL->fetch_param('member_id');
			$this->params['type']		= 'member';
		}

		if(ee()->TMPL->fetch_param('username'))
		{

			$this->params['item_id']	= ee()->favorites_members->get_member_id_from_name(ee()->TMPL->fetch_param('username'));
			$this->params['type']		= 'member';
		}

		if(ee()->TMPL->fetch_param('entry_id'))
		{
			$this->params['item_id']	= ee()->TMPL->fetch_param('entry_id');
			$this->params['type']		= 'entry_id';
		}

		if(ee()->TMPL->fetch_param('favoriter_id'))
		{
			$this->params['favoriter_id']	= ee()->TMPL->fetch_param('favoriter_id');
		}
		else
		{
			$this->params['favoriter_id']	= ee()->session->userdata('member_id');
		}

		if(ee()->TMPL->fetch_param('collection'))
		{
			$this->params['collection_id'] = ee()->favorites_collections->collection_id_from_name(ee()->TMPL->fetch_param('collection'), $this->params['type']);
		}


		$this->params['limit'] = ee()->TMPL->fetch_param('limit') ? ee()->TMPL->fetch_param('limit') : 100;

		$this->params['offset'] = ee()->TMPL->fetch_param('offset') ? ee()->TMPL->fetch_param('offset') : 0;

		//	----------------------------------------
		//	Show no results if logged out or
		//	non-numeric ID provided
		//	----------------------------------------

		if( ! is_numeric($this->params['favoriter_id']) || empty($this->params['favoriter_id']) )
		{
			return $this->no_results();
		}

		//	----------------------------------------
		//	Show error if ID isn't provided
		//	----------------------------------------

		if( ! isset($this->params['item_id']) || ! is_numeric($this->params['item_id']) )
		{
			return $this->error_page( array(
					lang('id_not_found')
					)
				);
		}

		$tagdata = ee()->TMPL->tagdata;

		// -------------------------------------
		//	Pagination
		// -------------------------------------

		//	----------------------------------------
		//	Find pagination URI offset
		//	----------------------------------------

		$pag_offset = 0;

		preg_match("#(/?P\d+)#", ee()->uri->uri_string, $matches);

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $matches) && ! $this->check_yes(ee()->TMPL->fetch_param('disable_pagination')) )
		{
			$pag_offset = substr( $matches['1'], 2 );
		}

		//	----------------------------------------
		//	Combine the offsets
		//	----------------------------------------

		$original_offset        = $this->params['offset'];
		$this->params['offset'] = $this->params['offset'] + $pag_offset;

		//	----------------------------------------
		//	Get Favorite data
		//	----------------------------------------

		$data = ee()->favorites_members->get_favorite_data($this->params);

		//	----------------------------------------
		//	Process {if favorites:no_results} if... uhh... no results
		//	----------------------------------------

		if(empty($data))
		{
			return $this->no_results();
		}

		$variables = $data[$this->params['type']];

		//	--------------------------------------
		//  Pagination start vars
		//	--------------------------------------

		$current_page		= 0;

		$pagination_prefix = stristr($tagdata, LD . 'favorites:paginate' . RD);

		//	----------------------------------------
		//	Get pagination info
		//	----------------------------------------

		$pagination_data = $this->universal_pagination(array(
			'total_results'			=> $variables[0]['favorites:absolute_results'],
			'tagdata'				=> $tagdata,
			'limit'					=> $this->params['limit'],
			'offset' 				=> $original_offset,
			'uri_string'			=> ee()->uri->uri_string,
			'prefix'				=> 'favorites:',
			'auto_paginate'			=> TRUE
		));

		//if we paginated, sort the data
		if ($pagination_data['paginate'] === TRUE)
		{
			$tagdata		= $pagination_data['tagdata'];
			$current_page 	= $pagination_data['pagination_page'];
		}

		$current_page = $current_page + $this->params['offset'];

		$count = 0;

		if($variables[0]['favorites:total_results'] > 0)
		{
			foreach($variables as $count => $row)
			{
				$variables[$count]['absolute_count']			= $this->params['offset'] + $count + 1;
				$variables[$count]['favorites:absolute_count']	= $this->params['offset'] + $count + 1;
			}
		}

		$tagdata = ee()->TMPL->parse_variables($tagdata, $variables);

		// -------------------------------------
		//	add pagination
		// -------------------------------------

		//prefix or no prefix?
		if (isset($pagination_prefix))
		{
			$tagdata = $this->parse_pagination(array(
				'prefix' 	=> 'favorites:',
				'tagdata' 	=> $tagdata
			));
		}
		else
		{
			$tagdata = $this->parse_pagination(array(
				'tagdata' 	=> $tagdata
			));
		}

		return $tagdata;

	} // END info

	// --------------------------------------------------------------------

	/**
	 * Favorite Edit
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function edit()
	{
		$this->edit_mode = TRUE;
		return $this->form();
	} // END edit


	// --------------------------------------------------------------------

	/**
	 * Favorite form
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function form()
	{

		//	----------------------------------------
		//	Don't display form if logged out
		//	----------------------------------------

		if(ee()->session->userdata('group_id') == 3)
		{
			return '';
		}

		//	----------------------------------------
		//	Parameters
		//	----------------------------------------

		if(ee()->TMPL->fetch_param('member_id'))
		{
			$this->params['member_id'] = ee()->TMPL->fetch_param('member_id');
			$this->item_id             = $this->params['member_id'];
			$this->member              = $this->item_id;
			$this->type                = 'member';
		}

		if(ee()->TMPL->fetch_param('username'))
		{
			ee()->load->model('favorites_members');

			$this->params['username'] = ee()->TMPL->fetch_param('username');
			$this->item_id            = ee()->favorites_members->get_member_id_from_name(ee()->TMPL->fetch_param('username'));
			$this->member             = $this->item_id;
			$this->type               = 'member';
		}

		if(ee()->TMPL->fetch_param('entry_id'))
		{
			$this->params['entry_id'] = ee()->TMPL->fetch_param('entry_id');
			$this->item_id            = $this->params['entry_id'];
			$this->type               = 'entry_id';
		}

		$this->params['type'] = $this->type;

		if(ee()->TMPL->fetch_param('collection'))
		{
			$this->params['collection'] = ee()->TMPL->fetch_param('collection');
		}

		if(ee()->TMPL->fetch_param('allow_new_collections'))
		{
			$this->params['allow_new_collections'] = ee()->TMPL->fetch_param('allow_new_collections');
		}

		if(ee()->TMPL->fetch_param('error_page'))
		{
			$this->params['error_page'] = ee()->TMPL->fetch_param('error_page');
		}

		if(ee()->TMPL->fetch_param('return'))
		{
			$this->params['return'] = ee()->TMPL->fetch_param('return');
		}
		else
		{
			$this->params['return'] = ee()->uri->uri_string;
		}

		//	----------------------------------------
		//	Edit Mode?
		//	----------------------------------------

		if( $this->check_yes(ee()->TMPL->fetch_param('edit_mode')) || $this->edit_mode !== FALSE )
		{
			$this->edit_mode           = TRUE;
			$this->params['edit_mode'] = 'y';
		}

		if(ee()->TMPL->fetch_param('favorite_id'))
		{
			$this->params['favorite_id'] = ee()->TMPL->fetch_param('favorite_id');
		}

		//	----------------------------------------
		//	Deal with tagdata
		//	----------------------------------------

		$tagdata = ee()->TMPL->tagdata;

		// {if saved} / {if not_saved} conditionals

		$tagdata = $this->saved();

		//	----------------------------------------
		//	Getting data if in edit mode
		//	----------------------------------------

		if($this->edit_mode)
		{
			$fav_data = ee()->favorites_members->get_favorite_data( array('favs_ids' => array(ee()->TMPL->fetch_param('favorite_id'))) );

			if(empty($fav_data))
			{
				return $this->error_page( array(
						lang('no_favorite_id')
						)
					);
			}

			// Set a few variables that wouldn't be set
			// otherwise when in edit mode
			foreach($fav_data as $type => $data)
			{
				$this->params['type'] = $type; // This is sent to save_favorites to know the type
				$this->type           = $type;
				$this->item_id        = $data[0]['favorites:item_id'];
				$this->collection_id  = $data[0]['favorites:collection_id'];
				$favorites_notes      = $data[0]['favorites:notes'];
			}
		}

		//	----------------------------------------
		//	Parse {favorites:collections} tag pair
		//	----------------------------------------

		$tagdata = $this->parse_collections($tagdata);

		//	----------------------------------------
		//	Parse notes
		//	----------------------------------------

		if($this->edit_mode)
		{
			$tagdata = str_replace(LD . 'favorites:notes' . RD, $favorites_notes, $tagdata);
		}

		//	----------------------------------------
		//	Data to send to build_form()
		//	----------------------------------------

		$data['tagdata'] = $tagdata;
		$data['method'] = 'post';
		$data['action']	= $this->get_action_url('save_favorites');
		$data['hidden_fields']['params_id'] = $this->insert_params();

		$output = $this->build_form($data);

		return $output;
	}
	// END form()


	// --------------------------------------------------------------------

	/**
	 * Collection Form, to create/edit/delete collections
	 * @return string tagdata
	 */
	public function collection_form()
	{
		//	----------------------------------------
		//	Parameters
		//	----------------------------------------

		if(ee()->TMPL->fetch_param('type'))
		{
			if( ee()->TMPL->fetch_param('type') == 'member')
			{
				$this->type = 'member';
			}
			else
			{
				$this->type = 'entry_id';
			}

			$this->params['type'] = $this->type;
		}

		if( $this->check_yes(ee()->TMPL->fetch_param('edit_mode')) )
		{
			$this->edit_mode           = TRUE;
			$this->params['edit_mode'] = 'y';
		}

		if(ee()->TMPL->fetch_param('error_page'))
		{
			$this->params['error_page'] = ee()->TMPL->fetch_param('error_page');
		}

		$tagdata = ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Deal with when in edit mode tagdata
		//	----------------------------------------

		$collection_name = '';

		if($this->edit_mode)
		{
			if( ! ee()->TMPL->fetch_param('collection_id') )
			{
				return $this->error_page( array(
						lang('invalid_collection_id')
						)
					);
			}

			$this->params['collection_id'] = ee()->TMPL->fetch_param('collection_id');
			$collections                   = ee()->favorites_collections->collections($this->type);
			$saved_collections             = ee()->favorites_collections->saved_collections($this->type);

			// /* Limits editing to saved collections by user */
			// if(empty($saved_collections) || ! isset($saved_collections[ee()->TMPL->fetch_param('collection_id')]) )
			// {
			// 	return $this->error_page( array(
			// 			lang('invalid_collection_id')
			// 			)
			// 		);
			// }
			// else
			// {
				$collection_name = $collections[ee()->TMPL->fetch_param('collection_id')]['collection_name'];
			// }


		}

		$tagdata = str_replace(LD . 'favorites:collection_name' . RD, $collection_name, $tagdata);

		if(ee()->TMPL->fetch_param('return'))
		{
			$this->params['return'] = ee()->TMPL->fetch_param('return');
		}
		else
		{
			$this->params['return'] = ee()->uri->uri_string;
		}

		//	----------------------------------------
		//	Data to send to build_form()
		//	----------------------------------------

		$data['tagdata']                    = $tagdata;
		$data['method']                     = 'post';
		$data['action']                     = $this->get_action_url('save_collection');
		$data['hidden_fields']['params_id'] = $this->insert_params();

		$output = $this->build_form($data);

		return $output;


	} // END collection_form()


	// --------------------------------------------------------------------

	/**
	 * parse_collections - parse {favorites:collections} tag pair
	 *
	 * @access	private
	 * @param	string	$tagdata	The current tagdata
	 * @param   int 	$id 		The ID of the favorite type (eg. entry_id, member_id)
	 * @return	string 				The parsed tagdata
	 */

	private function parse_collections ($tagdata)
	{
		$matches         = array();
		$tag             = 'favorites:collections';
		$collection_data = ee()->favorites_collections->collections($this->type);
		$collections     = array();

		foreach($collection_data as $id => $data)
		{
			$collections[$id]['name'] = $data['collection_name'];
			$collections[$id]['id']   = $data['collection_id'];
		}

		$saved_collections = ee()->favorites_collections->saved_collections($this->type, $this->item_id);
		$paircontents      = '';

		preg_match_all('/' . LD . $tag . RD . '(.*?)' . LD . '\/' . $tag . RD . '/ms', $tagdata, $matches, PREG_SET_ORDER);

		if ($matches AND
					isset($matches[0]) AND
					! empty($matches[0]))
		{
			// Go through each match. More than once if the tag pair is present more than once
			foreach ($matches as $key => $value)
			{
				// Loop through tag pair contents
				if( ! empty($collections))
				{
					foreach($collections as $id => $collection)
					{
						// Check if entry was saved for collection.
						$saved_collection = in_array($collection['name'], $saved_collections) ? $collection['name'] : FALSE;

						// Check if the currently looping collection corresponds to
						// the current favorite's saved collection in an {exp:favorites:edit} form.
						if($this->edit_mode && ! empty($this->collection_id))
						{
							$currently_looped_is_saved = $this->collection_id == $collection['id'] ? TRUE : FALSE;
						}
						else
						{
							$currently_looped_is_saved = FALSE;
						}

						$cond['favorites:collection_id']    = $collection['id'];
						$cond['favorites:collection']       = $collection['name'];
						$cond['favorites:saved_collection'] = $saved_collection;
						$cond['favorites:selected']         = $selected = $currently_looped_is_saved !== FALSE ? 'selected="selected"': '';
						$cond['favorites:checked']          = $checked = $currently_looped_is_saved !== FALSE ? 'checked="checked"': '';

						$pre_replace_content = $value[1];
						$pre_replace_content = ee()->functions->prep_conditionals($pre_replace_content, $cond);

						$paircontents .= str_replace(
								array(	LD . 'favorites:collection_id' . RD,
										LD . 'favorites:collection' . RD,
										LD . 'favorites:saved_collection' . RD,
										LD . 'favorites:selected' . RD,
										LD . 'favorites:checked' . RD
									),
								array($collection['id'], $collection['name'], $saved_collection, $selected, $checked),
								$pre_replace_content
						);


					}
				}

				// Remove tag pair, replace with parsed contents
				$tagdata = str_replace($value[0], $paircontents, $tagdata);

				// Reset the parsed string contents
				$paircontents = '';
			}
		}

		return $tagdata;
	}
	//	End parse_collections


	// --------------------------------------------------------------------

	/**
	 * insert_params - adds multiple params to stored params
	 *
	 * @access	private
	 * @param	array	$param	sassociative array of params to send
	 * @return	mixed			insert id or false
	 */

	private function insert_params ( $params = array() )
	{
		ee()->load->model('favorites_param_model');

		if (empty($params) AND isset($this->params))
		{
			$params = $this->params;
		}

		return ee()->favorites_param_model->insert_params($params);
	}
	//	End insert params


	// --------------------------------------------------------------------

	/**
	 * param - gets stored paramaters
	 *
	 * @access	private
	 * @param	string  $which	which param needed
	 * @param	string  $type	type of param
	 * @return	bool 			$which was empty
	 */

	private function param ( $which = '', $type = 'all' )
	{
		//	----------------------------------------
		//	Params set?
		//	----------------------------------------

		if ( count( $this->params ) == 0 )
		{
			ee()->load->model('favorites_param_model');

			//	----------------------------------------
			//	Empty id?
			//	----------------------------------------

			$params_id = ee()->input->get_post('params_id', TRUE);

			if ( ! $this->is_positive_intlike($params_id) )
			{
				return FALSE;
			}

			$this->params_id = $params_id;

			// -------------------------------------
			//	pre-clean so cache can keep
			// -------------------------------------

			ee()->favorites_param_model->cleanup();

			//	----------------------------------------
			//	Select from DB
			//	----------------------------------------

			$data = ee()->favorites_param_model->select('data')
											  ->get_row($this->params_id);

			//	----------------------------------------
			//	Empty?
			//	----------------------------------------

			if ( ! $data )
			{
				return FALSE;
			}

			//	----------------------------------------
			//	Unserialize
			//	----------------------------------------

			$this->params				= json_decode( $data['data'], TRUE );
			$this->params				= is_array($this->params) ? $this->params : array();
			$this->params['set']		= TRUE;
		}
		//END if ( count( $this->params ) == 0 )


		//	----------------------------------------
		//	Fetch from params array
		//	----------------------------------------

		if ( isset( $this->params[$which] ) )
		{
			$return	= str_replace( "&#47;", "/", $this->params[$which] );

			return $return;
		}

		//	----------------------------------------
		//	Fetch TMPL
		//	----------------------------------------

		if ( isset( ee()->TMPL ) AND
			 is_object(ee()->TMPL) AND
			 ee()->TMPL->fetch_param($which) )
		{
			return ee()->TMPL->fetch_param($which);
		}

		//	----------------------------------------
		//	Return (if which is blank, we are just getting data)
		//	else if we are looking for something that doesn't exist...
		//	----------------------------------------

		return ($which === '');
	}
	//End param


	// --------------------------------------------------------------------

	/**
	 * build_form
	 *
	 * builds a form based on passed data
	 *
	 * @access	private
	 * @
	 * @return 	mixed  	boolean false if not found else id
	 */

	private function build_form ( $data )
	{
		// -------------------------------------
		//	prep input data
		// -------------------------------------

		//set defaults for optional items
		$input_defaults	= array(
			'action' 			=> '/',
			'hidden_fields' 	=> array(),
			'tagdata'			=> ee()->TMPL->tagdata,
		);

		//array2 overwrites any duplicate key from array1
		$data 			= array_merge($input_defaults, $data);

		//OK, so form_open is supposed to be doing this,
		//but guess what: It only works if CI sees that
		//config->item('csrf_protection') === true, and uh
		//sometimes it's false eventhough secure_forms == 'y'
		//
		if ( $this->check_yes(ee()->config->item('secure_forms')) )
		{
			$data['hidden_fields']['XID'] = $this->create_xid();
		}

		// --------------------------------------------
		//  HTTPS URLs?
		// --------------------------------------------

		$data['action'] = $this->prep_url(
			$data['action'],
			(
				isset($this->params['secure_action']) AND
				$this->params['secure_action']
			)
		);


		foreach(array('return', 'RET') as $return_field)
		{
			if (isset($data['hidden_fields'][$return_field]))
			{
				$data['hidden_fields'][$return_field] = $this->prep_url(
					$data['hidden_fields'][$return_field],
					(
						isset($this->params['secure_return']) AND
						$this->params['secure_return']
					)
				);
			}
		}

		// --------------------------------------------
		//  Override Form Attributes with form:xxx="" parameters
		// --------------------------------------------

		$form_attributes = array();

		if (is_object(ee()->TMPL) AND ! empty(ee()->TMPL->tagparams))
		{
			foreach(ee()->TMPL->tagparams as $key => $value)
			{
				if (strncmp($key, 'form:', 5) == 0)
				{
					//allow action override.
					if (substr($key, 5) == 'action')
					{
						$data['action'] = $value;
					}
					else
					{
						$form_attributes[substr($key, 5)] = $value;
					}
				}
			}
		}

		// --------------------------------------------
		//  Create and Return Form
		// --------------------------------------------

		//have to have this for file uploads
		if (isset($this->multipart) && $this->multipart)
		{
			$form_attributes['enctype'] = 'multipart/form-data';
		}

		$form_attributes['method'] = $data['method'];

		$return		= form_open(
			$data['action'],
			$form_attributes,
			$data['hidden_fields']
		);

		$return		.= stripslashes($data['tagdata']);

		$return		.= "</form>";

		return $return;
	}
	//END build_form

	// --------------------------------------------------------------------


	/**
	 * prep_url
	 *
	 * checks a url for {path} or url creation needs with https replacement
	 *
	 * @access	private
	 * @param 	string 	url to be prepped
	 * @param 	bool 	replace http with https?
	 * @return	string 	url prepped with https or not
	 */

	private function prep_url ($url, $https = FALSE)
	{
		$return = trim($url);
		$return = ($return !== '') ? $return : ee()->config->item('site_url');

		if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $return, $match ) > 0 )
		{
			$return	= ee()->functions->create_url( $match['1'] );
		}
		elseif ( ! preg_match('/^http[s]?:\/\//', $return) )
		{
			$return	= ee()->functions->create_url( $return );
		}

		if ($https)
		{
			$return = preg_replace('/^http:\/\//', 'https://', $return);
		}

		return $return;
	}
	//end prep_url

	// --------------------------------------------------------------------


	/**
	 * Saves favorites
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function save_favorites()
	{

		//	----------------------------------------
		//	Get the old XID
		//	----------------------------------------

		if(version_compare(APP_VER, '2.7', '>=') && version_compare(APP_VER, '2.8', '<'))
		{
			ee()->security->restore_xid();
		}

		// -------------------------------------------
		//  Not logged in?  Fail out gracefully.
		// -------------------------------------------

		if(ee()->session->userdata('member_id') == 0)
		{
			return $this->error_page( array(
					lang('no_login')
					)
				);
		}

		if (ee()->input->get_post('params_id') === FALSE)
		{
			return $this->error_page( array(
					lang('missing_post_data') . ' - params_id'
					)
				);
		}

		//	----------------------------------------
		//	Edit mode?
		//	----------------------------------------

		if ($this->param('edit_mode') == 'y')
		{
			$this->edit_mode = TRUE;
		}

		//	----------------------------------------
		//	Type?
		//	----------------------------------------

		if( $this->param('type') )
		{
			$this->type = $this->param('type');
		}

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		$this->default_collections = ee()->favorites_collections->default_collections();
		$db_data['collection_id']  = 0;

		if ($this->param('collection'))
		{
			$db_data['collection_id'] = ee()->favorites_collections->collection_id_from_name($this->param('collection'), $this->type);
			$collection_name = $this->param('collection');
		}
		else if(ee()->input->get_post('collection', TRUE))
		{
			$db_data['collection_id'] = ee()->favorites_collections->collection_id_from_name(ee()->input->get_post('collection', TRUE), $this->type);
			$collection_name = ee()->input->get_post('collection', TRUE);
		}

		// Default collection if a collection_id hasn't been found.

		if($db_data['collection_id'] == 0)
		{
			$db_data['collection_id'] = $this->default_collections[$this->type]['collection_id'];
			$collection_name = $this->default_collections[$this->type]['collection_name'];
		}

		$collection_data['collection_name'] = $collection_name;
		$collection_data['type']            = $this->type;

		//	----------------------------------------
		//	Allow new collections
		//	Overrides the previous collection name if enabled
		//	----------------------------------------

		if ($this->check_yes( $this->param('allow_new_collections') ) && ee()->input->get_post('collection'))
		{
			if( ee()->favorites_collections->is_duplicate($collection_data) === FALSE )
			{
				$db_data['collection_id'] = ee()->favorites_collections->new_collection(ee()->input->get_post('collection'), $this->type);
			}
			else
			{
				return $this->error_page( array(
						lang('collection_name_empty_or_already_exists')
						)
					);
			}
		}

		//	----------------------------------------
		//	Member
		//	----------------------------------------

		if ($this->param('member_id') && is_numeric($this->param('member_id')) && $this->edit_mode === FALSE)
		{
			$db_data['item_id'] = $this->param('member_id');
			$this->type         = 'member';
		}

		//	----------------------------------------
		//	Member, based on username
		//	overrides member_id if present.
		//	----------------------------------------

		if ($this->param('username') && $this->edit_mode === FALSE)
		{
			ee()->load->model('favorites_members');

			$db_data['item_id'] = ee()->favorites_members->get_member_id_from_name($this->param('username'));
			$this->type         = 'member';
		}

		//	----------------------------------------
		//	entry_id. Previous member_id is
		//	removed since entry_id takes precedence
		//	----------------------------------------

		if ($this->param('entry_id') && is_numeric($this->param('entry_id')) && $this->edit_mode === FALSE)
		{
			$this->entry_id     = $this->param('entry_id');
			$this->type         = 'entry_id';
			$db_data['item_id'] = $this->param('entry_id');
		}

		//	----------------------------------------
		//	If a favorite_id is provided, get the
		//	corresponding favorite data from DB
		//	----------------------------------------

		if ($this->param('favorite_id') && is_numeric($this->param('favorite_id')) && $this->edit_mode !== FALSE)
		{
			$fav_data = ee()->favorites_members->get_favorite_data(
				array(
				'favs_ids' => array($this->param('favorite_id'))
				)
			);

			$this->type               = $fav_data[''][0]['favorites:type'];
			$db_data['item_id']       = $fav_data[''][0]['favorites:item_id'];

			if($this->check_yes( ee()->input->get_post('delete') ))
			{
				$db_data['collection_id'] = ! is_null($fav_data[''][0]['favorites:collection_id']) ? $fav_data[''][0]['favorites:collection_id'] : 0;
			}
		}

		$this->item_id = isset($db_data['item_id']) ? $db_data['item_id'] : 0;

		//	----------------------------------------
		//	Notes
		//	----------------------------------------

		if (ee()->input->get_post('notes') !== FALSE)
		{
			$db_data['notes'] = ee()->input->get_post('notes');
		}

		//	----------------------------------------
		//	Get author_id for entry
		//	----------------------------------------
		if($this->type == 'entry_id' && $this->edit_mode === FALSE)
		{
			$sql	= "SELECT 	author_id, entry_id, site_id AS entry_site_id
						   FROM 	{$this->sc->db->channel_titles}
						   WHERE 	entry_id = '" . ee()->db->escape_str( $this->item_id ) . "'
						   LIMIT 	1";

			$query		= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				$this->error_page( array(
					lang('id_not_found')
					)
				);
			}
			else
			{
				$row					= $query->first_row();
				$db_data['author_id']	= $row->author_id;
			}
		}

		$db_data['favoriter_id'] = ee()->session->userdata('member_id');
		$db_data['site_id']      = ee()->config->item('site_id');

		if($this->edit_mode === FALSE)
		{
			$db_data['favorited_date']	= ee()->localize->now;
		}

		//	----------------------------------------
		//	Deletion of favorites
		//	----------------------------------------

		if($this->check_yes( ee()->input->get_post('delete') ))
		{
			$where['item_id']       = ee()->db->escape_str($this->item_id);
			$where['type']          = $this->type;
			$where['favoriter_id']  = $db_data['favoriter_id'];
			$where['collection_id'] = $db_data['collection_id'];

			// ----------------------------------------
			// 'delete_favorite_start' hook.
			//  - Change or add additional processing before saving an favorite
			//	- Added Favorites 3.0.5
			// ----------------------------------------

			if (ee()->extensions->active_hook('delete_favorite_start') === TRUE)
			{
				ee()->extensions->universal_call(
					'delete_favorite_start',
					$where['type'],
					NULL,
					$db_data['favoriter_id'],
					$where['item_id'],
					$db_data['site_id']
				);

				if (ee()->extensions->end_script === TRUE) return;
			}

			ee()->db->delete('favorites', $where);

			// ----------------------------------------
			// 'delete_favorite_end' hook.
			//  - Change or add additional processing before saving an favorite
			//	- Added Favorites 3.0.5
			// ----------------------------------------

			if (ee()->extensions->active_hook('delete_favorite_end') === TRUE)
			{
				ee()->extensions->universal_call(
					'delete_favorite_end',
					$where['type'],
					NULL,
					$db_data['favoriter_id'],
					$where['item_id'],
					$db_data['site_id']
				);

				if (ee()->extensions->end_script === TRUE) return;
			}

			if($this->param('return'))
			{
				ee()->functions->redirect($this->param('return'));
			}

			return;
		}

		$favorite_id = $this->param('favorite_id');

		//	----------------------------------------
		//	Check if favorite hasn't already been... favorited
		//	----------------------------------------

		if($this->favorite_exists() === TRUE && $this->edit_mode === FALSE)
		{
			return $this->error_page(array(lang('no_duplicates')));
		}



		//	----------------------------------------
		//	Check if someone is trying to create a
		//	collection when you're not allowed to
		//	----------------------------------------

		if( ! $this->check_yes($this->param('allow_new_collections')))
		{
			if( ee()->favorites_collections->collection_id_from_name($collection_name, $this->type) == 0 )
			{
				return $this->error_page(array(lang('invalid_collection_name')));
			}
		}

		//	----------------------------------------
		//	If you still don't know the type, you're
		//	likely in edit mode, since no entry_id/member_id
		//	was provided (only a favorites_id). Get the type
		//	from the passed parameter.
		//	----------------------------------------
		if($this->param('type'))
		{
			$this->type = $this->param('type');
		}

		$db_data['type'] = $this->type;

		//	----------------------------------------
		//	Check if someone is trying to update to
		//	a collection that was already favorited to.
		//	1. Find original collection_id
		//	2. Check if new collection_id matches
		//	   collections of any favorites but also
		//	   not original collection_id
		//	----------------------------------------

		if($this->favorite_exists() === TRUE && $this->edit_mode && $favorite_id)
		{
			$current_fav_data = ee()->favorites_members->get_favorite_data($db_data);
			$original_collection = 0;

			foreach($current_fav_data[$this->type] as $data)
			{
				if($data['favorites:favorite_id'] == $favorite_id)
				{
					$original_collection = $data['favorites:collection_id'];
				}
			}

			foreach($current_fav_data[$this->type] as $data)
			{
				if($data['favorites:collection_id'] == $db_data['collection_id'] && $data['favorites:collection_id'] != $original_collection)
				{
					return $this->error_page(array(lang('no_duplicates')));
				}
			}
		}

		// ----------------------------------------
		// 'insert_favorite_start' hook.
		//  - Change or add additional processing before saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('insert_favorite_start') === TRUE)
		{
			$db_data = ee()->extensions->universal_call('insert_favorite_start', $db_data);
			if (ee()->extensions->end_script === TRUE) { return $data; }
		}

		if($this->edit_mode && $favorite_id)
		{
			ee()->db->update('favorites', $db_data, 'favorites_id = "' . ee()->db->escape_str($favorite_id) . '"');
		}
		else
		{
			ee()->db->insert('favorites', $db_data);
		}

		// ----------------------------------------
		// 'insert_favorite_end' hook.
		//  - Change or add additional processing after saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('insert_favorite_end') === TRUE)
		{
			$edata = ee()->extensions->universal_call('insert_favorite_end', $db_data, $favorite_id);
			if (ee()->extensions->end_script === TRUE) { return $edata; }
		}

		//	----------------------------------------
		//	Return redirect
		//	----------------------------------------

		if($this->param('return'))
		{
			ee()->functions->redirect($this->param('return'));
		}

		return;

	}
	// END save_favorites()


	// --------------------------------------------------------------------

	/**
	 * Saving a Collection
	 * @return void Redirect
	 */
	public function save_collection()
	{
		//	----------------------------------------
		//	Get the old XID
		//	----------------------------------------

		if(version_compare(APP_VER, '2.7', '>=') && version_compare(APP_VER, '2.8', '<'))
		{
			ee()->security->restore_xid();
		}

		// -------------------------------------------
		//  Not logged in?  Fail out gracefully.
		// -------------------------------------------

		if(ee()->session->userdata('member_id') == 0)
		{
			return $this->error_page( array(
					lang('no_login')
					)
				);
		}

		if(ee()->input->get_post('params_id') === FALSE)
		{
			return $this->error_page( array(
					lang('missing_post_data') . ' - params_id'
					)
				);
		}

		//	----------------------------------------
		//	Edit mode?
		//	----------------------------------------

		if($this->param('edit_mode') == 'y')
		{
			$this->edit_mode = TRUE;
		}

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if( ee()->input->get_post('collection', TRUE) )
		{
			$db_data['collection_name'] = ee()->input->get_post('collection', TRUE);
		}
		else
		{
			return $this->error_page( array(
					lang('collection_name_empty_or_already_exists')
					)
				);
		}

		if( $this->param('type') )
		{
			$db_data['type'] = $this->param('type');
		}
		else
		{
			$db_data['type'] = 'entry_id';
		}

		$collection_id = $this->param('collection_id');

		if( ee()->favorites_collections->is_duplicate($db_data) === FALSE )
		{
			if($this->edit_mode && $collection_id)
			{
				ee()->db->update('favorites_collections', $db_data, 'collection_id = "' . ee()->db->escape_str($collection_id) . '"');
			}
			else
			{
				ee()->db->insert('favorites_collections', $db_data);
			}
		}
		else
		{
			return $this->error_page( array(
					lang('collection_name_empty_or_already_exists')
					)
				);
		}

		//	----------------------------------------
		//	Return redirect
		//	----------------------------------------

		if($this->param('return'))
		{
			ee()->functions->redirect($this->param('return'));
		}

		return;
	} // END save_collection()


	// --------------------------------------------------------------------

	/**
	 * Save a favorite member
	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function save_member()
	{
		return $this->save( 'member_id' );
	}
	// END save_member()


	// --------------------------------------------------------------------

	/**
	 * Save a Favorite
	 *
	 * @access	public
	 * @param	string type to save
	 * @param	string delete
	 * @return	string message
	 */

	public function save( $type = 'entry_id', $delete = '' )
	{
		if(ee()->session->userdata['group_id'] == 1)
		{
			return 'Error: Obsolete tag.';
		}
		else
		{
			return '';
		}
	}
	// END save()


	// --------------------------------------------------------------------

	/**
	 *	Add a Favorite
	 *  This is a deprecated function
	 *
	 *	@access		public
	 *	@return		bool
	 */

	public function add_favorite_entry()
	{
		return $this->save();
	}
	// END add_favorite_entry()


	// --------------------------------------------------------------------

	/**
	 *	Delete a Favorite for an entry
	 *
	 *	@access		public
	 *	@return		bool
	 */

	public function delete()
	{
		return $this->save( 'entry_id', 'delete' );
	}
	//	End delete()


	// --------------------------------------------------------------------

	/**
	 *	Delete all Favorites for a member
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function delete_all()
	{

		// -------------------------------------------
		//  Not logged in?  Fail out gracefully.
		// -------------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return lang('no_login');
		}

		// -------------------------------------------
		//  Update last activity
		// -------------------------------------------

		$this->_update_last_activity();

		// -------------------------------------------
		//  Determine favorite type to delete
		// -------------------------------------------

		$this->type = ee()->TMPL->fetch_param('type') == 'member' ? 'member' : 'entry_id';

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if(ee()->TMPL->fetch_param('collection'))
		{
			$collection = ee()->TMPL->fetch_param('collection');

			$collection = " AND collection_id = '" . ee()->db->escape_str(ee()->favorites_collections->collection_id_from_name($collection, $this->type)) . "'";
		}
		else
		{
			$collection = '';
		}

		// ----------------------------------------
		// 'delete_all_favorites_start' hook.
		//  - Change or add additional processing before saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('delete_all_favorites_start') === TRUE)
		{
			ee()->extensions->universal_call('delete_all_favorites_start');

			if (ee()->extensions->end_script === TRUE) return;
		}

		// -------------------------------------------
		//  Delete All Favorites for this User for Site
		// -------------------------------------------

		$sql[] = "DELETE FROM 	exp_favorites
				  WHERE 	  	favoriter_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'
				  AND 			type = '" . ee()->db->escape_str($this->type) . "'
				  " . $collection . "
				  AND 			site_id
				  IN 			('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')";

		// -------------------------------------------
		//  Run Queries
		// -------------------------------------------

		foreach ($sql as $sql_query)
		{
			ee()->db->query($sql_query);
		}

		// ----------------------------------------
		// 'delete_all_favorites_end' hook.
		//  - Change or add additional processing before saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('delete_all_favorites_end') === TRUE)
		{
			ee()->extensions->universal_call('delete_all_favorites_end');

			if (ee()->extensions->end_script === TRUE) return;
		}

		// -------------------------------------------
		//  Return success
		// -------------------------------------------

		return lang('success_delete_all');
	}
	// End delete_all()


	// --------------------------------------------------------------------

	/**
	 *	Delete a Favorite for a member
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function delete_member()
	{
		return $this->save( 'member_id', 'delete' );
	}
	//	End delete_member()


	// --------------------------------------------------------------------

	/**
	 *  has this been saved already?
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function member_saved()
	{
		$this->type = 'member';

		return $this->saved( 'member' );
	}
	//END member_saved()


	// --------------------------------------------------------------------

	/**
	 *  Saved() helps you test whether a member
	 *	has saved an entry already
	 *
	 *	@access		public
	 *  @param		string type to check
	 *	@return		string tagdata with conditions for saved parsed
	 */

	public function saved()
	{
		$saved		= FALSE;

		$user_id	= ee()->session->userdata['member_id'];

		if ( $this->_entry_id( $this->type ) === TRUE AND $user_id != '0' )
		{

			$sql	= "/* Favorites saved() for member */ SELECT 	COUNT(*) AS count
					   FROM 	exp_favorites
					   WHERE 	site_id
					   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 		type 		= '" . ee()->db->escape_str( $this->type ) . "'
					   AND 		item_id		= '" . ee()->db->escape_str( $this->item_id ) . "'
					   AND 		favoriter_id 	= '" . ee()->db->escape_str( $user_id ) . "'";

			$query = $this->cacheless_query( $sql );

			if ($query->row('count') > 0)
			{
				$saved	= TRUE;
			}
		}

		$tagdata			= ee()->TMPL->tagdata;

		$cond['saved']		= ( $saved ) 	? TRUE: FALSE;
		$cond['not_saved']	= ( ! $saved ) 	? TRUE: FALSE;

		$tagdata		= ee()->functions->prep_conditionals($tagdata, $cond);

		return $tagdata;
	}
	// End saved()

	// --------------------------------------------------------------------


	/**
	 *  favorite_exists() helps you test whether something is already favorited
	 *
	 *	@access		public
	 *  @param		string type to check
	 *	@return		bool favorited or not
	 */

	public function favorite_exists()
	{
		$user_id = ee()->session->userdata['member_id'];

		$collection    = ee()->input->get_post('collection', TRUE);
		$collection_id = ee()->favorites_collections->collection_id_from_name($collection, $this->type);

		if ( $user_id != '0' )
		{
			$sql	= "/* Favorites favorite_exists() - item_id */ SELECT 	COUNT(*) AS count
					   FROM 	exp_favorites
					   WHERE 	site_id = '" . ee()->db->escape_str(ee()->config->item('site_id')) . "'
					   AND 		type = '" . ee()->db->escape_str($this->type) . "'
					   AND 		favoriter_id 	= '" . ee()->db->escape_str($user_id) . "'
					   AND 		item_id = '" . ee()->db->escape_str($this->item_id) . "'
					   AND 		collection_id = '" . ee()->db->escape_str($collection_id) . "'";

			$query = $this->cacheless_query( $sql );

			if ($query->row('count') > 0)
			{
				$this->saved = TRUE;
				$this->not_saved = FALSE;
				return TRUE;
			}
			else
			{
				$this->saved = FALSE;
				$this->not_saved = TRUE;
				return FALSE;
			}
		}

		return FALSE;

	}
	// End favorite_exists()

	// --------------------------------------------------------------------


	/**
	 *	Output Custom Error Template
	 *
	 *	@access		public
	 *	@param		array	$errors	what errors we are going to show
	 *	@param		string	$type	type of error
	 *	@return		string	$html	error output template parsed
	 */

	private function error_page ($errors, $type = 'submission')
	{

		$error_return = array();

		foreach ($errors as $error_set => $error_data)
		{
			if (is_array($error_data))
			{
				foreach ($error_data as $sub_key => $sub_error)
				{
					$error_return[] = $sub_error;
				}
			}
			else
			{
				$error_return[] = $error_data;
			}
		}

		$errors = $error_return;

		$error_page = (
			$this->param('error_page') ?
				$this->param('error_page') :
				ee()->input->post('error_page', TRUE)
		);

		if ( ! $error_page AND
			REQ == 'PAGE' AND
			isset(ee()->TMPL) AND
			is_object(ee()->TMPL) AND
			ee()->TMPL->fetch_param('error_page') !== FALSE)
		{
			$error_page = str_replace(T_SLASH, '/', ee()->TMPL->fetch_param('error_page'));
		}

		if ( ! $error_page)
		{
			return $this->show_error($errors);
		}

		//	----------------------------------------
		//  Retrieve Template
		//	----------------------------------------

		$x = explode('/', $error_page);

		if ( ! isset($x[1])) $x[1] = 'index';

		//	----------------------------------------
		//  Template as File?
		//	----------------------------------------

		$template_data = '';

		if ($template_data == '')
		{
			$query =	ee()->db->select('template_data, group_name, template_name, template_type')
								->from('exp_templates as t')
								->from('exp_template_groups as tg')
								->where('t.site_id', ee()->config->item('site_id'))
								->where('t.group_id = tg.group_id')
								->where('t.template_name', $x[1])
								->where('tg.group_name', $x[0])
								->limit(1)
								->get();

			if ($query->num_rows() > 0)
			{
				if (ee()->config->item('save_tmpl_files') == 'y' AND
					ee()->config->item('tmpl_file_basepath') != '')
				{
					ee()->load->library('api');
					ee()->api->instantiate('template_structure');

					$row = $query->row_array();

					$template_data = $this->find_template_file(
						$row['group_name'],
						$row['template_name'],
						ee()->api_template_structure->file_extensions(
							$row['template_type']
						)
					);
				}

				//no file? query it is
				if ($template_data == '')
				{
					$template_data = stripslashes($query->row('template_data'));
				}

			}
		}

		// -------------------------------------
		//	query didn't work but save templates
		//	as files is enabled? Lets see if its there
		//	as an html file anyway
		// -------------------------------------

		if ($template_data == '' AND
			ee()->config->item('save_tmpl_files') == 'y' AND
			ee()->config->item('tmpl_file_basepath') != '')
		{
			$template_data = $this->find_template_file($x[0], $x[1]);
		}

		// -------------------------------------
		//	still no template data? buh bye
		// -------------------------------------

		if ($template_data == '')
		{
			return $this->show_error($errors);
		}

		if ($type == 'general')
		{
			$heading = lang('general_error');
		}
		else
		{
			$heading = lang('submission_error');
		}

		//	----------------------------------------
		//  Create List of Errors for Content
		//	----------------------------------------

		$content  = '<ul>';

		if ( ! is_array($errors))
		{
			$content.= "<li>".$errors."</li>\n";
		}
		else
		{
			foreach ($errors as $val)
			{
				$content.= "<li>".$val."</li>\n";
			}
		}

		$content .= "</ul>";

		//	----------------------------------------
		//  Data Array
		//	----------------------------------------

		$data = array(
			'title' 		=> lang('error'),
			'heading'		=> $heading,
			'content'		=> $content,
			'redirect'		=> '',
			'meta_refresh'	=> '',
			'link'			=> array(
				'javascript:history.go(-1)',
				lang('return_to_previous')
			),
			'charset'		=> ee()->config->item('charset')
		);

		if (is_array($data['link']) AND count($data['link']) > 0)
		{
			$refresh_msg = (
				$data['redirect'] != '' AND
				$this->refresh_msg == TRUE
			) ? lang('click_if_no_redirect') : '';

			$ltitle = ($refresh_msg == '') ? $data['link']['1'] : $refresh_msg;

			$url = (
				strtolower($data['link']['0']) == 'javascript:history.go(-1)') ?
					$data['link']['0'] :
					ee()->security->xss_clean($data['link']['0']
			);

			$data['link'] = "<a href='".$url."'>".$ltitle."</a>";
		}

		//	----------------------------------------
		//  For a Page Request, we parse variables and return
		//  to let the Template Parser do the rest of the work
		//	----------------------------------------

		if (REQ == 'PAGE')
		{
			foreach ($data as $key => $val)
			{
				$template_data = str_replace('{'.$key.'}', $val, $template_data);
			}

			return str_replace('/', T_SLASH, $template_data);
		}

		// --------------------------------------------
		//	Parse as Template
		// --------------------------------------------

		$this->actions()->template();

		ee()->TMPL->global_vars	= array_merge(ee()->TMPL->global_vars, $data);
		$out = ee()->TMPL->process_string_as_template($template_data);

		exit($out);
	}
	// END error_page

	// --------------------------------------------------------------------


	/**
	 * Find the template
	 *
	 * @access	protected
	 * @param	string	$group		template group
	 * @param	string	$template	template name
	 * @param	string	$extension	file extension
	 * @return	string				template data or empty string
	 */

	protected function find_template_file ($group, $template, $extention = '.html')
	{
		$template_data = '';

		$extention = '.' . ltrim($extention, '.');

		$filepath = rtrim(ee()->config->item('tmpl_file_basepath'), '/') . '/';
		$filepath .= ee()->config->item('site_short_name') . '/';
		$filepath .= $group . '.group/';
		$filepath .= $template;
		$filepath .= $extention;

		ee()->security->sanitize_filename($filepath);

		if (file_exists($filepath))
		{
			$template_data = file_get_contents($filepath);
		}

		return $template_data;
	}
	//END find_template_file


	// --------------------------------------------------------------------

	/**
	 * Display collections
	 * @return string The parsed template code
	 */
	public function collections()
	{
		if(ee()->TMPL->fetch_param('type'))
		{
			if( ee()->TMPL->fetch_param('type') == 'member')
			{
				$this->type = 'member';
			}
			else
			{
				$this->type = 'entry_id';
			}
		}

		$collections = ee()->favorites_collections->collections($this->type);

		foreach($collections as $id => $collection_data)
		{
			$filtered_collection[$id] = $collection_data['collection_name'];
		}

		if(ee()->TMPL->fetch_param('favorites_member_id'))
		{
			// Reset the collections,
			// we'll make a new list with filtered collection
			// per the selected member_id
			$filtered_collection = array();

			$saved_collections = ee()->favorites_collections->saved_collections($this->type, '', ee()->TMPL->fetch_param('favorites_member_id'));

			foreach($collections as $id => $collection_data)
			{
				if( isset($saved_collections[$id]) )
				{
					$filtered_collection[$id] = $collection_data['collection_name'];
				}
			}
		}

		$variables = array();

		if( ! empty($filtered_collection) )
		{
			foreach($filtered_collection as $id => $name)
			{
				$data['favorites:collection_id']   = $id;
				$data['favorites:collection_name'] = $name;
				$variables[] = $data;
			}
		}
		else
		{
			return $this->no_results();
		}

		$output = ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);

		return $output;

	} // END collections()


	// --------------------------------------------------------------------

	/**
	 *  entries
	 *
	 *	@access		public
	 *	@return		string result of $this->parse_tagdata_entries();
	 */

	public function entries()
	{
		$entry_id	= '';
		$cat_id		= '';

		$dynamic = ! $this->check_no(ee()->TMPL->fetch_param('dynamic'));

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql = "/* Favorites entries() - entry_ids */
				SELECT 		f.*, t.*
				FROM 		exp_favorites f
				LEFT JOIN 	exp_channel_titles t ON t.entry_id = f.item_id";

		if ( ee()->TMPL->fetch_param('category') OR
			 ($cat_id != '' AND $dynamic) )
		{
			$sql	.= " LEFT JOIN 	exp_category_posts
						 ON 		t.entry_id = exp_category_posts.entry_id
						 LEFT JOIN 	exp_categories
						 ON 		exp_category_posts.cat_id = exp_categories.cat_id";
		}

		$sql	.= " WHERE 	f.type = 'entry_id'
					 AND 	t.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

		//	----------------------------------------
		//	Member_id
		//	----------------------------------------

		$this->member_id = ee()->TMPL->fetch_param('favorites_member_id') ? ee()->TMPL->fetch_param('favorites_member_id') : ee()->session->userdata['member_id'];

		if (ee()->TMPL->fetch_param('favorites_username'))
		{
			$this->member_id = ee()->favorites_members->get_member_id_from_name(ee()->TMPL->fetch_param('favorites_username'));
		}

		//	----------------------------------------
		//	Foolproofing the member_id
		//	----------------------------------------

		if ( ! is_numeric($this->member_id))
		{
			$this->member_id = ee()->session->userdata['member_id'];
		}

		$sql .= " AND f.favoriter_id = " . ee()->db->escape_str($this->member_id);

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('collection'))
		{
			$this->collections = ee()->favorites_collections->collections('entry_id');

			$sql_collection = ' AND f.collection_id = 0';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		//	----------------------------------------
		//	Favorite start/stop dates
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('favorites_start_on') !== FALSE OR
			ee()->TMPL->fetch_param('favorites_stop_before') !== FALSE)
		{
			if (ee()->TMPL->fetch_param('favorites_start_on'))
			{
				$sql .= " AND f.favorited_date >= '" .
							$this->string_to_timestamp(
								ee()->TMPL->fetch_param('favorites_start_on')
							) . "' ";
			}

			if (ee()->TMPL->fetch_param('favorites_stop_before'))
			{
				$sql .= " AND f.favorited_date < '" .
							$this->string_to_timestamp(
								ee()->TMPL->fetch_param('favorites_stop_before')
							) . "' ";
			}
		}

		// -------------------------------------------
		//	We only select un-expired entries
		// -------------------------------------------

		$timestamp = (ee()->TMPL->cache_timestamp != '') ?
						ee()->TMPL->cache_timestamp : ee()->localize->now;

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_future_entries') ) )
		{
			$sql .= " AND t.entry_date < ".$timestamp." ";
		}

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_expired') ) )
		{
			$sql .= " AND (t.expiration_date = 0 || t.expiration_date > " . $timestamp . ") ";
		}

		// -------------------------------------------
		// Limit to/exclude specific weblogs
		// -------------------------------------------

		if ($channel = ee()->TMPL->fetch_param($this->sc->channel))
		{
			$xql = "SELECT 	{$this->sc->db->channel_id}
					FROM 	{$this->sc->db->channels}
					WHERE 	site_id
					IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

			$xql .= ee()->functions->sql_andor_string($channel, $this->sc->db->channel_name);

			$query = ee()->db->query($xql);

			if ($query->num_rows() == 0)
			{
				return $this->no_results();
			}
			else
			{
				if ($query->num_rows() == 1)
				{
					$sql .= "AND t.{$this->sc->db->channel_id} = '" . $query->row($this->sc->db->channel_id) . "' ";
				}
				else
				{
					$sql .= "AND (";

					foreach ($query->result_array() as $row)
					{
						$sql .= "t.{$this->sc->db->channel_id} = '" . $row[$this->sc->db->channel_id] . "' OR ";
					}

					$sql = substr($sql, 0, - 3);

					$sql .= ") ";
				}
			}
		}

		// -------------------------------------------
		//  Limit query by category
		// -------------------------------------------

		if (ee()->TMPL->fetch_param('category'))
		{
			$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_categories.cat_id')." ";
		}
		else
		{
			if ($cat_id != '' AND $dynamic)
			{
				$sql .= " AND exp_categories.cat_id = '".ee()->db->escape_str($cat_id)."' ";
			}
		}

		// -------------------------------------------
		//	Add status declaration
		// -------------------------------------------

		if ($status = ee()->TMPL->fetch_param('status'))
		{
			$status = str_replace('Open',   'open',   $status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = ee()->functions->sql_andor_string($status, 't.status');

			if ( ! stristr($sstr, "'closed'") )
			{
				$sstr .= " AND t.status != 'closed' ";
			}

			$sql .= $sstr;
		}
		else
		{
			$sql .= "AND t.status = 'open' ";
		}

		// -------------------------------------------
		//	Limit by number of hours
		// -------------------------------------------

		if ( $days = ee()->TMPL->fetch_param('hours') )
		{
			$time	= ee()->localize->now - ( $days * 60 * 60 );
			$sql	.= " AND t.entry_date > $time";
		}

		$this->parse_tagdata_entries($sql, 'entries');

		return $this->return_data;
	}
	// END entries()


	// --------------------------------------------------------------------

	/**
	 *  _entries
	 *
	 *	@access		public
	 *  @param		array 	params for weblog options
	 *	@return		string 	result of $this->_entries();
	 */

	public function _entries ( $params = array() )
	{
		//	----------------------------------------
		//	Execute?
		//	----------------------------------------

		if ( $this->entry_id == '' )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Invoke Channel/Weblog class
		//	----------------------------------------

		if ( ! class_exists('Channel') )
		{
			require PATH_MOD.'/channel/mod.channel'.EXT;
		}

		$channel = new Channel;

		// --------------------------------------------
		//  Invoke Pagination for EE 2.4 and Above
		// --------------------------------------------

		if ($this->ee_version >= '2.4.0')
		{
			ee()->load->library('pagination');
			$channel->pagination = new Pagination_object('Channel');

			// Used by pagination to determine whether we're coming from the cache
			$channel->pagination->dynamic_sql = FALSE;
		}

		//	----------------------------------------
		//	Pass params
		//	----------------------------------------

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

		// Clear the url_title value, we are using it in a different context
		ee()->TMPL->tagparams['url_title']	= NULL;

		ee()->TMPL->tagparams['inclusive']	= '';

		ee()->TMPL->tagparams['show_pages']	= 'all';

		if ( isset( $params['dynamic'] ) AND $this->check_no($params['dynamic'])  )
		{
			ee()->TMPL->tagparams['dynamic']	= 'no';
		}

		//	----------------------------------------
		//	Pre-process related data
		//	----------------------------------------


		if (ee()->TMPL->fetch_param('favorites_count') != 'yes')
		{
			if (version_compare($this->ee_version, '2.6.0', '<'))
			{
				ee()->TMPL->tagdata	= ee()->TMPL->assign_relationship_data(
					ee()->TMPL->tagdata
				);
			}

			ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );
		}

		//	----------------------------------------
		//	Execute needed methods
		//	----------------------------------------

		$channel->fetch_custom_channel_fields();

		$channel->fetch_custom_member_fields();

		if (ee()->TMPL->fetch_param('favorites_count') != 'yes')
		{
			// --------------------------------------------
			//  Pagination Tags Parsed Out
			// --------------------------------------------

			if ($this->ee_version >= '2.4.0')
			{
				$channel->pagination->get_template();
				$channel->pagination->cfields = $channel->cfields;
			}
			else
			{
				$channel->fetch_pagination_data();
			}

			//	----------------------------------------
			//	Override segment 3 momentarily
			//	----------------------------------------
			//	We need to force some functionality on EE 2.
			//	The CI Pagination class looks at the
			//	3rd URI segment. If it sees an integer there,
			//	it assumes the number is a page
			//	number and builds pagination based on that.
			//	We want that to be ignored.
			//	----------------------------------------

			$segs	= ee()->uri->segments;
			ee()->uri->segments[3]	= '';
		}

		// --------------------------------------------
		//  Pagination Tags Parsed Out
		// --------------------------------------------

		if ($channel->enable['pagination'] == TRUE)
		{
			ee()->TMPL->tagdata = $this->pagination_prefix_replace(
				'favorites:',
				ee()->TMPL->tagdata
			);

			if (APP_VER >= '2.4.0')
			{
				$channel->pagination->get_template();
				$channel->pagination->cfields = $channel->cfields;
				// Done in build_sql_query();
				//$channel->pagination->build();
			}
			else
			{
				$channel->fetch_pagination_data();
				// // Done in build_sql_query();
				$channel->create_pagination();
			}

			ee()->TMPL->tagdata = $this->pagination_prefix_replace(
				'favorites:',
				ee()->TMPL->tagdata,
				TRUE
			);
		}


		//	----------------------------------------
		//	 Build Weblog Data Query
		//	----------------------------------------

		// Since they no longer give us $this->pager_sql in EE 2.4, I will just
		// insure it is stored  and pull it right back out to use again.
		if ($this->ee_version >= '2.4.0')
		{
			ee()->db->save_queries = TRUE;
		}

		$channel->build_sql_query();

		// --------------------------------------------
		//  Transfer Pagination Variables Over to Channel object
		//	- Has to go after the building of the query as EE 2.4 does its Pagination work in there
		// --------------------------------------------

		if ($this->ee_version >= '2.4.0')
		{
			$transfer = array(
				'paginate'		=> 'paginate',
				'total_pages' 	=> 'total_pages',
				'current_page'	=> 'current_page',
				'offset'		=> 'offset',
				'page_next'		=> 'page_next',
				'page_previous'	=> 'page_previous',
				'page_links'	=> 'pagination_links', // different!
				'total_rows'	=> 'total_rows',
				'per_page'		=> 'per_page',
				'per_page'		=> 'p_limit',
				'offset'		=> 'p_page'
			);

			foreach($transfer as $from => $to)
			{
				$channel->$to = $channel->pagination->$from;
			}
		}

		//	----------------------------------------
		//	Return segment 3 now
		//	----------------------------------------
		//	We need to force some functionality on EE 2.
		//	The CI Pagination class looks at the 3rd URI
		//	segment. If it sees an integer there, it assumes
		//	the number is a page number and builds pagination
		//	based on that. We want that to be ignored.
		//	----------------------------------------

		if ( isset( $segs ) === TRUE )
		{
			ee()->uri->segments	= $segs;
		}

		if ($channel->sql == '')
		{
			return $this->no_results();
		}

		//	----------------------------------------
		//	 Favorites Specific Rewrites!
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('favorites_count') == 'yes')
		{
			$query = ee()->db->query(
				preg_replace(
					"/SELECT(.*?)\s+FROM\s+/is",
					'SELECT COUNT(*) AS count FROM ',
					$channel->sql
				)
			);

			return $this->return_data = str_replace(
				LD . 'favorites_count' . RD,
				$query->row('count'),
				ee()->TMPL->tagdata
			);
		}

		if ( stristr(ee()->TMPL->tagdata, LD.'favorites:date ') OR
			ee()->TMPL->fetch_param('orderby') == 'favorited_date')
		{
			$sort = (in_array(strtoupper(ee()->TMPL->fetch_param('sort')), array('DESC', 'ASC'))) ?
						strtoupper(ee()->TMPL->fetch_param('sort')) : 'DESC';

			// --------------------------------------------
			//  EE 2.4 removed $this->pager from the Channel class.
			//	To find it, we do some clever searching.
			// --------------------------------------------

			if ($this->ee_version >= '2.4.0')
			{
				$num = sizeof(ee()->db->queries) - 1;

				while($num > 0)
				{
					$test_sql = ee()->db->queries[$num];

					if ( substr(trim($test_sql), 0, strlen('SELECT t.entry_id FROM')) == 'SELECT t.entry_id FROM')
					{
						$channel->pager_sql = $test_sql;
						break;
					}

					$num--;
				}

				if (ee()->config->item('show_profiler') != 'y' && DEBUG != 1)
				{
					ee()->db->save_queries	= FALSE;
					ee()->db->queries 		= array();
				}
			}

			// --------------------------------------------
			//  Fun Times with Pagination Manipulation
			// --------------------------------------------

			if ( ! empty($channel->pager_sql) && $channel->paginate == TRUE )
			{
				$channel->pager_sql = preg_replace(
					"/\s+FROM\s+/s",
					", f.favorited_date, f.collection AS collection, f.notes AS notes FROM ",
					ltrim($channel->pager_sql)
				);

				$channel->pager_sql = preg_replace(
					"/LEFT JOIN\s+{$this->sc->db->channels}/is",
					"LEFT JOIN 	exp_favorites AS f
					 ON 		t.entry_id = f.item_id
					 LEFT JOIN 	{$this->sc->db->channels}
					 WHERE f.type = 'entry_id'",
					$channel->pager_sql
				);

				if ($this->member_id != '' && is_numeric($this->member_id))
				{
					$channel->pager_sql = preg_replace(
						"/WHERE\st.entry_id\s+/is",
						"WHERE 	f.favoriter_id = '" . ee()->db->escape_str($this->member_id) . "'
						 AND 	t.entry_id ",
						ltrim($channel->pager_sql)
					);
				}

				if (ee()->TMPL->fetch_param('collection'))
				{
					$channel->pager_sql = preg_replace(
						"/WHERE\s\s+/is",
						"WHERE f.collection = '" . ee()->db->escape_str(ee()->TMPL->fetch_param('collection')) . "' AND ",
						 ltrim($channel->sql)
					);
				}

				// In EE 2.4.0 we find the pager_sql in the query log.
				// Previous to that we actually got it from $channel
				// However, it was missing the ORDER clause, so we add it back in
				if ($this->ee_version < '2.4.0')
				{
					if (preg_match("/ORDER BY(.*?)(LIMIT|$)/s", $channel->sql, $matches))
					{
						$channel->pager_sql .= 'ORDER BY'.$matches[1];
					}
				}

				if (ee()->TMPL->fetch_param('orderby') == 'favorited_date')
				{
					if (stristr($channel->pager_sql, 'ORDER BY'))
					{
						$channel->pager_sql = preg_replace("/ORDER BY(.*?)(,|LIMIT|$)/s",
														   'ORDER BY favorited_date '.$sort.',\1\2',
														   $channel->pager_sql);
					}
					else
					{
						$channel->pager_sql .= ' ORDER BY favorited_date '.$sort.' ';
					}
				}

				// In EE 2.4.0 we find the pager_sql in the query log.
				// Previous to that we actually got it from $channel
				// However, it was missing the LIMIT clause, so we add it back in
				if ($this->ee_version < '2.4.0')
				{
					$offset = ( ! ee()->TMPL->fetch_param('offset') OR
								! is_numeric(ee()->TMPL->fetch_param('offset'))) ?
									'0' : ee()->TMPL->fetch_param('offset');

					$channel->pager_sql .= ($channel->p_page == '') ?
						" LIMIT " . $offset . ', ' . $channel->p_limit :
						" LIMIT " . $channel->p_page . ', ' . $channel->p_limit;

				}

				$pquery = ee()->db->query($channel->pager_sql);

				$entries = array();

				// Build ID numbers (checking for duplicates)

				foreach ($pquery->result_array() as $row)
				{
					$entries[] = $row['entry_id'];
				}

				$channel->sql = preg_replace(
					"/t\.entry_id\s+IN\s+\([^\)]+\)/is",
					"t.entry_id IN (".implode(',', $entries).")",
					$channel->sql
				);

				//?
				unset($pquery);
				unset($entries);
			}

			// --------------------------------------------
			//  Rewrite the Weblog Data Query
			// --------------------------------------------

			$channel->favorited_date = TRUE;

			$channel->sql = preg_replace(
				"/\s+FROM\s+/s",
				", f.favorited_date, f.collection AS collection, f.notes AS notes FROM ",
				ltrim($channel->sql)
			);

			$channel->sql = preg_replace(
				"/LEFT JOIN\s+{$this->sc->db->channels}/is",
				"LEFT JOIN 	exp_favorites AS f
				 ON 		t.entry_id = f.item_id
				 LEFT JOIN 	{$this->sc->db->channels}
				 WHERE f.type = 'entry_id'",
				$channel->sql
			);

			if ($this->member_id != '' && is_numeric($this->member_id))
			{
				$channel->sql = preg_replace(
					"/WHERE\st.entry_id\s+/is",
					"WHERE 	f.member_id = '" . ee()->db->escape_str($this->member_id) . "'
					 AND 	t.entry_id ",
					 ltrim($channel->sql)
				);
			}

			if (ee()->TMPL->fetch_param('collection'))
			{
				$channel->sql = preg_replace(
					"/WHERE\s\s+/is",
					"WHERE f.collection = '" . ee()->db->escape_str(ee()->TMPL->fetch_param('collection')) . "' AND ",
					 ltrim($channel->sql)
				);
			}

			if (ee()->TMPL->fetch_param('orderby') == 'favorited_date')
			{
				$channel->sql = preg_replace(
					"/ORDER BY.+?(LIMIT|$)/is",
					"ORDER BY favorited_date " . $sort . ' \1',
					$channel->sql
				);
			}
		}

		$channel->query = ee()->db->query($channel->sql);

		//	----------------------------------------
		//	Are we forcing the order?
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param( 'tag_rank' ) !== FALSE )
		{
			//	----------------------------------------
			//	Reorder
			//	----------------------------------------
			//	The weblog class fetches entries and
			//	sorts them for us, but not according to
			//	our ranking order. So we need to
			//	reorder them.
			//	----------------------------------------

			$new	= array_flip(explode( "|", $this->entry_id ));

			foreach ( $channel->query->result_array() as $key => $row )
			{
				$new[$row['entry_id']] = $row;
			}

			//	----------------------------------------
			//	Redeclare
			//	----------------------------------------
			//	We will reassign the
			//	$channel->query->result with our
			//	reordered array of values. Thank you
			//	PHP for being so fast with array loops.
			//	----------------------------------------

			$channel->query->result_array = array_values($new);

			//	Clear some memory
			unset( $new );
			unset( $entries );
		}

		if ( isset( $channel->query ) === FALSE OR $channel->query->num_rows() == 0)
		{
			return FALSE;
		}

		//	----------------------------------------
		//	typography
		//	----------------------------------------


		ee()->load->library('typography');
		ee()->typography->initialize();
		ee()->typography->convert_curly = FALSE;

		$channel->fetch_categories();

		//	----------------------------------------
		//	Parse and return entry data
		//	----------------------------------------

			//	----------------------------------------
		//	Here's another pagination hack to make sure that total pages parses correctly in the template.
		//	----------------------------------------

		// echo (floor($channel->total_rows / $channel->p_limit));

		$channel->total_pages	= ceil($channel->total_rows / $channel->p_limit);

		$channel->parse_channel_entries();

		// $channel->total_pages	= ( $channel->total_pages == 0 ) ? 1: $channel->total_pages;

		if ($this->ee_version >= '2.4.0')
		{
			$channel->return_data = $channel->pagination->render($channel->return_data);
		}
		else
		{
			$channel->add_pagination_data();
		}

		//	----------------------------------------
		//	Count tag
		//	----------------------------------------

		if (version_compare($this->ee_version, '2.6.0', '<'))
		{
			if (count(ee()->TMPL->related_data) > 0 AND
				count($channel->related_entries) > 0)
			{
				$channel->parse_related_entries();
			}

			if (count(ee()->TMPL->reverse_related_data) > 0 AND
				count($channel->reverse_related_entries) > 0)
			{
				$channel->parse_reverse_related_entries();
			}
		}



		//	----------------------------------------
		//	Handle problem with pagination segments
		//	in the url
		//	----------------------------------------

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}

		$tagdata = $channel->return_data;

		return $tagdata;
	}
	// End _entries()


	// --------------------------------------------------------------------

	/**
	 *  This fetches a list of members who have
	 *	favorited a given member.
	 *
	 *	@access		public
	 *	@return		string 	result of $this->members(), tagdata output
	 */

	public function fans ()
	{
		return $this->members( 'member_id' );
	}
	//	End fans()


	// --------------------------------------------------------------------

	/**
	 * This fetches a list of favorited members
	 * belonging to the logged in member
	 *
	 *	@access		public
	 *	@return		string 	tagdata for ouput
	 */

	public function my_members()
	{
		$groups	= ( is_numeric( ee()->TMPL->fetch_param('groups') ) ) ?
						ee()->TMPL->fetch_param('groups'): '1';

		// -------------------------------------------
		//  Get member id
		// -------------------------------------------

		if ( ee()->session->userdata('member_id') == 0 )
		{
			return $this->no_results();
		}
		else
		{
			$this->member_id = ee()->session->userdata('member_id');
		}

		// -------------------------------------------
		//  Begin SQL
		// -------------------------------------------

		$sql	= "SELECT DISTINCT m.*";

		// -------------------------------------------
		//  Add custom member fields
		// -------------------------------------------

		$this->_fetch_custom_member_fields();

		foreach ( $this->mfields as $key => $value )
		{
			$sql	.= ", md.m_field_id_".$value['0']." AS ".$key;
		}

		$sql	.= " FROM 		exp_members 	AS m
					 LEFT JOIN 	exp_member_data AS md
					 ON 		md.member_id 	= m.member_id
					 LEFT JOIN 	exp_favorites 	AS f
					 ON 		m.member_id 	= f.author_id
					 WHERE 		f.public 		= 'y'
					 AND 		f.type 			= 'member_id'
					 AND  		f.favoriter_id 	= '" . ee()->db->escape_str( $this->member_id ) . "'";

		// -------------------------------------------
		//  Allow narcissism?
		// -------------------------------------------

		if (
			( ee()->TMPL->fetch_param( 'allow_narcissism' ) !== FALSE AND
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcissism' ) ) ) OR
			 ( ee()->TMPL->fetch_param( 'allow_narcisism' ) !== FALSE AND
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcisism' ) ) )
		   )
		{
			$sql	.= " AND f.author_id != f.member_id";
		}

		// -------------------------------------------
		//  Limit by member group
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('group_id') )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('group_id'), 'm.group_id' );
		}

		// -------------------------------------------
		//  Order by
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND ee()->TMPL->fetch_param('orderby') != '' )
		{
			if ( isset( $this->mfields[ee()->TMPL->fetch_param('orderby')] ) )
			{
				$sql	.= " ORDER BY md.m_field_id_" . $this->mfields[ee()->TMPL->fetch_param('orderby')]['0'];
			}
			else
			{
				$sql	.= " ORDER BY m." . ee()->TMPL->fetch_param('orderby');
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		// -------------------------------------------
		//  Sort
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('sort') == 'asc' )
		{
			$sql	.= " ASC";
		}
		else
		{
			$sql	.= " DESC";
		}

		// -------------------------------------------
		//  Limit
		// -------------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('limit') ) )
		{
			$sql	.= " LIMIT " . ee()->TMPL->fetch_param('limit');
		}

		// -------------------------------------------
		//  Run query
		// -------------------------------------------

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		// ----------------------------------------
		//  Parse count
		// ----------------------------------------

		ee()->TMPL->tagdata	= ee()->TMPL->swap_var_single( 'favorites_count', $query->num_rows(), ee()->TMPL->tagdata );

		// ----------------------------------------
		//  Parse list
		// ----------------------------------------

		return $this->_members( $query, $groups );
	}
	//	End my_members()


	// --------------------------------------------------------------------

	/**
	 * This fetches a list of members who have
	 * favorited a given entry.
	 *
	 *	@access		public
	 *	@return		string 	result of $this->members, tagdata
	 */

	public function subscribers()
	{
		return $this->members( 'entry_id' );
	}
	//	End subscribers()


	// --------------------------------------------------------------------

	/**
	 * This fetches a list of members who have
	 * favorited a given entry or member.
	 *
	 *	@access		public
	 *  @param 		string 	type to look up
	 *	@return		string 	Parsed tagdata
	 */

	public function saved_by( $type = 'entry_id' )
	{
		ee()->load->model('favorites_members');

		$this->member_fields = ee()->favorites_members->get_member_fields();

		// -------------------------------------------
		//  Get entry id
		// -------------------------------------------

		if ( $this->_entry_id( $this->type ) === FALSE )
		{
			return $this->no_results();
		}

		// -------------------------------------------
		//  Begin SQL
		// -------------------------------------------

		$sql	= "/* Favorites ".__FUNCTION__."() */\n SELECT SQL_CALC_FOUND_ROWS DISTINCT m.*, md.*, f.*";

		// -------------------------------------------
		//  Add custom member fields
		// -------------------------------------------

		foreach ( $this->member_fields as $id => $name )
		{
			if(is_numeric($id))
			{
				$sql	.= ", md.m_field_id_" . ee()->db->escape_str($id) . " AS " . ee()->db->escape_str($name);
			}
			else
			{
				$sql	.= ", m." . ee()->db->escape_str($id) . " AS " . ee()->db->escape_str($name);
			}
		}

		$sql	.= " FROM 		exp_members 	AS m
					 LEFT JOIN 	exp_member_data AS md
					 ON 		md.member_id 	= m.member_id
					 LEFT JOIN 	exp_favorites 	AS f
					 ON 		m.member_id 	= f.favoriter_id
					 WHERE 		f.site_id
					 IN 		('" . implode("','", ee()->TMPL->site_ids) . "')";

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		$this->collections = ee()->favorites_collections->collections($this->types);

		if (ee()->TMPL->fetch_param('collection'))
		{
			$sql_collection = ' AND f.collection_id = 0';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		//	----------------------------------------
		//	Member_id
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('member_id') || ee()->TMPL->fetch_param('username'))
		{
			$this->type = 'member';

			$this->item_id = ee()->TMPL->fetch_param('member_id') ? ee()->TMPL->fetch_param('member_id') : ee()->session->userdata['member_id'];

			if (ee()->TMPL->fetch_param('username'))
			{
				$this->item_id = ee()->favorites_members->get_member_id_from_name(ee()->TMPL->fetch_param('username'));
			}

			//	----------------------------------------
			//	Foolproofing the member_id
			//	----------------------------------------

			if ( ! is_numeric($this->item_id))
			{
				$this->item_id = ee()->session->userdata['member_id'];
			}
		}

		//	----------------------------------------
		//	Switch on type
		//	----------------------------------------

		if ( $this->type == 'member' )
		{
			$sql	.= " AND f.type 		= 'member'
							 AND f.item_id 	= '" . ee()->db->escape_str( $this->item_id ) . "' ";
		}
		else
		{
			$sql	.= " AND f.type 	= 'entry_id'
						 AND f.item_id = '" . ee()->db->escape_str( $this->entry_id ) . "' ";
		}

		// -------------------------------------------
		//  Allow narcissism?
		// -------------------------------------------

		if (
			 ( ee()->TMPL->fetch_param( 'allow_narcissism' ) !== FALSE AND
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcissism' ) ) ) OR
			 ( ee()->TMPL->fetch_param( 'allow_narcisism' ) !== FALSE AND
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcisism' ) ) )
		   )
		{
			$sql	.= " AND f.item_id != f.favoriter_id";
		}

		//	----------------------------------------
		//	Group ID
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('group_id'))
		{
			$group_id_string = ee()->TMPL->fetch_param('group_id');

			if( strncmp($group_id_string, 'not ', 4) == 0 )
			{
				$in = 'NOT IN';
				$group_id_string = str_replace('not ', '', $group_id_string);
			}
			else
			{
				$in = 'IN';
			}

			$group_id_array = array_filter( explode('|', trim($group_id_string)) );

			$group_id_string = implode(', ', $group_id_array);

			$sql .= " AND m.group_id " . $in . " (" . ee()->db->escape_str($group_id_string) . ")";
		}

		$sql .= " GROUP BY m.member_id";

		$sql .= $this->process_orderby();

		$this->parse_tagdata($sql);

		return $this->return_data;

	}
	//	End saved_by()


	// --------------------------------------------------------------------

	/**
	 * function members()
	 * Lists members favorited by a user.
	 *
	 *	@access		public
	 *	@return		string 	tagdata
	 */
	public function members()
	{

		ee()->load->model('favorites_members');

		$this->member_fields = ee()->favorites_members->get_member_fields();

		$member_id = ee()->session->userdata['member_id'];

		if (ee()->TMPL->fetch_param('favorites_username'))
		{
			$member_id = ee()->favorites_members->get_member_id_from_name(ee()->TMPL->fetch_param('favorites_username'));
		}

		if (ee()->TMPL->fetch_param('favorites_member_id'))
		{
			$member_id = ee()->TMPL->fetch_param('favorites_member_id');
		}

		//	----------------------------------------
		//	Foolproofing the member_id
		//	----------------------------------------

		if ( ! is_numeric($member_id))
		{
			$member_id = ee()->session->userdata['member_id'];
		}

		$sql	= "/* Favorites ".__FUNCTION__."() */
					SELECT SQL_CALC_FOUND_ROWS f.*, f.item_id as member_id, f.favorited_date, m.screen_name AS total_favorites, m.*, md.*
					FROM exp_favorites AS f
					LEFT JOIN 	exp_members 	AS m
					ON 			m.member_id 	= f.item_id
					LEFT JOIN 	exp_member_data AS md
					ON 			md.member_id 	= f.item_id
					WHERE 		f.site_id IN ('" . implode("','", ee()->TMPL->site_ids) . "')
					AND f.type = 'member'
					AND f.favoriter_id = '". ee()->db->escape_str($member_id)."' ";

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('collection'))
		{
			$this->collections = ee()->favorites_collections->collections('member');

			$sql_collection = ' AND f.collection_id = 0';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		// ----------------------------------------------------
		//  Limit query by date range given in tag parameters
		// ----------------------------------------------------

		if (ee()->TMPL->fetch_param('favorites_start_on'))
		{
			$sql .= "AND f.favorited_date >= '" .
					$this->string_to_timestamp(
						ee()->TMPL->fetch_param('favorites_start_on')
					) .
					"' ";
		}

		if (ee()->TMPL->fetch_param('favorites_stop_before'))
		{
			$sql .= "AND f.favorited_date < '" .
					$this->string_to_timestamp(
						ee()->TMPL->fetch_param('favorites_stop_before')
					) .
					"' ";
		}

		$sql .= $this->process_orderby();

		$this->parse_tagdata($sql);

		return $this->return_data;

	} // END members()


	// --------------------------------------------------------------------

	/**
	 * This parses a member list.
	 *
	 *	@access		public
	 *  @param 		string 	query to check
	 * 	@param		string  groups
	 *	@return		string 	tagdata
	 */

	public function _members( $query, $groups = '1' )
	{
		// ----------------------------------------
		//  Set dates
		// ----------------------------------------

		$dates	= array(
			'join_date',
			'last_bulletin_date',
			'last_visit',
			'last_activity',
			'last_entry_date',
			'last_rating_date',
			'last_comment_date',
			'last_forum_post_date',
			'last_email_date'
		);

		// -------------------------------------------
		//  Parse when we have groups
		// -------------------------------------------

		if ( preg_match( "/" . LD . 'group' . RD . "(.+?)" . LD . preg_quote(T_SLASH, '/') .
						 "group" . RD . "/s", ee()->TMPL->tagdata, $match ) )
		{
			$chunk	= $match['1'];

			// -------------------------------------------
			//  Convert to array and chunk
			// -------------------------------------------

			$members	= $query->result;

			$members	= array_chunk( $members, ceil( count( $members ) / $groups ) );

			// -------------------------------------------
			//  Parse
			// -------------------------------------------

			$return	= '';

			foreach ( $members as $group )
			{
				$tagdata	= ee()->TMPL->tagdata;
				$r			= '';

				foreach ( $group as $row )
				{
					$c			= $chunk;

					// -------------------------------------------
					//  Conditionals
					// -------------------------------------------

					$cond	= $row;
					$c		= ee()->functions->prep_conditionals( $c, $cond );

					// ----------------------------------------
					//  Parse dates
					// ----------------------------------------

					foreach ($dates as $value)
					{
						if (preg_match("/" . LD . $value . "\s+format=[\"'](.*?)[\"']" . RD . "/s", $c, $m))
						{
							$str	= $m['1'];

							$codes	= $this->fetch_date_params( $m['1'] );

							foreach ( $codes as $code )
							{
								$str	= str_replace(
									$code,
									$this->convert_timestamp( $code, $row[$value], TRUE ),
									$str
								);
							}

							$c	= str_replace( $m['0'], $str, $c );
						}
					}

					// -------------------------------------------
					//  Single vars
					// -------------------------------------------

					foreach ( ee()->TMPL->var_single as $key => $value )
					{
						if ( isset( $row[$key] ) )
						{
							$c	= ee()->TMPL->swap_var_single( $key, $row[$key], $c );
						}
					}

					$r	.= $c;
				}

				$tagdata	= str_replace( $match['0'], $r, $tagdata );

				$return		.= $tagdata;
			}
		}
		else
		{
			$return	= '';

			foreach ( $query->result_array() as $row )
			{
				$tagdata	= ee()->TMPL->tagdata;

				// ----------------------------------------
				//  Parse dates
				// ----------------------------------------

				foreach ($dates as $value)
				{
					if (preg_match("/" . LD . $value . "\s+format=[\"'](.*?)[\"']" . RD . "/s", $tagdata, $match))
					{
						$str	= $match['1'];

						$codes	= $this->fetch_date_params( $match['1'] );

						foreach ( $codes as $code )
						{
							$str	= str_replace(
								$code,
								$this->convert_timestamp( $code, $row[$value], TRUE ),
								$str
							);
						}

						$tagdata	= str_replace( $match['0'], $str, $tagdata );
					}
				}

				// ----------------------------------------
				//  Parse conditionals
				// ----------------------------------------

				$cond		= $row;
				$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

				// ----------------------------------------
				//  Parse singles
				// ----------------------------------------

				foreach ( ee()->TMPL->var_single as $key => $value )
				{
					if ( isset( $row[$key] ) )
					{
						$tagdata	= ee()->TMPL->swap_var_single( $key, $row[$key], $tagdata );
					}

				}

				$return	.= $tagdata;
			}
		}

		return $return;
	}
	//	End _members()


	// --------------------------------------------------------------------

	/**
	 * Member rank
	 *
	 *	@access		public
	 *	@return		string 	tagdata. result of $this->members
	 */

	public function member_rank()
	{
		return $this->members( 'member_id', 'yes' );
	}
	//	End member_rank()


	// --------------------------------------------------------------------

	/**
	 * rank()
	 * An alias to rank_entries. For legacy/nostalgia.
	 * @return string Tagdata
	 */
	public function rank()
	{
		return $this->rank_entries();
	}
	// END rank()


	// --------------------------------------------------------------------

	/**
	 *	Rank entries
	 * 	This function ranks entries. Yup.
	 * 	(i.e. on how many times it was favorited)
	 *
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function rank_entries()
	{
		$entry_id	= '';
		$cat_id		= '';

		$dynamic = ! $this->check_no(ee()->TMPL->fetch_param('dynamic'));

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql = "/* Favorites rank_entries() - entry_ids */
				SELECT 		f.*, count(f.item_id) AS total_favorites, t.*
				FROM 		exp_favorites f
				LEFT JOIN 	exp_channel_titles t ON t.entry_id = f.item_id";

		if ( ee()->TMPL->fetch_param('category') OR
			 ($cat_id != '' AND $dynamic) )
		{
			$sql	.= " LEFT JOIN 	exp_category_posts
						 ON 		t.entry_id = exp_category_posts.entry_id
						 LEFT JOIN 	exp_categories
						 ON 		exp_category_posts.cat_id = exp_categories.cat_id";
		}

		$sql	.= " WHERE 	f.type = 'entry_id'
					 AND t.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('collection'))
		{
			$this->collections = ee()->favorites_collections->collections('entry_id');

			$sql_collection = ' AND f.collection_id = 0';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		//	----------------------------------------
		//	Favorite start/stop dates
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('favorites_start_on') !== FALSE OR
			ee()->TMPL->fetch_param('favorites_stop_before') !== FALSE)
		{
			if (ee()->TMPL->fetch_param('favorites_start_on'))
			{
				$sql .= " AND f.favorited_date >= '" .
							$this->string_to_timestamp(
								ee()->TMPL->fetch_param('favorites_start_on')
							) . "' ";
			}

			if (ee()->TMPL->fetch_param('favorites_stop_before'))
			{
				$sql .= " AND f.favorited_date < '" .
							$this->string_to_timestamp(
								ee()->TMPL->fetch_param('favorites_stop_before')
							) . "' ";
			}
		}

		// -------------------------------------------
		//	We only select un-expired entries
		// -------------------------------------------

		$timestamp = (ee()->TMPL->cache_timestamp != '') ?
						ee()->TMPL->cache_timestamp : ee()->localize->now;

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_future_entries') ) )
		{
			$sql .= " AND t.entry_date < ".$timestamp." ";
		}

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_expired') ) )
		{
			$sql .= " AND (t.expiration_date = 0 || t.expiration_date > " . $timestamp . ") ";
		}

		// -------------------------------------------
		// Limit to/exclude specific weblogs
		// -------------------------------------------

		if ($channel = ee()->TMPL->fetch_param($this->sc->channel))
		{
			$xql = "SELECT 	{$this->sc->db->channel_id}
					FROM 	{$this->sc->db->channels}
					WHERE 	site_id
					IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

			$xql .= ee()->functions->sql_andor_string($channel, $this->sc->db->channel_name);

			$query = ee()->db->query($xql);

			if ($query->num_rows() == 0)
			{
				return $this->no_results();
			}
			else
			{
				if ($query->num_rows() == 1)
				{
					$sql .= "AND t.{$this->sc->db->channel_id} = '" . $query->row($this->sc->db->channel_id) . "' ";
				}
				else
				{
					$sql .= "AND (";

					foreach ($query->result_array() as $row)
					{
						$sql .= "t.{$this->sc->db->channel_id} = '" . $row[$this->sc->db->channel_id] . "' OR ";
					}

					$sql = substr($sql, 0, - 3);

					$sql .= ") ";
				}
			}
		}

		// -------------------------------------------
		//  Limit query by category
		// -------------------------------------------

		if (ee()->TMPL->fetch_param('category'))
		{
			$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_categories.cat_id')." ";
		}
		else
		{
			if ($cat_id != '' AND $dynamic)
			{
				$sql .= " AND exp_categories.cat_id = '".ee()->db->escape_str($cat_id)."' ";
			}
		}

		// -------------------------------------------
		//	Add status declaration
		// -------------------------------------------

		if ($status = ee()->TMPL->fetch_param('status'))
		{
			$status = str_replace('Open',   'open',   $status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = ee()->functions->sql_andor_string($status, 't.status');

			if ( ! stristr($sstr, "'closed'") )
			{
				$sstr .= " AND t.status != 'closed' ";
			}

			$sql .= $sstr;
		}
		else
		{
			$sql .= "AND t.status = 'open' ";
		}

		// -------------------------------------------
		//	Limit by number of hours
		// -------------------------------------------

		if ( $days = ee()->TMPL->fetch_param('hours') )
		{
			$time	= ee()->localize->now - ( $days * 60 * 60 );
			$sql	.= " AND t.entry_date > $time";
		}

		$sql .= " GROUP BY f.item_id";

		$this->parse_tagdata_entries($sql, 'rank_entries');

		return $this->return_data;
	}
	//	End rank_entries()


	// --------------------------------------------------------------------

	/**
	 *	Rank members
	 * 	This function ranks members. Yup.
	 * 	(i.e. on how many times the member was favorited)
	 *
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function rank_members()
	{
		ee()->load->model('favorites_members');

		$this->member_fields = ee()->favorites_members->get_member_fields();

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql = "/* Favorites rank_members() */
				SELECT		SQL_CALC_FOUND_ROWS f.*, count(f.item_id) AS total_favorites, m.*, md.*
				FROM		exp_favorites f
				LEFT JOIN	exp_members m ON m.member_id = f.item_id
				LEFT JOIN	exp_member_data md ON md.member_id = f.item_id
				WHERE		f.site_id IN ('".implode("','", ee()->TMPL->site_ids)."')
				AND 		f.type = 'member'";

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		$this->collections = ee()->favorites_collections->collections('member');

		if (ee()->TMPL->fetch_param('collection'))
		{
			$sql_collection = ' AND f.collection_id = 0';

			foreach($this->collections as $collection_id => $collection)
			{
				if($collection['collection_name'] == ee()->TMPL->fetch_param('collection'))
				{
					$sql_collection = " AND f.collection_id = '" . ee()->db->escape_str($collection_id) . "'";
				}
			}

			$sql .= $sql_collection;
		}

		//	----------------------------------------
		//	Group ID
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('group_id'))
		{
			$group_id_string = ee()->TMPL->fetch_param('group_id');

			if( strncmp($group_id_string, 'not ', 4) == 0 )
			{
				$in = 'NOT IN';
				$group_id_string = str_replace('not ', '', $group_id_string);
			}
			else
			{
				$in = 'IN';
			}

			$group_id_array = array_filter( explode('|', trim($group_id_string)) );

			$group_id_string = implode(', ', $group_id_array);

			$sql .= " AND m.group_id " . $in . " (" . ee()->db->escape_str($group_id_string) . ")";
		}

		// ----------------------------------------------------
		//  Limit query by date range given in tag parameters
		// ----------------------------------------------------

		if (ee()->TMPL->fetch_param('favorites_start_on'))
		{
			$sql .= "AND f.favorited_date >= '" .
					$this->string_to_timestamp(
						ee()->TMPL->fetch_param('favorites_start_on')
					) .
					"' ";
		}

		if (ee()->TMPL->fetch_param('favorites_stop_before'))
		{
			$sql .= "AND f.favorited_date < '" .
					$this->string_to_timestamp(
						ee()->TMPL->fetch_param('favorites_stop_before')
					) .
					"' ";
		}

		//	----------------------------------------
		//	Grouping and Ordering
		//	----------------------------------------

		$sql .= " GROUP BY f.item_id ";

		$sql .= $this->process_orderby();

		$this->parse_tagdata($sql);

		return $this->return_data;

	}
	// END rank_members


	// --------------------------------------------------------------------

	/**
	 *	Related entries
	 *
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function related_entries()
	{

		$entry_id = ee()->TMPL->fetch_param('favorites_entry_id') ? trim(ee()->TMPL->fetch_param('favorites_entry_id')) : 0;

		if( ! is_numeric($entry_id))
		{
			return $this->_no_results('favorites');
		}

		$dynamic = ! $this->check_no(ee()->TMPL->fetch_param('dynamic'));

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql = "/* Favorites ".__FUNCTION__."() */
				SELECT 		SQL_CALC_FOUND_ROWS COUNT(f.item_id) AS total_favorites, f.*, t.*
				FROM 		exp_favorites f
				LEFT JOIN 	exp_channel_titles t ON t.entry_id = f.item_id";

		$cat_id		= '';

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('category') OR
			 ($cat_id != '' AND $dynamic) )
		{
			$sql	.= " LEFT JOIN 	exp_category_posts
						 ON 		t.entry_id = exp_category_posts.entry_id
						 LEFT JOIN 	exp_categories
						 ON 		exp_category_posts.cat_id = exp_categories.cat_id";
		}

		$sql	.= " WHERE f.type = 'entry_id'
					 AND t.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('collection'))
		{
			$this->collections = ee()->favorites_collections->collections('entry_id');

			$sql_collection = ' AND f.collection_id = 0';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		// -------------------------------------------
		//	We only select un-expired entries
		// -------------------------------------------

		$timestamp = (ee()->TMPL->cache_timestamp != '') ?
						ee()->TMPL->cache_timestamp : ee()->localize->now;

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_future_entries') ) )
		{
			$sql .= " AND t.entry_date < ".$timestamp." ";
		}

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_expired') ) )
		{
			$sql .= " AND (t.expiration_date = 0 || t.expiration_date > " . $timestamp . ") ";
		}

		// -------------------------------------------
		// Limit to/exclude specific weblogs
		// -------------------------------------------

		if ($channel = ee()->TMPL->fetch_param($this->sc->channel))
		{
			$xql = "SELECT 	{$this->sc->db->channel_id}
					FROM 	{$this->sc->db->channels}
					WHERE 	site_id
					IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

			$xql .= ee()->functions->sql_andor_string($channel, $this->sc->db->channel_name);

			$query = ee()->db->query($xql);

			if ($query->num_rows() == 0)
			{
				return $this->_no_results('favorites');
			}
			else
			{
				if ($query->num_rows() == 1)
				{
					$sql .= "AND t.{$this->sc->db->channel_id} = '" . $query->row($this->sc->db->channel_id) . "' ";
				}
				else
				{
					$sql .= "AND (";

					foreach ($query->result_array() as $row)
					{
						$sql .= "t.{$this->sc->db->channel_id} = '" . $row[$this->sc->db->channel_id] . "' OR ";
					}

					$sql = substr($sql, 0, - 3);

					$sql .= ") ";
				}
			}
		}

		// -------------------------------------------
		//  Limit query by category
		// -------------------------------------------

		if (ee()->TMPL->fetch_param('category'))
		{
			$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_categories.cat_id')." ";
		}
		else
		{
			if ($cat_id != '' AND $dynamic)
			{
				$sql .= " AND exp_categories.cat_id = '".ee()->db->escape_str($cat_id)."' ";
			}
		}

		// -------------------------------------------
		//	Add status declaration
		// -------------------------------------------

		if ($status = ee()->TMPL->fetch_param('status'))
		{
			$status = str_replace('Open',   'open',   $status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = ee()->functions->sql_andor_string($status, 't.status');

			if ( ! stristr($sstr, "'closed'") )
			{
				$sstr .= " AND t.status != 'closed' ";
			}

			$sql .= $sstr;
		}
		else
		{
			$sql .= "AND t.status = 'open' ";
		}

		// -------------------------------------------
		//	Limit by number of hours
		// -------------------------------------------

		if ( $days = ee()->TMPL->fetch_param('hours') )
		{
			$time	= ee()->localize->now - ( $days * 60 * 60 );
			$sql	.= " AND t.entry_date > $time";
		}

		$sql .= "AND f.favoriter_id IN (SELECT favoriter_id FROM exp_favorites WHERE item_id = ".ee()->db->escape_str($entry_id).")
				AND f.type = 'entry_id'
				AND f.item_id != ".ee()->db->escape_str($entry_id)."
				GROUP BY f.item_id ";

		$this->parse_tagdata_entries($sql, 'related_entries');

		return $this->return_data;

	}
	// END related_entries


	// --------------------------------------------------------------------

	/**
	 *	Related members
	 *
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function related_members()
	{

		$this->member_id = ee()->TMPL->fetch_param('member_id') ? trim(ee()->TMPL->fetch_param('member_id')) : ee()->session->userdata['member_id'];

		if( ! is_numeric($this->member_id))
		{
			return $this->_no_results('favorites');
		}

		ee()->load->model('favorites_members');

		$this->member_fields = ee()->favorites_members->get_member_fields();

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql = "/* Favorites ".__FUNCTION__."() */
				SELECT 		SQL_CALC_FOUND_ROWS COUNT(f.item_id) AS total_favorites, f.*, m.*, md.*
				FROM 		exp_favorites f
				LEFT JOIN	exp_members m ON m.member_id = f.item_id
				LEFT JOIN	exp_member_data md ON md.member_id = f.item_id
				WHERE		f.site_id IN ('".implode("','", ee()->TMPL->site_ids)."')
				AND 		f.type = 'member'";

		//	----------------------------------------
		//	Collection
		//	----------------------------------------

		$this->collections = ee()->favorites_collections->collections('member');

		if (ee()->TMPL->fetch_param('collection'))
		{
			$sql_collection = ' AND f.collection_id = 0 ';

			$collection_param_values = explode('|', ee()->TMPL->fetch_param('collection'));

			foreach($this->collections as $collection_id => $collection)
			{
				if(in_array($collection['collection_name'], $collection_param_values))
				{
					$collection_where_in[] = $collection['collection_id'];
				}
			}

			if(isset($collection_where_in))
			{
				$sql_collection = " AND f.collection_id IN (" . implode(',', ee()->db->escape_str($collection_where_in)) . ") ";
			}

			$sql .= $sql_collection;
		}

		//	----------------------------------------
		//	Group ID
		//	----------------------------------------

		if (ee()->TMPL->fetch_param('group_id'))
		{
			$group_id_string = ee()->TMPL->fetch_param('group_id');

			if( strncmp($group_id_string, 'not ', 4) == 0 )
			{
				$in = 'NOT IN';
				$group_id_string = str_replace('not ', '', $group_id_string);
			}
			else
			{
				$in = 'IN';
			}

			$group_id_array = array_filter( explode('|', $group_id_string) );

			$group_id_string = implode(', ', $group_id_array);

			$sql .= " AND m.group_id " . $in . " (" . ee()->db->escape_str($group_id_string) . ")";
		}

		//	----------------------------------------
		//	Grouping and Ordering
		//	----------------------------------------

		$sql .= "AND f.favoriter_id IN (SELECT favoriter_id FROM exp_favorites WHERE item_id = ".ee()->db->escape_str($this->member_id).")
				AND f.type = 'member'
				AND f.item_id != ".ee()->db->escape_str($this->member_id)."
				GROUP BY f.item_id ";

		$sql .= $this->process_orderby();

		$this->parse_tagdata($sql);

		return $this->return_data;

	}
	// END related_members

	// --------------------------------------------------------------------


	/**
	 * parse_tagdata
	 * Does some of the tagdata/pagination parsing and busywork
	 * Stay DRY, people.
	 * @param  string $sql The build query up to now
	 * @return string The parsed tagdata
	 */

	private function parse_tagdata($sql)
	{
		$tagdata = ee()->TMPL->tagdata;

		//	----------------------------------------
		//	User-set offset
		//	----------------------------------------

		$offset	= ee()->TMPL->fetch_param('offset') ? ee()->TMPL->fetch_param('offset') : 0;

		//	----------------------------------------
		//	Pagination offset
		//	----------------------------------------

		$pag_offset = 0;

		preg_match("#(/?P\d+)#", ee()->uri->uri_string, $matches);

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $matches) && ! $this->check_yes(ee()->TMPL->fetch_param('disable_pagination')) )
		{
			$pag_offset = substr( $matches['1'], 2 );
		}

		//	----------------------------------------
		//	Combine the offsets
		//	----------------------------------------

		$total_offset = $offset + $pag_offset;


		$limit	= ee()->TMPL->fetch_param('limit') ? ee()->TMPL->fetch_param('limit') : 100;

		$limit_sql = " LIMIT " . ee()->db->escape_str($total_offset) . ", " . ee()->db->escape_str($limit);

		//	----------------------------------------
		//	If unfavorited members are to be included, they will be
		//	after the $sql query. Therefore drop the LIMIT, so that
		//	unfavorited members can be added at the end of the list
		//	of *all* favorited members.
		//	----------------------------------------
		if ( $this->check_yes( ee()->TMPL->fetch_param('show_unfavorited') ) && $this->type = 'member' )
		{
			$limit_sql = '';
		}

		$sql .= $limit_sql;

		//	----------------------------------------
		//	Run it
		//	----------------------------------------

		$results = ee()->db->query($sql);

		$result_array = $results->result_array();

		//	----------------------------------------
		//	Get totals
		//	----------------------------------------

		$tota_results = 0;

		$total_query = ee()->db->query("/* Favorites ".__FUNCTION__."() total results */ \n SELECT FOUND_ROWS() as total_rows");

		foreach($total_query->result_array() as $row)
		{
			$total_results = $row['total_rows'];
		}

		//	----------------------------------------
		//	Get unfavorited members
		//	----------------------------------------

		if ( $this->check_yes( ee()->TMPL->fetch_param('show_unfavorited') ) && $this->type = 'member' )
		{
			if($results->num_rows() > 0)
			{
				foreach($results->result_array() as $row)
				{
					$favorited_member_ids[] = $row['member_id'];
				}
			}

			if( ! isset($favorited_member_ids) )
			{
				$favorited_member_ids[] = 0;
			}

			$sql = "SELECT SQL_CALC_FOUND_ROWS m.*, md.* FROM exp_members m, exp_member_data md
					WHERE m.member_id = md.member_id
					AND m.member_id NOT IN (" . implode(',', $favorited_member_ids) . ")";

			$sql .= $this->process_orderby(TRUE);

			$results = ee()->db->query($sql);

			$result_array_unfavorited = array();

			if($results->num_rows() > 0)
			{
				$result_array_unfavorited = $results->result_array();
			}

			//	----------------------------------------
			//	Include favorited members?
			//	----------------------------------------

			if( ! ee()->TMPL->fetch_param('show_favorites') || $this->check_yes( ee()->TMPL->fetch_param('show_favorites') ))
			{
				$result_array = array_merge($result_array, $result_array_unfavorited);

				//	----------------------------------------
				//	The array now has all favorited, followed
				//	by unfavorited members. Trim that down to
				//	the determined limit and offset
				//	----------------------------------------
				$result_array = array_slice($result_array, $total_offset, $limit);
			}
			else
			{
				$result_array = $result_array_unfavorited;
			}

			$total_query_unfavorited = ee()->db->query("/* Favorites ".__FUNCTION__."() total results */ \n SELECT FOUND_ROWS() as total_rows");

			foreach($total_query_unfavorited->result_array() as $row)
			{
				$total_unfavorited = $row['total_rows'];
			}

			$total_results = $total_results + $total_unfavorited;
		}

		//--------------------------------------
		//  pagination start vars
		//--------------------------------------

		$row_count			= 0;
		$current_page		= 0;

		// -------------------------------------
		//	pagination?
		// -------------------------------------

		$pagination_prefix = stristr($tagdata, LD . 'favorites:paginate' . RD);

		//get pagination info
		$pagination_data = $this->universal_pagination(array(
			'total_results'			=> $total_results,
			'tagdata'				=> $tagdata,
			'limit'					=> $limit,
			'offset' 				=> $offset,
			'uri_string'			=> ee()->uri->uri_string,
			'prefix'				=> 'favorites:',
			'auto_paginate'			=> TRUE
		));

		//if we paginated, sort the data
		if ($pagination_data['paginate'] === TRUE)
		{
			$tagdata		= $pagination_data['tagdata'];
			$current_page 	= $pagination_data['pagination_page'];
		}

		$current_page = $current_page + $offset;

		$count = 0;
		$r = '';

		if($total_results > 0)
		{
			foreach($result_array as $count => $row)
			{
				$data['count']						= $count + 1;
				$data['absolute_count']				= $pag_offset + $count + 1;
				$data['total_results']				= count($result_array);
				$data['absolute_results']			= $total_results;
				$data['favorites:count']			= $count + 1;
				$data['favorites:absolute_count']	= $pag_offset + $count + 1;
				$data['favorites:rank']				= $pag_offset + $count + 1;
				$data['favorites:total_results']	= count($result_array);
				$data['favorites:absolute_results']	= $total_results;
				$data['favorites:total_favorites']	= isset($row['total_favorites']) ? $row['total_favorites'] : 0;
				$data['favorites:relevance']		= isset($row['total_favorites']) ? $row['total_favorites'] : 0;

				foreach($row as $key => $val)
				{
					$data[$key] = $val;
					$data['favorites:'.$key] = $val;
				}

				//	----------------------------------------
				//	Avatar_url
				//	----------------------------------------

				$data['favorites:avatar_url'] = ee()->config->item('avatar_url').$data['avatar_filename'];
				$data['favorites:avatar'] = ! empty($row['avatar_filename']) && ! is_null($row['avatar_filename']) ? TRUE : FALSE;

				//	----------------------------------------
				//	Favorited date
				//	----------------------------------------

				$dates							= array();
				$dates[] = array(
					'favorites:date'		=> isset($row['favorited_date']) ? $row['favorited_date'] : '',
					);

				//	----------------------------------------
				//	Get member field variables
				//	----------------------------------------

				if( ! empty($this->member_fields))
				{
					foreach($this->member_fields as $m_id => $m_name)
					{
						if( is_numeric($m_id) )
						{
							$data['favorites:'.$m_name] = $row['m_field_id_'.$m_id];
						}
						else
						{
							$data['favorites:'.$m_name] = $row[$m_name];
						}
					}
				}

				//	----------------------------------------
				//	Parse conditionals then single vars
				//	----------------------------------------

				$tagdata_temp	= ee()->functions->prep_conditionals( $tagdata, $data );
				$tagdata_temp	= ee()->TMPL->parse_variables( $tagdata_temp, $dates );

				foreach ( $data as $key => $val )
				{
					$tagdata_temp	= ee()->TMPL->swap_var_single( $key, $val, $tagdata_temp );
				}

				$r .= $tagdata_temp;
			}

			// -------------------------------------
			//	add pagination
			// -------------------------------------

			//prefix or no prefix?
			if (isset($pagination_prefix))
			{
				$r = $this->parse_pagination(array(
					'prefix' 	=> 'favorites:',
					'tagdata' 	=> $r
				));
			}
			else
			{
				$r = $this->parse_pagination(array(
					'tagdata' 	=> $r
				));
			}

			$this->return_data = $r;

		}
		else
		{
			$this->return_data = $this->_no_results('favorites');
		}

	}
	// END parse_tagdata();


	// --------------------------------------------------------------------

	/**
	 * parse_tagdata_entries
	 * Does some of the tagdata/pagination parsing and busywork, for
	 * channel entry-related tags. Stay DRY, people.
	 * @param  string $sql The build query up to now
	 * @param  string $method The method for which the processing is run
	 * @return string The parsed tagdata
	 */

	private function parse_tagdata_entries($sql, $method = 'entries')
	{
		$tagdata = ee()->TMPL->tagdata;

		// -------------------------------------------
		//	Order by
		// -------------------------------------------

		$sort = (in_array(strtoupper(ee()->TMPL->fetch_param('sort')), array('DESC', 'ASC'))) ?
						strtoupper(ee()->TMPL->fetch_param('sort')) : 'DESC';

		if (ee()->TMPL->fetch_param('orderby') == 'random')
		{
			$sql	.= " ORDER BY rand()";
		}
		elseif (in_array(ee()->TMPL->fetch_param('orderby'), array('total_favorites', 'matches', 'relevance')))
		{
			$sql	.= " ORDER BY total_favorites " . $sort;
		}
		elseif (ee()->TMPL->fetch_param('orderby') == 'favorited_date')
		{
			$sql 	.= " ORDER BY favorited_date " . $sort;
		}

		// ----------------------------------------
		//  Pagination!
		// ----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('limit') ) )
		{
			$this->p_limit = ee()->TMPL->fetch_param('limit');
		}

		$orderby = ( ee()->TMPL->fetch_param('orderby') != '' ) ? ee()->TMPL->fetch_param('orderby') : 'count';

		// -------------------------------------------
		//	Run query
		// -------------------------------------------

		$query = ee()->db->query($sql);

		// -------------------------------------------
		//	Create entries array
		// -------------------------------------------

		$entries = array();

		if ( $query->num_rows() == 0 )
		{
			$this->return_data = $this->_no_results('favorites');
			return;
		}
		else
		{
			//	----------------------------------------
			//	Saved for later. Total favorite results
			//	----------------------------------------

			$this->total_rows = $query->num_rows();

			//	----------------------------------------
			//	Build entry_id, total_favorites and absolute_count arrays.
			//	The total_favorites and absolute_count arrays are cached and
			//	used in the extension for parsing by the Channel Module.
			//	----------------------------------------

			$c = 1; // One, ah-ah-ah...

			foreach ( $query->result_array() as $row )
			{
				$entries[]							= $row['entry_id'];
				$total_favorites[$row['entry_id']]	= isset($row['total_favorites']) ? $row['total_favorites'] : '';
				$absolute_count[$row['entry_id']]	= $c;
				$c++;
			}

			ee()->session->set_cache('favorites', 'absolute_results', $this->total_rows);
			ee()->session->set_cache('favorites', 'total_favorites', $total_favorites);
			ee()->session->set_cache('favorites', 'absolute_count', $absolute_count);


			// -------------------------------------------
			//  Pass params to be used in Channel module
			// -------------------------------------------

			$entry_ids = implode( "|", $entries );
			$reverse_entry_ids = implode( "|", array_reverse($entries) );

			ee()->TMPL->tagparams['entry_id']		= $entry_ids;

			if ( in_array($orderby, array('favorited_date', 'total_favorites', 'matches', 'relevance')) )
			{

				if ( $sort == 'ASC' )
				{
					$entries = array_reverse( $entries );
				}

				ee()->TMPL->tagparams['fixed_order']	= $entry_ids;
			}

			ee()->TMPL->tagparams['sort'] = $sort;

			// Add a high limit to catch all entry_ids from exp_favorites
			ee()->TMPL->tagparams['limit'] = 9999999;
		}

		// -------------------------------------------
		//  Invoke weblog class
		// -------------------------------------------

		if ( ! class_exists('Channel') )
		{
			require PATH_MOD.'/channel/mod.channel'.EXT;
		}

		$channel = new Channel;

		// --------------------------------------------
		//  Invoke Pagination for EE 2.4 and Above
		// --------------------------------------------

		$channel = $this->add_pag_to_channel($channel);

		// ----------------------------------------
		//  Pre-process related data
		// ----------------------------------------

		if (version_compare($this->ee_version, '2.6.0', '<'))
		{
			ee()->TMPL->tagdata	= ee()->TMPL->assign_relationship_data( ee()->TMPL->tagdata );
		}

		ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );

		// ----------------------------------------
		//  Execute needed methods
		// ----------------------------------------

		$channel->fetch_custom_channel_fields();

		$channel->fetch_custom_member_fields();

		// --------------------------------------------
		//  Pagination Tags Parsed Out
		// --------------------------------------------

		$channel = $this->fetch_pagination_data($channel);

		//	----------------------------------------
		//	 Build Weblog Data Query
		//	----------------------------------------

		$channel->build_sql_query();

		// --------------------------------------------
		//  Transfer Pagination Variables Over to Channel object
		//	- Has to go after the building of the query as EE 2.4 does its Pagination work in there
		// --------------------------------------------

		if ($this->ee_version >= '2.4.0')
		{
			$transfer = array(
				'paginate'		=> 'paginate',
				'total_pages' 	=> 'total_pages',
				'current_page'	=> 'current_page',
				'offset'		=> 'offset',
				'page_next'		=> 'page_next',
				'page_previous'	=> 'page_previous',
				'page_links'	=> 'pagination_links', // different!
				'total_rows'	=> 'total_rows',
				'per_page'		=> 'per_page',
				'per_page'		=> 'p_limit',
				'offset'		=> 'p_page'
			);

			foreach($transfer as $from => $to)
			{
				$channel->$to = $channel->pagination->$from;
			}
		}

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if( trim($channel->sql) == '' )
		{
			return $this->no_results();
		}

		// ----------------------------------------
		//  Pagination
		// ----------------------------------------

		$query = ee()->db->query(
			preg_replace(
				"/SELECT(.*?)\s+FROM\s+/is",
				'SELECT COUNT(*) AS count FROM ',
				$channel->sql
			)
		);

		//$this->total_rows = $query->row('count');

		//pagination request but no entries?
		if ( $query->row('count') == 0 AND
			 strpos( ee()->TMPL->tagdata, 'paginate' ) !== FALSE )
		{
			return $this->no_results();
		}

		$sql_remove = 'SELECT t.entry_id ';

		//	----------------------------------------
		//	Get pagination info to get a formatted SQL query
		//	----------------------------------------

		$pagination_data = $this->universal_pagination(array(
			'sql'					=> $channel->sql,
			'total_results'			=> $this->total_rows,
			'tagdata'				=> ee()->TMPL->tagdata,
			'limit'					=> $this->p_limit,
			'uri_string'			=> ee()->uri->uri_string,
			'current_page'			=> $this->current_page,
			'prefix'				=> 'favorites:',
		));

		// If we're paginated, grab the query
		if ($pagination_data['paginate'] === TRUE)
		{
			$channel->sql			= str_replace($sql_remove, '', $pagination_data['sql']);
		}
		// ...or else we just put a limit on the original query
		else
		{
			$channel->sql .= " LIMIT " . $this->p_limit;
		}

		//	----------------------------------------
		//	 Favorites Specific Rewrites!
		//	----------------------------------------

		//	----------------------------------------
		//	 Favorites date, collection, and notes
		//	----------------------------------------

		$channel->favorited_date = TRUE;

		$channel->sql = preg_replace(
			"/\s+FROM\s+/s",
			", f.favorites_id, f.favorited_date, f.collection_id, f.notes FROM ",
			ltrim($channel->sql)
		);

		if($method == "entries")
		{
			$channel->sql = preg_replace(
				"/LEFT JOIN\s+{$this->sc->db->channels}/is",
				"LEFT JOIN 	exp_favorites AS f
				 ON 		t.entry_id = f.item_id
				 LEFT JOIN 	{$this->sc->db->channels}",
				$channel->sql
			);
		}
		else
		{
			$channel->sql = preg_replace(
				"/LEFT JOIN\s+{$this->sc->db->channels}/is",
				"LEFT JOIN 	exp_favorites AS f
				 ON 		(t.entry_id = f.item_id
				 AND 		f.favorites_id
				 IN 		(SELECT MAX(favorites_id) FROM exp_favorites GROUP BY item_id))
				 LEFT JOIN 	{$this->sc->db->channels}",
				$channel->sql
			);
		}

		if ( $this->check_yes( ee()->TMPL->fetch_param('show_unfavorited') ) && $this->check_yes( ee()->TMPL->fetch_param('show_favorites') ) )
		{
			//	----------------------------------------
			//	Include all entries, favorited or not
			//	----------------------------------------

			$channel->sql = preg_replace(
				"/\s+WHERE t.entry_id IN \((.*?)\)\s+/s",
				" WHERE f.type = 'entry_id' AND (t.entry_id IN ($1) OR t.entry_id NOT IN ($1)) ",
				ltrim($channel->sql)
			);

			//	----------------------------------------
			//	This orders favorited before or
			//	after non-favorited based on $sort.
			//	Crazy MySQL FIELD ordering magic
			//	----------------------------------------

			$orderby_field_order = $sort == "ASC" ? $entry_ids : $reverse_entry_ids;

			$orderby_field_order = str_replace('|', ',', $orderby_field_order);

			$channel->sql = preg_replace(
				"/\s+ORDER BY FIELD\((.*?)\)\s+/s",
				" ORDER BY FIELD(t.entry_id, " . $orderby_field_order . ") " . $sort . ", t.entry_id ",
				ltrim($channel->sql)
			);
		}
		elseif ( $this->check_yes( ee()->TMPL->fetch_param('show_unfavorited') ) && ! $this->check_yes( ee()->TMPL->fetch_param('show_favorites') ) )
		{
			//	----------------------------------------
			//	Just show non-favorited entries
			//	----------------------------------------

			$channel->sql = preg_replace(
				"/\s+WHERE t.entry_id IN \((.*?)\)\s+/s",
				" WHERE t.entry_id NOT IN ($1) ",
				ltrim($channel->sql)
			);
		}
		elseif ( $this->check_no( ee()->TMPL->fetch_param('show_unfavorited') ) && $this->check_no( ee()->TMPL->fetch_param('show_favorites') ) )
		{
			//	----------------------------------------
			//	Really? You just want to show nothing then.
			//	----------------------------------------

			return $this->_no_results();
		}

		//	----------------------------------------
		//	Add favorite_member_id filtering for
		//	entries() method
		//	----------------------------------------
		if($method == 'entries')
		{
			$channel->sql = preg_replace(
				"/\s+WHERE\s+/s",
				" WHERE f.favoriter_id = " . ee()->db->escape_str($this->member_id) . " AND ",
				ltrim($channel->sql)
			);
		}

		$channel->query = ee()->db->query($channel->sql);

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( ! isset( $channel->query ) OR
			 $channel->query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		//	----------------------------------------
		//	typography
		//	----------------------------------------

		ee()->load->library('typography');
		ee()->typography->initialize();
		ee()->typography->convert_curly = FALSE;

		$channel->fetch_categories();

		// ----------------------------------------
		//  Parse and return entry data
		// ----------------------------------------

		$channel->parse_channel_entries();

		// --------------------------------------------
		//  Render the Pagination Data
		// --------------------------------------------

		if ($this->ee_version >= '2.4.0')
		{
			$channel->return_data = $channel->pagination->render($channel->return_data);
		}
		else
		{
			$channel->add_pagination_data();
		}

		// --------------------------------------------
		//  Reverse and Related Entries
		// --------------------------------------------

		if (version_compare($this->ee_version, '2.6.0', '<'))
		{
			if (count(ee()->TMPL->related_data) > 0 AND
				count($channel->related_entries) > 0)
			{
				$channel->parse_related_entries();
			}

			if (count(ee()->TMPL->reverse_related_data) > 0 AND
				count($channel->reverse_related_entries) > 0)
			{
				$channel->parse_reverse_related_entries();
			}
		}

		// ----------------------------------------
		//  Handle problem with pagination segments
		//	in the url
		// ----------------------------------------

		if ( preg_match("#(/P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}
		elseif ( preg_match("#(P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}

		//	----------------------------------------
		//	Pagination
		//	----------------------------------------

		//	$channel->return_data can get gunked up with
		//	repeated pagination code. Use universal_pagination() to get
		//	the "pagination code-less" tagdata and assign it to $channel->return_data

		$pagination_prefix = stristr($tagdata, LD . 'favorites:paginate' . RD);

		$pagination_data = $this->universal_pagination(array(
			'total_results'			=> $this->total_rows,
			'tagdata'				=> $channel->return_data,
			'limit'					=> $this->p_limit,
			//'offset' 				=> $offset,
			'current_page'			=> $this->current_page,
			'uri_string'			=> ee()->uri->uri_string,
			'prefix'				=> 'favorites:',
			'auto_paginate'			=> TRUE
		));

		if ($pagination_data['paginate'] === TRUE)
		{
			$channel->return_data = $pagination_data['tagdata'];
		}

		// Let parse_pagination add pagination where it should be
		// i.e. around $channel->return_data

		if ($pagination_prefix)
		{
			$channel->return_data = $this->parse_pagination(array(
					'prefix' 	=> 'favorites:',
					'tagdata' 	=> $channel->return_data,
				));
		}

		$this->return_data = $channel->return_data;
	}
	// END parse_tagdata_entries()


	// --------------------------------------------------------------------

	/**
	 * process_orderby
	 *
	 * Sets up a multiple sort order SQL string
	 *
	 * @param $skip array Skips conditionals calling for non-member columns
	 * @return string The ORDER BY statement
	 */

	private function process_orderby($skip = FALSE)
	{
		$sort_array = ee()->TMPL->fetch_param('sort') ? array_filter( explode('|', ee()->TMPL->fetch_param('sort')) ) : array('DESC');

		$orderby_array = ee()->TMPL->fetch_param('orderby') ? array_filter( explode('|', ee()->TMPL->fetch_param('orderby')) ) : array('total_favorites');

		$orderby_sql = '';

		foreach($orderby_array as $key => $orderby)
		{
			$sort = isset($sort_array[$key]) ? strtoupper($sort_array[$key]) . ", " : 'DESC, ';

			switch($orderby)
			{
				case 'random':
					$orderby_sql	.= "rand()";
				break;
				case in_array($orderby, array('total_favorites', 'matches', 'relevance')) && $skip !== TRUE:
					$orderby_sql	.= "total_favorites " . $sort;
				break;
				case $orderby == 'favorited_date' && $skip !== TRUE:
					$orderby_sql 	.= "favorited_date " . $sort;
				break;
				case 'group_id':
					$orderby_sql 	.= "m.group_id " . $sort;
				break;
				default:
					if( ! empty($this->member_fields) && in_array($orderby, $this->member_fields) && $this->type == 'member' )
					{
						$m_field_id = array_search($orderby, $this->member_fields);

						if( ! empty($m_field_id) && is_numeric($m_field_id) )
						{
							$orderby_sql	.= "md.m_field_id_" . $m_field_id . " " . $sort;
						}
						elseif( ! empty($m_field_id))
						{
							$orderby_sql	.= "m." . $m_field_id . " " . $sort;
						}
					}
					else
					{
						$orderby_sql 		.= "m.screen_name " . $sort;
					}
				break;
			}
		}

		if( ! empty($orderby_sql) )
		{
			return " ORDER BY " . rtrim($orderby_sql, ', ');
		}

	}
	// END process_orderby


	// --------------------------------------------------------------------

	/**
	 * shared
	 *
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function shared()
	{
		$member_id	= array();
		$qstring	= '';
		$cat_id		= '';
		$year		= '';

		// -------------------------------------------
		//  Entry Id
		// -------------------------------------------

		if ( $this->_entry_id() === FALSE )
		{
			return $this->no_results();
		}

		// -------------------------------------------
		//  Grab members
		// -------------------------------------------

		$sql	= "SELECT 		f.favoriter_id
				   FROM 		exp_favorites AS f
				   LEFT JOIN 	{$this->sc->db->channel_titles} AS t
				   ON 			t.entry_id = f.item_id
				   WHERE 		f.type = 'entry_id'
				   AND 			f.site_id IN ('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
				   AND 			t.entry_id = '" . ee()->db->escape_str($this->entry_id) . "'
				   AND 			t.title IS NOT NULL";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		foreach ( $query->result_array() as $row )
		{
			$member_id[] = $row['favoriter_id'];
		}

		// -------------------------------------------
		//  Grab member entries
		// -------------------------------------------

		$sql		= "SELECT 		f.item_id, COUNT(f.item_id) AS count
					   FROM 		exp_favorites AS f
					   LEFT JOIN 	{$this->sc->db->channel_titles} AS t
					   ON 			t.entry_id = f.item_id
					   WHERE 		f.type = 'entry_id'
					   AND 			f.site_id IN ('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 			f.public = 'y'
					   AND 			t.title IS NOT NULL ";

		if ( ee()->session->userdata['member_id'] != '0' )
		{
			$sql	.= " AND f.member_id != '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'";
		}

		if (count($member_id) > 0)
		{
			$sql	.= " AND f.member_id IN ('" . implode("','", ee()->db->escape_str($member_id)) . "')";
		}

		$sql		.= " AND t.entry_id != '" . ee()->db->escape_str($this->entry_id) . "'";

		$sql		.= " GROUP BY item_id ORDER BY count DESC";

		$query		= ee()->db->query( $sql );

		$this->entry_id	= '';

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		foreach ( $query->result_array() as $row )
		{
			$this->entry_id	.= $row['entry_id'].'|';
		}

		ee()->TMPL->tagparams['dynamic'] = 'off';

		//	----------------------------------------
		//	Parse and return
		//	----------------------------------------

		return $this->_entries();
	}
	//	End shared()


	// --------------------------------------------------------------------

	/**
	 *  Show favorite entries (deprecated)
	 *
	 *	@access		public
	 *	@return		string 	tagdata, result of $this->entries();
	 */

	public function show_favorite_entries()
	{
		return $this->entries();
	}
	// End show_favorite_entries()


	// --------------------------------------------------------------------

	/**
	 *  Show Other People's Favorites (Deprecated)
	 *
	 *	@access		public
	 *	@return		string 	tagdata, result of $this->entries();
	 */

	public function show_others_favorites()
	{
		return $this->entries();
	}
	// End show_others_favorites()


	// --------------------------------------------------------------------

	/**
	 *  _entry_id
	 *
	 *	@access		public
	 *  @param		string	type
	 *	@return		bool 	id type found and set to $this->$type
	 */

	public function _entry_id( $type = 'entry_id' )
	{
		if ( $this->$type != '' )
		{
			return TRUE;
		}

		$cat_segment	= ee()->config->item("reserved_category_word");

		// --------------------------------------
		//  Set Via Parameter
		// --------------------------------------

		if ( $this->_numeric( trim( ee()->TMPL->fetch_param( $type ) ) ) === TRUE )
		{
			$this->$type	= trim( ee()->TMPL->fetch_param( $type ) );

			return TRUE;
		}


		// --------------------------------------
		//  Set Via the url_title parameter
		// --------------------------------------

		if( ee()->TMPL->fetch_param( 'url_title' ) != '' )
		{
			$sql	= "SELECT 	{$this->sc->db->channel_titles}.entry_id
					   FROM   	{$this->sc->db->channel_titles}, {$this->sc->db->channels}
					   WHERE  	{$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = " .
									"{$this->sc->db->channels}.{$this->sc->db->channel_id}
					   AND    	{$this->sc->db->channel_titles}.url_title = '" . ee()->db->escape_str(ee()->TMPL->fetch_param('url_title') ) . "'
					   AND	  	{$this->sc->db->channels}.site_id
					   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

			if (ee()->TMPL->fetch_param($this->sc->channel) !== FALSE)
			{
				$sql .= ee()->functions->sql_andor_string(
					ee()->TMPL->fetch_param($this->sc->channel),
					$this->sc->db->channel_name,
					$this->sc->db->channels
				);
			}

			$query	= ee()->db->query($sql);

			if ( $query->num_rows() > 0 )
			{
				$this->entry_id = $query->row('entry_id');

				return TRUE;
			}
		}

		// --------------------------------------
		//  Found in the URI
		// --------------------------------------

		$qstring	= ( ee()->uri->page_query_string != '' ) ?
						ee()->uri->page_query_string : ee()->uri->query_string;
		$dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		// -------------------------------------------
		//  Do we have a pure ID number?
		// -------------------------------------------

		if ( $this->_numeric( $qstring ) === TRUE )
		{
			$this->$type	= $qstring;

			return TRUE;
		}
		elseif ($dynamic === TRUE)
		{
			// --------------------------------------
			//  Remove day
			// --------------------------------------

			if (preg_match("#\d{4}/\d{2}/(\d{2})#", $qstring, $match))
			{
				$partial = substr($match['0'], 0, -3);

				$qstring = trim_slashes(str_replace($match['0'], $partial, $qstring));
			}

			// --------------------------------------
			//  Remove /year/month/
			// --------------------------------------

			// added (^|\/) to make sure this doesn't trigger with url titles like big_party_2006
			if (preg_match("#(^|\/)(\d{4}/\d{2})#", $qstring, $match))
			{
				$qstring = trim_slashes(str_replace($match['2'], '', $qstring));
			}

			// --------------------------------------
			//  Remove ID indicator
			// --------------------------------------

			if (preg_match("#^(\d+)(.*)#", $qstring, $match))
			{
				$seg = ( ! isset($match['2'])) ? '' : $match['2'];

				if (substr($seg, 0, 1) == "/" OR $seg == '')
				{
					$this->entry_id = $match['1'];

					return TRUE;
				}
			}

			// --------------------------------------
			//  Remove page number
			// --------------------------------------

			if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match) AND $dynamic)
			{
				$qstring = trim_slashes(str_replace($match['0'], '', $qstring));
			}

			// --------------------------------------
			//  Parse category indicator
			// --------------------------------------

			// Text version of the category

			if ( $qstring != '' AND
				 $this->reserved_cat_segment != '' AND
				 in_array($this->reserved_cat_segment, explode("/", $qstring)) AND
				 ee()->TMPL->fetch_param($this->sc->channel))
			{
				$qstring = preg_replace("/(.*?)" . preg_quote($this->reserved_cat_segment) . "\//i", '', $qstring);
			}

			// Numeric version of the category

			if (preg_match("#(^|\/)C(\d+)#", $qstring, $match))
			{
				$qstring = trim_slashes(str_replace($match['0'], '', $qstring));
			}

			// --------------------------------------
			//  Remove "N"
			// --------------------------------------

			// The recent comments feature uses "N" as the URL indicator
			// It needs to be removed if present

			if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
			{
				$qstring = trim_slashes(str_replace($match['0'], '', $qstring));
			}

			// ----------------------------------------
			//  Remove 'delete' and 'private'
			// ----------------------------------------

			$qstring	= trim_slashes( str_replace( array('delete', 'private'), array( '','' ), $qstring) );

			// ----------------------------------------
			//  Try numeric id again
			// ----------------------------------------

			if ( preg_match( "/^(\d+)$/", $qstring, $match ) )
			{
				$this->$type = $match['1'];

				return TRUE;
			}

			// ----------------------------------------
			//  Parse URL title or username
			// ----------------------------------------

			if ( $type == 'member_id' )
			{
				// ----------------------------------------
				//  Parse username
				// ----------------------------------------

				if (strstr($qstring, '/'))
				{
					$xe			= explode('/', $qstring);
					$qstring	= current($xe);
				}

				$sql	= "SELECT 	member_id
						   FROM 	exp_members
						   WHERE 	username = '" . ee()->db->escape_str($qstring) . "'";

				$query	= ee()->db->query($sql);

				if ( $query->num_rows() > 0 )
				{
					$this->member_id = $query->row('member_id');

					return TRUE;
				}
			}
			else
			{
				// ----------------------------------------
				//  Parse URL title
				// ----------------------------------------

				if (strstr($qstring, '/'))
				{
					$xe			= explode('/', $qstring);
					$qstring	= current($xe);
				}

				$sql	= "SELECT 	{$this->sc->db->channel_titles}.entry_id
						   FROM   	{$this->sc->db->channel_titles}, {$this->sc->db->channels}
						   WHERE  	{$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = " .
										"{$this->sc->db->channels}.{$this->sc->db->channel_id}
						   AND    	{$this->sc->db->channel_titles}.url_title = '" . ee()->db->escape_str($qstring) . "'
						   AND	  	{$this->sc->db->channels}.site_id
						   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

				if (ee()->TMPL->fetch_param($this->sc->channel) !== FALSE)
				{
					$sql .= ee()->functions->sql_andor_string(
						ee()->TMPL->fetch_param($this->sc->channel),
						$this->sc->db->channel_name,
						$this->sc->db->channels
					);
				}

				$query	= ee()->db->query($sql);

				if ( $query->num_rows() > 0 )
				{
					$this->entry_id = $query->row('entry_id');
					$this->item_id = $query->row('entry_id');

					return TRUE;
				}
			}
		}

		return FALSE;
	}
	//	End _entry_id()


	//-----------------------------------------------------------------------------------------------------------

	// --------------------------------------------------------------------

	/**
	 *  _numeric
	 *
	 *	@access		public
	 *  @param		string	string to check for number
	 *	@return		bool 	is numeric
	 */

	public function _numeric ( $str = '' )
	{
		if ( $str == '' OR preg_match( '/[^0-9]/', $str ) != 0 )
		{
			return FALSE;
		}

		return TRUE;
	}
	// END _numeric()


	// --------------------------------------------------------------------

	/**
	 *  Fetch custom member field IDs
	 *
	 *	@access		public
	 *	@return		null
	 */

	public function _fetch_custom_member_fields()
	{
		$query = ee()->db->query(
			"SELECT m_field_id, m_field_name, m_field_fmt
			 FROM exp_member_fields"
		);

		if ( $query->num_rows() > 0 )
		{
			foreach ($query->result_array() as $row)
			{
				$this->mfields[$row['m_field_name']] = array(
					$row['m_field_id'],
					$row['m_field_fmt']
				);
			}
		}
	}

	//	End  _fetch_custom_member_fields()


	// --------------------------------------------------------------------

	/**
	 *  Update last activity
	 *
	 *	@access		public
	 *	@return		bool	has been updated
	 */

	public function _update_last_activity()
	{
		if ( ee()->session->userdata('member_id') == 0 )
		{
			return FALSE;
		}

		return ee()->db->update(
			'exp_members',
			array( 'last_activity' 	=> ee()->localize->now ),
			array( 'member_id' 		=> ee()->session->userdata('member_id') )
		);
	}
	//	End _update_last_activity()


	// --------------------------------------------------------------------

	/**
	 * String to timestamp with legacy support
	 *
	 * @access	public
	 * @param	string	$human_string	string to convert to timestamp
	 * @param	boolean	$localized		localize timestamp? (EE 2.6+ only)
	 * @return	string					unix timestamp
	 */

	public function string_to_timestamp($human_string, $localized = TRUE)
	{
		if (trim($human_string) == '')
		{
			return '';
		}

		//EE 2.6+
		if (is_callable(array(ee()->localize, 'string_to_timestamp')))
		{
			return ee()->localize->string_to_timestamp($human_string);
		}
		//EE 2.5.5 and below
		else
		{
			return $this->string_to_timestamp($human_string);
		}
	}
	//END string_to_timestamp

	// --------------------------------------------------------------------


	/**
	 * _no_results 	Parses no_results conditional
	 *
	 * @access  public
	 * @param strong $str
	 * @return string no_(add-on)_results content
	 */

	function _no_results ( $str = '' )
	{

		if( $str != '' AND
			preg_match(
				"/" . LD . "if " . trim($str, '_') . ":no_results" . RD .
				"(.*?)". LD . preg_quote(T_SLASH, '/') . "if" . RD . "/s",
				ee()->TMPL->tagdata,
				$match
			) )
		{
			return $match['1'];
		}
		else
		{
			return $this->no_results();
		}
	}
	// End no results
}
// END CLASS Favorites