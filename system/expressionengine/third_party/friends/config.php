<?php if ( ! defined('EXT')) exit('No direct script access allowed');


/**
 * Friends - Config
 *
 * NSM Addon Updater config file.
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2014, Solspace, Inc.
 * @link		http://solspace.com/docs/friends
 * @license		http://www.solspace.com/license_agreement
 * @version		1.6.4
 * @filesource	friends/config.php
 */

require_once 'constants.friends.php';

$config['name']									= 'Friends';
$config['version']								= FRIENDS_VERSION;
$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/friends';
