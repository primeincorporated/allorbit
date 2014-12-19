<?php if (!defined('BASEPATH')) die('No direct script access allowed');

// include config file
require_once dirname(dirname(__FILE__)).'/editor/config.php';

/**
 * Editor Module Extension File
 *
 * @package			DevDemon_Updater
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2012 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com
 * @see				http://expressionengine.com/user_guide/development/extensions.html
 */
class Editor_ext
{
	public $version			= EDITOR_VERSION;
	public $name			= 'Editor Extension';
	public $description		= 'Supports the Editor Module in various functions.';
	public $docs_url		= 'http://www.devdemon.com';
	public $settings_exist	= FALSE;
	public $settings		= array();
	public $hooks			= array('cp_menu_array');

	// ********************************************************************************* //

	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->site_id = $this->EE->config->item('site_id');
	}

	// ********************************************************************************* //

	/**
     * cp_menu_array
     *
     * @param array $menu
     * @access public
     * @see N/A
     * @return array
     */
    public function cp_menu_array($menu)
    {
        if ($this->EE->extensions->last_call !== false) {
            $menu = $this->EE->extensions->last_call;
        }

        if ($this->EE->input->get('D') != 'cp') {
            return $menu;
        }

        if ($this->EE->input->get('C') != 'admin_content') {
            return $menu;
        }

        if ($this->EE->input->get('M') != 'category_edit') {
            return $menu;
        }

        $this->EE->load->add_package_path(PATH_THIRD . 'editor/');
		$this->EE->lang->loadfile('editor');
		$this->EE->load->library('editor_helper');

		$this->EE->config->load('editor_config');



		// -----------------------------------------
		// Settings
		// -----------------------------------------
		$ext_settings =$this->EE->editor_helper->grab_extension_settings($this->site_id);
		$settings = $this->EE->editor_helper->parse_editor_settings($ext_settings);

		// Do we need to load an additional Language?
		$lang_js = '';
		if (isset($settings['language']) === TRUE && $settings['language'] != 'en') {
			$this->EE->editor_helper->addMcpAssets('js', "redactor/lang/{$settings['language']}.js?v=".EDITOR_VERSION, 'redactor', 'lang');
		}

		$this->EE->editor_helper->addMcpAssets('gjs');
        $this->EE->editor_helper->addMcpAssets('css', 'redactor/redactor.css?v='.EDITOR_VERSION, 'redactor', 'main');
        $this->EE->editor_helper->addMcpAssets('css', 'css/pbf.css?v='.EDITOR_VERSION, 'editor', 'pbf');
        //$this->EE->editor_helper->addMcpAssets('css', 'editor_buttons.css?v='.EDITOR_VERSION, 'editor', 'buttons');
        $this->EE->editor_helper->addMcpAssets('js', 'redactor/redactor.min.js?v='.EDITOR_VERSION, 'redactor', 'main');
        $this->EE->editor_helper->addMcpAssets('js', 'js/handlebars.runtime.min.js?v='.EDITOR_VERSION, 'handlebars', 'runtime');
        $this->EE->editor_helper->addMcpAssets('js', 'js/hbs-templates.js?v='.EDITOR_VERSION, 'editor', 'templates');
        $this->EE->editor_helper->addMcpAssets('js', 'js/pbf.min.js?v='.EDITOR_VERSION, 'editor', 'buttons');


		$html = $this->EE->editor_helper->get_editor_button_html();
		$css_js = $this->EE->editor_helper->get_editor_css_js($settings, 'cat_description');
		$this->EE->cp->add_to_foot($css_js.$html);

		//$this->EE->firephp->log($json);

		return $menu;
	}

	// ********************************************************************************* //

	/**
	 * cp_menu_array
	 *
	 * @param array $menu
	 * @access public
	 * @see N/A
	 * @return array
	 */
	public function cp_css_end()
	{
		$css = '';

		if ($this->EE->extensions->last_call !== FALSE)
		{
			$css = $this->EE->extensions->last_call;
		}

		if (isset($this->EE->editor->css_js_out) === TRUE)
		{
			$css .= $this->EE->editor->css_js_out['css'];
		}

		$css .= "
			{$this->EE->editor->buttons_css}

			@media
            only screen and (-webkit-min-device-pixel-ratio: 2),
            only screen and (   min--moz-device-pixel-ratio: 2),
            only screen and (     -o-min-device-pixel-ratio: 2/1),
            only screen and (        min-device-pixel-ratio: 2),
            only screen and (                min-resolution: 192dpi),
            only screen and (                min-resolution: 2dppx) {
    			{$this->EE->editor->buttons_css_hq}
			}

			#redactor_paste_plaintext_area {border:1px solid #8195A0; border-radius:3px 3px 3px 3px; overflow:scroll; width: 99%; height: 300px;}
			#redactor_paste_plaintext_area p {margin:0 0 10px}

			#EditorPackStyles {}
			#EditorPackStyles a:hover {background-color:#fff;}
		";

		return $css;
	}

	// ********************************************************************************* //

	/**
	 * Called by ExpressionEngine when the user activates the extension.
	 *
	 * @access		public
	 * @return		void
	 **/
	public function activate_extension()
	{
		foreach ($this->hooks as $hook)
		{
			 $data = array(	'class'		=>	__CLASS__,
			 				'method'	=>	$hook,
							'hook'      =>	$hook,
							'settings'	=>	'a:23:{s:15:"editor_settings";s:10:"predefined";s:13:"convert_field";s:4:"none";s:14:"upload_service";s:5:"local";s:20:"file_upload_location";s:1:"0";s:21:"image_upload_location";s:1:"0";s:2:"s3";a:4:{s:4:"file";a:1:{s:6:"bucket";s:0:"";}s:5:"image";a:1:{s:6:"bucket";s:0:"";}s:14:"aws_access_key";s:0:"";s:14:"aws_secret_key";s:0:"";}s:6:"height";s:3:"200";s:9:"direction";s:3:"ltr";s:7:"toolbar";s:3:"yes";s:6:"source";s:3:"yes";s:5:"focus";s:2:"no";s:10:"autoresize";s:3:"yes";s:5:"fixed";s:2:"no";s:12:"convertlinks";s:3:"yes";s:11:"convertdivs";s:3:"yes";s:7:"overlay";s:3:"yes";s:13:"observeimages";s:3:"yes";s:3:"air";s:2:"no";s:3:"wym";s:2:"no";s:18:"allowedtags_option";s:7:"default";s:11:"allowedtags";s:0:"";s:11:"editor_conf";s:1:"1";s:6:"site:1";a:22:{s:15:"editor_settings";s:10:"predefined";s:13:"convert_field";s:4:"none";s:14:"upload_service";s:5:"local";s:20:"file_upload_location";s:1:"0";s:21:"image_upload_location";s:1:"0";s:2:"s3";a:4:{s:4:"file";a:1:{s:6:"bucket";s:0:"";}s:5:"image";a:1:{s:6:"bucket";s:0:"";}s:14:"aws_access_key";s:0:"";s:14:"aws_secret_key";s:0:"";}s:6:"height";s:3:"200";s:9:"direction";s:3:"ltr";s:7:"toolbar";s:3:"yes";s:6:"source";s:3:"yes";s:5:"focus";s:2:"no";s:10:"autoresize";s:3:"yes";s:5:"fixed";s:2:"no";s:12:"convertlinks";s:3:"yes";s:11:"convertdivs";s:3:"yes";s:7:"overlay";s:3:"yes";s:13:"observeimages";s:3:"yes";s:3:"air";s:2:"no";s:3:"wym";s:2:"no";s:18:"allowedtags_option";s:7:"default";s:11:"allowedtags";s:0:"";s:11:"editor_conf";s:1:"2";}}',
							'priority'	=>	100,
							'version'	=>	$this->version,
							'enabled'	=>	'y'
      			);

			// insert in database
			$this->EE->db->insert('exp_extensions', $data);
		}
	}

	// ********************************************************************************* //

	/**
	 * Called by ExpressionEngine when the user disables the extension.
	 *
	 * @access		public
	 * @return		void
	 **/
	public function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('exp_extensions');
	}

	// ********************************************************************************* //

	/**
	 * Called by ExpressionEngine updates the extension
	 *
	 * @access public
	 * @return void
	 **/
	public function update_extension($current=FALSE)
	{
		if ($current == $this->version) return false;

        // Get all existing ones
        $dbexts = array();
        $query = $this->EE->db->select('*')->from('exp_extensions')->where('class', __CLASS__)->get();

        foreach ($query->result() as $row) {
            $dbexts[$row->hook] = $row;
        }

        // Add the new ones
        foreach ($this->hooks as $hook) {
            if (isset($dbexts[$hook]) === true) continue;

            $data = array(
                'class'     =>  __CLASS__,
                'method'    =>  $hook,
                'hook'      =>  $hook,
                'settings'  =>  serialize($this->settings),
                'priority'  =>  100,
                'version'   =>  $this->version,
                'enabled'   =>  'y'
            );

            // insert in database
            $this->EE->db->insert('exp_extensions', $data);
        }

        // Delete old ones
        foreach ($dbexts as $hook => $ext) {
            if (in_array($hook, $this->hooks) === true) continue;

            $this->EE->db->where('hook', $hook);
            $this->EE->db->where('class', __CLASS__);
            $this->EE->db->delete('exp_extensions');
        }

        // Update the version number for all remaining hooks
        $this->EE->db->where('class', __CLASS__)->update('extensions', array('version' => $this->version));
	}

	// ********************************************************************************* //

} // END CLASS

/* End of file ext.editor.php */
/* Location: ./system/expressionengine/third_party/editor/ext.editor.php */
