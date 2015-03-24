<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Base Model
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @filesource	favorites/models/favorites_collections.php
 */

class Favorites_Collections extends Module_builder_favorites
{

	/**
	 * Constructor
	 *
	 * @access public
	 * @return object $this
	 */

	public function __construct ()
	{
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));
	}
	//END __construct


	// --------------------------------------------------------------------

	/**
	 * collections - lists collections
	 *
	 * @access	private
	 * @return	array			collection name array
	 */

	public function collections($type = 'entry_id')
	{

		$type_cache_name = is_array($type) ? implode('_', $type) : $type;

		if(ee()->session->cache('favorites', 'collections_'.$type_cache_name))
		{
			return ee()->session->cache('favorites', 'collections_'.$type_cache_name);
		}

		$member_id = ee()->session->userdata('member_id');

		$type_query = is_array($type) ? " WHERE type IN('".rtrim(implode("','", ee()->db->escape_str($type)), ",")."') " : " WHERE type = '" . ee()->db->escape_str($type) . "' ";

		//	----------------------------------------
		//	Get all collections of all types if $type
		//	is not provided. i.e. reset $type_query
		//	----------------------------------------

		if(empty($type))
		{
			$type_query = '';
		}

		$sql = ee()->db->query("/* Favorites ".__FUNCTION__."() */ SELECT *
			FROM exp_favorites_collections "
			. $type_query . "
			ORDER BY collection_id, collection_name, type ASC");

		$output = array();

		if($sql->num_rows > 0)
		{
			foreach($sql->result() as $row)
			{
				$output[$row->collection_id]['collection_id']   = $row->collection_id;
				$output[$row->collection_id]['collection_name'] = $row->collection_name;
				$output[$row->collection_id]['type']            = $row->type;
				$output[$row->collection_id]['default']         = $row->default;
			}
		}

		ee()->session->set_cache('favorites', 'collections_'.$type_cache_name, $output);

		return $output;
	}
	//	End collections


	// --------------------------------------------------------------------

	/**
	 * saved_collections - lists saved collections
	 *
	 * @access	private
	 * @return	array			collection name array
	 */

	public function saved_collections($type = 'entry_id', $item_id = '', $favoriter_id = FALSE)
	{

		if(ee()->session->cache('favorites', 'saved_collections_'.$type.'_'.$item_id))
		{
			return ee()->session->cache('favorites', 'saved_collections_'.$type.'_'.$item_id);
		}

		if( $favoriter_id === FALSE || ! is_numeric($favoriter_id) )
		{
			$favoriter_id = ee()->session->userdata('member_id');
		}

		$sql_item_id = is_numeric($item_id) ? " AND f.item_id = " . ee()->db->escape_str($item_id) : '';

		$sql = ee()->db->query("/* Favorites saved_collections() */ SELECT c.collection_id, c.collection_name as collection
			FROM exp_favorites f
			JOIN exp_favorites_collections c ON c.collection_id = f.collection_id
			WHERE f.favoriter_id = " . ee()->db->escape_str($favoriter_id) . "
			AND f.type = '" . ee()->db->escape_str($type) . "'" .
			$sql_item_id . "
			GROUP BY f.collection_id
			ORDER BY f.collection_id ASC");

		$output = array();

		if($sql->num_rows > 0)
		{
			foreach($sql->result() as $row)
			{
				$output[$row->collection_id] = $row->collection;
			}

		}

		ee()->session->set_cache('favorites', 'saved_collections_'.$type.'_'.$item_id, $output);

		return $output;
	}
	//	End saved_collections

	// --------------------------------------------------------------------


	/**
	 * Retrieve default collections
	 */
	public function default_collections()
	{
		if(ee()->session->cache('favorites', 'default_collections'))
		{
			return ee()->session->cache('favorites', 'default_collections');
		}

		$sql = ee()->db->query("/* Favorites ".__FUNCTION__."() */
			SELECT collection_id, collection_name, type
			FROM exp_favorites_collections
			WHERE `default` = 'y'");

		$output = array();

		if($sql->num_rows() > 0)
		{
			foreach($sql->result() as $row)
			{
				$output[$row->type]['collection_id']   = $row->collection_id;
				$output[$row->type]['collection_name'] = $row->collection_name;
			}
		}

		ee()->session->set_cache('favorites', 'default_collections', $output);

		return $output;
	} // END default_collections

	// --------------------------------------------------------------------


	/**
	 * Find collection ID from a collection name
	 * @param  string $collection_name 	Collection name, eg. "public"
	 * @param  string $type            	The favorite type, eg. entry_id/member
	 * @return int                  	The collection_id
	 */
	public function collection_id_from_name($collection_name = '', $type = 'entry_id')
	{
		$unique_suffix = base64_encode($type.$collection_name);

		if(ee()->session->cache('favorites', 'collection_id_from_name_'.$unique_suffix))
		{
			return ee()->session->cache('favorites', 'collection_id_from_name'.$unique_suffix);
		}

		$sql = ee()->db->query("/* Favorites ".__FUNCTION__."() */
			SELECT collection_id
			FROM exp_favorites_collections
			WHERE collection_name = '" . ee()->db->escape_str($collection_name) . "'
			AND type = '" . ee()->db->escape_str($type) . "'");

		$output = 0;

		if($sql->num_rows() > 0)
		{
			foreach($sql->result() as $row)
			{
				$output = $row->collection_id;
			}
		}

		ee()->session->set_cache('favorites', 'collection_id_from_name'.$unique_suffix, $output);

		return $output;

	} // END collection_id_from_name()


	// --------------------------------------------------------------------

	/**
	 * Create new collection from a collection name
	 * @param  string $collection_name 	Collection name, eg. "public"
	 * @param  string $type            	The favorite type, eg. entry_id/member
	 * @return int                  	The new collection_id
	 */
	public function new_collection($collection_name = '', $type = 'entry_id')
	{
		$data['collection_name'] = $collection_name;
		$data['type']            = $type;
		ee()->db->insert('exp_favorites_collections', $data);

		return ee()->db->insert_id();
	} // END new_collection()

	// --------------------------------------------------------------------

	/**
	 * Check if collection name already exists for collection type
	 * @param  array $data Collection name and type
	 * @return bool       Duplicate found or not.
	 */
	public function is_duplicate($data)
	{
		if( ! isset($data['collection_name']) || empty($data['collection_name']))
		{
			return TRUE;
		}

		ee()->db->where('collection_name', $data['collection_name']);

		if( isset($data['type']) )
		{
			ee()->db->where('type', $data['type']);
		}

		$sql = ee()->db->get('exp_favorites_collections');

		if($sql->num_rows() > 0)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	} // END check_duplicate

	// --------------------------------------------------------------------

}
//END Favorites_Collections