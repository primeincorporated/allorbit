<?php if (!defined('BASEPATH')) die('No direct script access allowed');

// include config file
require_once dirname(dirname(__FILE__)).'/editor/config.php';

/**
 * Link Button for Editor
 *
 * @package			DevDemon_Editor
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2011 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com/editor/
 */
class Styles_ebtn extends Editor_button
{
	/**
	 * Button info - Required
	 *
	 * @access public
	 * @var array
	 */
	public $info = array(
		'name' 		=> 'Styles',
		'author'	=> 'DevDemon',
		'author_url' => 'http://www.devdemon.com',
		'description'=> 'Styles dropdown',
		'version'	=> EDITOR_VERSION,
		'settings'	=> TRUE,
		'font_icon' => 'dicon-brush',
	);


	/**
	 * Constructor
	 *
	 * @access public
	 *
	 * Calls the parent constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->EE->load->add_package_path(PATH_THIRD . 'editor/');
	}

	// ********************************************************************************* //

	public function display($settings=array())
	{
		// Let's load our CSS/JS
		// $this->css_js('js', 'url', EDITOR_THEME_URL.'editor_buttons.js?v='.EDITOR_VERSION, 'editor_buttons', 'main');
		// $this->css_js('css', 'url', EDITOR_THEME_URL.'editor_buttons.css?v='.EDITOR_VERSION, 'editor_buttons', 'main');
	}

	// ********************************************************************************* //

	public function display_settings($settings=array())
	{
		$data = $settings;
		if (isset($data['styles']) === FALSE) $data['styles'] = array();

		// We added custom_type later on
		foreach ($data['styles'] as &$style)
		{
			if (isset($style['custom_type']) === FALSE) $style['custom_type'] = 'div';
		}

		return $this->EE->load->view('btn/settings_styles', $data, TRUE);
	}

	// ********************************************************************************* //

	public function save_settings($settings=array())
	{
		if (isset($settings['styles']) === FALSE || is_array($settings['styles']) === FALSE) return array();

		$data = array();

		foreach ($settings['styles'] as $style)
		{
			if (isset($style['title']) === FALSE || $style['title'] == FALSE) continue;
			if (isset($style['type']) === FALSE || $style['type'] == FALSE) continue;

			$data['styles'][] = $style;
		}

		return $data;
	}

	// ********************************************************************************* //
}

/* End of file editor.styles.php */
/* Location: ./system/expressionengine/third_party/editor/editor.styles.php */
