<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Base Model
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @filesource	favorites/models/favorites_members.php
 */

class Favorites_Members extends Module_builder_favorites
{

	/**
	 * Constructor
	 *
	 * @access public
	 * @return object $this
	 */

	public function __construct ()
	{
		parent::__construct();
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));
		$this->type = '';

		//	----------------------------------------
		// 	CP Views don't need template tag-style prefixes
		//	----------------------------------------

		if(REQ == 'CP')
		{
			$this->tag_prefix = '';
		}
		else
		{
			$this->tag_prefix = 'favorites:';
		}
	}
	//END __construct

	// --------------------------------------------------------------------

	/**
	 * function get_member_id_from_name
	 * Retrieve the member_id based on a username or screen name
	 * @param  string $name The username or screen_name
	 * @param  string $type What to search in exp_members, default: username
	 * @return string member_id
	 */

	public function get_member_id_from_name($name, $type = 'username')
	{

		if(ee()->session->cache('favorites', 'member_id_from_'.$type.'_'.$name))
		{
			return ee()->session->cache['favorites']['member_id_from_'.$type.'_'.$name];
		}

		$output = 0;

		$sql = ee()->db->query("/* Favorites get_member_id_from_username */ SELECT member_id FROM exp_members WHERE " . $type . " = '" . ee()->db->escape_str($name) . "'");

		if($sql->num_rows() > 0)
		{
			$row = $sql->first_row();
			$output = $row->member_id;
		}

		ee()->session->set_cache('favorites', 'member_id_from_'.$type.'_'.$name, $output);

		return $output;
	} // END get_member_id_from_name()


	// --------------------------------------------------------------------

	/**
	 * Function get_member_fields
	 * Retrieve and cache custom member fields data
	 * @return array member field data
	 */

	public function get_member_fields()
	{
		if(ee()->session->cache('favorites', 'member_fields'))
		{
			return ee()->session->cache['favorites']['member_fields'];
		}

		$output = array();

		$sql = ee()->db->query("/* Favorites ".__FUNCTION__."() */ SELECT m_field_id, m_field_name FROM exp_member_fields");

		if($sql->num_rows() > 0)
		{
			foreach($sql->result_array() as $row)
			{
				$output[$row['m_field_id']] = $row['m_field_name'];
			}
		}

		$sql = ee()->db->query("/* Favorites ".__FUNCTION__."() */ SHOW COLUMNS FROM exp_members");

		if($sql->num_rows() > 0)
		{
			foreach($sql->result_array() as $row)
			{
				$output[$row['Field']] = $row['Field'];
			}
		}

		ee()->session->set_cache('favorites', 'member_fields', $output);

		return $output;

	} // END get_member_fields()


	/**
	 * Get Fav data
	 * @param  string $member_id The member_id you might want to filter results with
	 * @param  array  $fav_ids 	 An array of fav_ids if you want to limit display to certain favorites.
	 * @return array  $output 	 The favorite data, as an array.
	 */
	public function get_favorite_data($params = array())
	{
		if( ee()->session->cache('favorites', 'favorites_'.base64_encode(serialize($params))) )
		{
			return ee()->session->cache['favorites']['favorites_'.base64_encode(serialize($params))];
		}

		$output = array();

		if(empty($params))
		{
			return $output;
		}

		//	----------------------------------------
		//	Determine the type of favorite and its ID
		//	----------------------------------------

		if(isset($params['type']))
		{
			$this->type    = $params['type'];
		}

		if(isset($params['item_id']))
		{
			$this->item_id = $params['item_id'];
		}
		else
		{
			unset($this->item_id);
		}

		$where_type    = ! empty($this->type) ? " AND f.type = '" . ee()->db->escape_str($this->type) . "'" : '';
		$where_item_id    = ! empty($this->item_id) ? " AND f.item_id = " . ee()->db->escape_str($this->item_id) : '';

		$collection       = isset($params['collection_id']) ? $params['collection_id'] : ee()->input->get_post('collection_id', TRUE);

		$select_sql       = '';
		$join_sql         = '';
		$where_member_id  = '';
		$where_collection = '';
		$where_fav_ids    = '';
		$limit_sql        = '';

		//	----------------------------------------
		//	Filter my favoriter member_id
		//	----------------------------------------

		$favoriter_id = isset($params['favoriter_id']) ? $params['favoriter_id'] : ee()->input->get_post('member_id', TRUE);

		if( ! empty($favoriter_id) )
		{
			$where_member_id = " AND f.favoriter_id = " . ee()->db->escape_str($favoriter_id);
		}

		//	----------------------------------------
		//	Filter by collection
		//	----------------------------------------

		if( ! empty($collection) )
		{
			$where_collection = " AND f.collection_id = '" . ee()->db->escape_str($collection) . "'";
		}

		//	----------------------------------------
		//	Filter by favorite_ids, if these are provided
		//	----------------------------------------

		if( isset($params['favs_ids']) && ! empty($params['favs_ids']) && is_array($params['favs_ids']) )
		{
			$where_fav_ids = ' AND f.favorites_id IN (' . ee()->db->escape_str(rtrim(implode(',', $params['favs_ids']), ',')) . ')';
		}

		$site_id  = isset($params['site_id']) && ctype_digit($params['site_id']) ? $params['site_id'] : $this->clean_site_id;

		if( ! empty($this->type) )
		{
			if($this->type == 'entry_id')
			{
				$select_sql = " ct.*, ";
				$join_sql = " LEFT JOIN exp_channel_titles ct ON ct.entry_id = f.item_id ";
			}

			if($this->type == 'member')
			{
				$select_sql = " m.member_id as m_member_id, m.screen_name, m.username, ";
				$join_sql = " LEFT JOIN exp_members m ON m.member_id = f.item_id ";
			}
		}

		if( isset($params['offset']) )
		{
			$limit_sql = " LIMIT " . $params['offset'];
		}

		if( isset($params['limit']) && ! empty($limit_sql) )
		{
			$limit_sql .= ", " . $params['limit'];
		}

		$sql_string = "/* Favorites ".__FUNCTION__."() */
			SELECT SQL_CALC_FOUND_ROWS
				COUNT(f.item_id) AS total,
				f.favorites_id AS favorite_id,
				f.collection_id,
				f.favoriter_id,
				f.type,
				f.item_id,
				f.site_id,
				f.favorited_date,
				f.notes, "
				. $select_sql . "
				fc.collection_id,
				fc.collection_name
			FROM exp_favorites f
			LEFT JOIN exp_favorites_collections fc ON fc.collection_id = f.collection_id "
			. $join_sql . "
			WHERE f.site_id = " . $site_id
			. $where_member_id
			. $where_collection
			. $where_fav_ids
			. $where_type
			. $where_item_id . "
			GROUP BY f.collection_id, f.item_id
			ORDER BY total DESC"
			. $limit_sql;

		$sql = ee()->db->query($sql_string);

		$count = 1;

		if($sql->num_rows() > 0)
		{
			$data_array[$this->tag_prefix.'total_results'] = $sql->num_rows();

			$absolute_results_query = ee()->db->query("/* Favorites ".__FUNCTION__."() total results */ \n SELECT FOUND_ROWS() as absolute_results");

			foreach($absolute_results_query->result_array() as $row)
			{
				$data_array[$this->tag_prefix.'absolute_results'] = $row['absolute_results'];
			}

			foreach($sql->result_array() as $row)
			{
				$data_array[$this->tag_prefix.'count'] = $count;
				$count++;

				foreach($row as $item => $data)
				{
					$data_array[$this->tag_prefix.$item] = $data;
				}

				$url_method  = $this->type != 'entry_id' ? 'member' : 'entry';
				$url_id_type = $this->type != 'entry_id' ? 'member_id' : 'entry_id';

				$data_array['url'] = $this->base.'&method='.$url_method.'&'.$url_id_type.'='.$row['item_id'];

				$output[$this->type][] = $data_array;
			}
		}

		ee()->session->set_cache('favorites', 'favorites_'.base64_encode(serialize($params)), $output);

		return $output;
	} // END get_favorite_data

	// --------------------------------------------------------------------


	/**
	 * Get a list of screen names from people who have favorited something.
	 * @param  array  $member_ids An array of member_ids
	 * @return array             An array of ID/Screen Name
	 */
	public function get_favoriters($member_ids = array())
	{
		if(ee()->session->cache('favorites', 'favoriters'))
		{
			return ee()->session->cache['favorites']['favoriters'];
		}

		$output = array();

		$sql = ee()->db->query("/* Favorites ".__FUNCTION__."() */
			SELECT m.member_id, m.screen_name
			FROM exp_members m
			JOIN exp_favorites f ON f.favoriter_id = m.member_id");

		if($sql->num_rows() > 0)
		{
			foreach($sql->result_array() as $row)
			{
				$output[$row['member_id']] = $row['screen_name'];
			}
		}

		ee()->session->set_cache('favorites', 'favoriters', $output);

		return $output;
	} // END get_favoriters

	// --------------------------------------------------------------------


}
//END Favorites_Members