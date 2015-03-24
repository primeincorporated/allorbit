<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Favorites - Config
 *
 * NSM Addon Updater config file.
 *
 * @package		Solspace:Favorites
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2015, Solspace, Inc.
 * @link		http://solspace.com/docs/favorites
 * @license		http://www.solspace.com/license_agreement
 * @version		4.0.3
 * @filesource	favorites/config.php
 */

require_once 'constants.favorites.php';

$config['name']    								= 'Favorites';
$config['version'] 								= FAVORITES_VERSION;
$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/favorites';
