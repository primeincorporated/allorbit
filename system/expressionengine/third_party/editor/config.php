<?php

/**
 * Config file for Editor
 *
 * @package			DevDemon_Editor
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2012 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com/editor/
 * @see				http://ee-garage.com/nsm-addon-updater/developers
 */

if ( ! defined('EDITOR_NAME'))
{
	define('EDITOR_NAME',         'Editor');
	define('EDITOR_CLASS_NAME',   'editor');
	define('EDITOR_VERSION',      '3.2.4');
}

$config['name'] 	= EDITOR_NAME;
$config["version"] 	= EDITOR_VERSION;
$config['nsm_addon_updater']['versions_xml'] = 'http://www.devdemon.com/'.EDITOR_CLASS_NAME.'/versions_feed/';

//----------------------------------------
// < EE 2.6.0 backward compat
//----------------------------------------
if (!function_exists('ee'))
{
    function ee()
    {
        static $EE;
        if ( ! $EE) $EE = get_instance();
        return $EE;
    }
}


/* End of file config.php */
/* Location: ./system/expressionengine/third_party/editor/config.php */
