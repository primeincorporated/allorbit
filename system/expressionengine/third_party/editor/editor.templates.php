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
 * @link			http://www.devdemon.com/editor_pack/
 */
class Templates_ebtn extends Editor_button
{
	/**
	 * Button info - Required
	 *
	 * @access public
	 * @var array
	 */
	public $info = array(
		'name' 		=> 'Templates',
		'author'	=> 'DevDemon',
		'author_url' => 'http://www.devdemon.com',
		'description'=> 'Create HTML Templates',
		'version'	=> EDITOR_VERSION,
		'settings'	=> TRUE,
		'font_icon' => 'dicon-page-blocks',
	);

	/**
	 * Constructor
	 *
	 * @access public
	 *
	 * Calls the parent constructor
	 */
	public function __construct($settings=array())
	{
		parent::__construct($settings);
		$this->EE->load->add_package_path(PATH_THIRD . 'editor/');
	}

	// ********************************************************************************* //

	public function display($settings=array())
	{
		// Let's load our CSS/JS
		//$this->css_js('js', 'url', EDITOR_THEME_URL.'editor_buttons.js?v='.EDITOR_VERSION, 'editor_buttons', 'main');
		//$this->css_js('css', 'url', EDITOR_THEME_URL.'editor_buttons.css?v='.EDITOR_VERSION, 'editor_buttons', 'main');
	}

	// ********************************************************************************* //

	public function display_settings($settings=array())
	{
		$data = $settings;
		if (isset($data['templates']) === FALSE) $data['templates'] = array();

		return $this->EE->load->view('btn/settings_templates', $data, TRUE);
	}

	// ********************************************************************************* //

	public function save_settings($settings=array())
	{
		if (isset($settings['templates']) === FALSE || is_array($settings['templates']) === FALSE) return array();

		$data = array();

		foreach ($settings['templates'] as $template)
		{
			if (isset($template['title']) === FALSE || $template['title'] == FALSE) continue;

			$data['templates'][] = $template;
		}

		return $data;
	}

	// ********************************************************************************* //
}

/* End of file editor.template.php */
/* Location: ./system/expressionengine/third_party/editor_pack/editor.template.php */
