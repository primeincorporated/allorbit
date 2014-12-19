<?php if (!defined('BASEPATH')) die('No direct script access allowed');

// include config file
require_once PATH_THIRD.'editor/config.php';

/**
 * Editor Helper File
 *
 * @package         DevDemon_Editor
 * @author          DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright       Copyright (c) 2007-2010 Parscale Media <http://www.parscale.com>
 * @license         http://www.devdemon.com/license/
 * @link            http://www.devdemon.com
 */
class Editor_helper
{
    private $ekey = 'SADFo92jzVnzXj39IUYGvi6eL8h6RvJV8CytUiouV547vCytDyUFl76R';

    /**
     * Module Short Name
     *
     * @var string
     * @access private
     */
    private $package_name = 'editor';

    public function __construct()
    {
        // Creat EE Instance
        $this->EE =& get_instance();

        if (isset($this->EE->editor) === FALSE) {
            $this->EE->editor = new stdClass();
            $this->EE->editor->css = array('inline' => array(), 'url' => array());
            $this->EE->editor->js = array('inline' => array(), 'url' => array());
        }

        $this->site_id = $this->EE->config->item('site_id');
        $this->AJAX = NULL;

        $this->EE->load->library('firephp');

        // When installing this should NOT run
        if ($this->EE->input->get('M') != 'package_settings')
        {
            $this->find_editor_buttons();
        }

    }

    // ********************************************************************************* //

    public function parse_editor_settings($settings=array())
    {
        $default = $this->EE->config->item('editor_defaults');

        if ((isset($settings['editor_conf']) === TRUE && $settings['editor_conf'] > 0) && (isset($settings['editor_settings']) === TRUE && $settings['editor_settings'] == 'predefined'))
        {
            $editor_conf = $settings['editor_conf'];

            $query = $this->EE->db->select('config_settings')->from('exp_editor_configs')->where('config_id', $editor_conf)->get();

            if ($query->num_rows() > 0)
            {
                $settings = unserialize(base64_decode($query->row('config_settings')));
            }

            $settings['editor_conf'] = $editor_conf;
            $settings['editor_settings'] = 'predefined';
        }

        if (isset($settings['formattingtags']) === TRUE && empty($settings['formattingtags']) === FALSE)
        {
            unset($default['formattingtags']);
        }

        $settings = $this->EE->editor_helper->array_extend($default, $settings);

        return $settings;
    }

    // ********************************************************************************* //

    public function find_editor_buttons()
    {
        $this->EE->load->helper('directory');
        $this->EE->editor->buttons_css = '';
        $this->EE->editor->buttons_css_hq = '';
        $css = '';
        $css_hq = '';

        // Did we already do this?
        if (isset($this->EE->editor->buttons) === FALSE)
        {
            // Default Button Settings
            $default_settings = array();
            $this->EE->db->select('button_class, button_settings');
            $this->EE->db->from('exp_editor_buttons');
            $this->EE->db->where('config_id', 0);
            $this->EE->db->where('lowvar_id', 0);
            $this->EE->db->where('matrixcol_id', 0);
            $query = $this->EE->db->get();

            foreach ($query->result() as $row)
            {
                $default_settings[$row->button_class] = @unserialize($row->button_settings);
                if (is_array($default_settings[$row->button_class]) === FALSE) $default_settings[$row->button_class] = array();
            }

            // Load our main button class
            require_once PATH_THIRD.'editor/libraries/editor_button.php';
            $this->EE->editor->buttons = array();

            // Map the third_party directory
            $map = directory_map(PATH_THIRD, 2);

            // Loop over all directories
            foreach ($map as $dir => $files)
            {
                // Directory? Continue
                if (is_array($files) === FALSE) continue;

                // Loop over all root files of the directory
                foreach ($files as $file)
                {
                    // The file must start with: editor.
                    if (strpos($file, 'editor.') === 0)
                    {
                        // Get the class name
                        $name = str_replace(array('editor.', '.php'), '', $file);
                        $class = ucfirst($name).'_ebtn';

                        // Load the file
                        $path = PATH_THIRD.$dir.'/'.$file;
                        require_once $path;

                        // Does the class exists now?
                        if (class_exists($class) === FALSE) continue;

                        // Add the default settings
                        $settings = array();
                        if (isset($default_settings[$name]) === TRUE) $settings = $default_settings[$name];

                        // Initiate it
                        $this->EE->editor->buttons[$name] = new $class($settings);

                        // Just in case lets add the settings
                        $this->EE->editor->buttons[$name]->settings = $settings;

                        // Language key
                        $this->EE->lang->language['ed:btn:'.$name] = $this->EE->editor->buttons[$name]->info['name'];

                        // Does it have any inline button CSS?
                        if (isset($this->EE->editor->buttons[$name]->info['button_css']) === TRUE && $this->EE->editor->buttons[$name]->info['button_css'] != FALSE)
                        {
                            $css .= "body .redactor_toolbar li a.redactor_btn_{$name} {{$this->EE->editor->buttons[$name]->info['button_css']}} \n";
                        }

                        // Does it have any inline button CSS HQ?
                        if (isset($this->EE->editor->buttons[$name]->info['button_css_hq']) === TRUE && $this->EE->editor->buttons[$name]->info['button_css_hq'] != FALSE)
                        {
                            $css_hq .= "body .redactor_toolbar li a.redactor_btn_{$name} {{$this->EE->editor->buttons[$name]->info['button_css_hq']}} \n";
                        }
                    }
                }
            }

            $this->EE->editor->buttons_css = $css;
            $this->EE->editor->buttons_css_hq = $css_hq;
        }
    }

    // ********************************************************************************* //

    public function get_editor_css_js($settings=array(), $textarea_id='', $output_redactor_js=TRUE)
    {
        $out = array('js' => '', 'css' => '');

        // Add the Pages JS
        $out['js'] .= $this->pages_js();

        // Buttons CSS
        $out['css'] = "
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
        ";

        //----------------------------------------
        // Inline CSS/JS
        //----------------------------------------

        // Add all inline JS
        foreach ($this->EE->editor->js['inline'] as $inline_js) {
            $out['js'] .= "\n{$inline_js}\n";
        }

        // Add all inline CSS
        foreach ($this->EE->editor->css['inline'] as $inline_css) {
            $out['css'] .= "\n{$inline_css}\n";
        }

        //----------------------------------------
        // Button Settings JSON
        //----------------------------------------
        $button_settings = array();

        foreach ($this->EE->editor->buttons as $class => $btn) {
            $button_settings[$class] = $btn->settings;
        }

        $button_settings = $this->generate_json($button_settings);

        $out['js'] .= "Editor.settings = {$button_settings};";

        if ($output_redactor_js == TRUE)
        {
            //----------------------------------------
            // Redactor JSON
            //----------------------------------------
            $redactor_json = $this->get_editor_json($settings);
            $out['js'] .= "jQuery('#{$textarea_id}').redactor({$redactor_json});\n";
        }


        //----------------------------------------
        // Return
        //----------------------------------------
        $return = '';

        // Add all JS URLS
        foreach ($this->EE->editor->js['url'] as $js_url) {
            $return .= "\n<script type='text/javascript' src='{$js_url}'></script>";
        }

        // Add all CSS URLS
        foreach ($this->EE->editor->css['url'] as $css_url) {
            $return .= "\n<link rel='stylesheet' href='{$css_url}' type='text/css' media='print, projection, screen' />";
        }

        // Returned value
        $return .= "
            <script type='text/javascript'>
            {$out['js']}
            </script>

            <style type='text/css'>
            {$out['css']}
            </style>
        ";

        // Lets store this (we use this in the extension)
        $this->EE->editor->css_js_out = $out;

        // Return it
        return $return;
    }

    // ********************************************************************************* //

    public function get_editor_button_html()
    {
        $html = '';

        // Loop over all buttons
        foreach ($this->EE->editor->buttons as $btn) {
            // Call the display method and get any html
            $html .= $btn->display($btn->settings);
        }

        // Return it
        return $html;
    }

    // ********************************************************************************* //

    public function get_editor_json($settings=array())
    {
        $this->EE->load->library('firephp');

        // Are there any config overrides
        $config_override = $this->EE->config->item('editor');

        if ($config_override != false && is_array($config_override)) {
            $settings = $this->array_extend($settings, $config_override);
        }


        require_once(PATH_THIRD.'editor/libraries/zend_json.php');
        Zend_Json::$useBuiltinEncoderDecoder = true;

        $js = new stdClass();


        // Do we need to load an additional Language?
        if (isset($settings['language']) === TRUE && $settings['language'] != 'en')
        {
            $js->lang = $settings['language'];
        }

        // Minheight
        if (isset($settings['height']) === TRUE && $settings['height'] > 0) $js->minHeight = $settings['height'];

        // Direction
        if (isset($settings['direction']) === TRUE && $settings['direction'] == 'rtl') $js->direction = 'rtl';

        // Toolbar
        if (isset($settings['toolbar']) === TRUE && $settings['toolbar'] == 'no') $js->toolbar = FALSE;

        // Source
        if (isset($settings['source']) === TRUE && $settings['source'] == 'no') $js->buttonSource = FALSE;

        // focus
        if (isset($settings['focus']) === TRUE && $settings['focus'] == 'yes') $js->focus = TRUE;

        // Auto Resize
        if (isset($settings['autoresize']) === TRUE && $settings['autoresize'] == 'no') $js->autoresize = FALSE;

        // fixed
        if (isset($settings['fixed']) === TRUE && $settings['fixed'] == 'yes') {
            $js->toolbarFixed = TRUE;
            //$js->toolbarFixedTarget = '#sub_hold_field_3';
            //$js->toolbarFixedTopOffset= 50;
        }

        // Convert Links
        if (isset($settings['convertlinks']) === TRUE && $settings['convertlinks'] == 'no') $js->convertLinks = FALSE;

        // Convert Divs
        if (isset($settings['convertdivs']) === TRUE && $settings['convertdivs'] == 'no') $js->convertDivs = FALSE;

        // Overlay
        if (isset($settings['overlay']) === TRUE && $settings['overlay'] == 'no') $js->modalOverlay = FALSE;

        // Observe Images
        if (isset($settings['observeimages']) === TRUE && $settings['observeimages'] == 'no') $js->observeImages = FALSE;

        // Keyboard shortcuts
        if (isset($settings['shortcuts']) === TRUE && $settings['shortcuts'] == 'no') $js->shortcuts = FALSE;

        // wym
        if (isset($settings['wym']) === TRUE && $settings['wym'] == 'yes') $js->wym = TRUE;

        if (isset($settings['linebreaks']) === TRUE && $settings['linebreaks'] == 'yes') $js->linebreaks = TRUE;

        if (isset($settings['remove_empty_tags']) === TRUE && $settings['remove_empty_tags'] == 'no') $js->removeEmptyTags = FALSE;

        // protocol
        if (isset($settings['protocol']) === TRUE && $settings['protocol'] == 'no') $js->linkProtocol = '';

        // Allowed Tags
        if (isset($settings['allowedtags_option']) === TRUE && $settings['allowedtags_option'] == 'custom')
        {
            if (isset($settings['allowedtags']) == FALSE || is_array($settings['allowedtags']) == FALSE) $settings['allowedtags'] = array();
            $js->allowedTags = $settings['allowedtags'];
        }

        // Denied Tags
        if (isset($settings['deniedtags_option']) === TRUE && $settings['deniedtags_option'] == 'custom')
        {
            if (isset($settings['deniedtags']) == FALSE || is_array($settings['deniedtags']) == FALSE) $settings['deniedtags'] = array();
            $js->deniedTags = $settings['deniedtags'];
        }

        // Formatting Tags
        if (isset($settings['formattingtags']) === TRUE && empty($settings['formattingtags']) === FALSE)
        {
            $js->formattingTags = $settings['formattingtags'];
        }

        // Custom CSS File
        if (isset($settings['css_file']) === TRUE && empty($settings['css_file']) === FALSE)
        {
            $css_file = trim($settings['css_file']);
            if ($css_file != FALSE)
            {
                $js->iframe = TRUE;

                $js_css = json_encode(explode(',', $settings['css_file']));
                $js->css = new Zend_Json_Expr($js_css);
            }
        }

        // Buttons
        if (isset($settings['buttons']) === TRUE)
        {
            $js->buttons = $settings['buttons'];
        }
        else
        {
            $js->buttons = array();
        }

        // Plugins
        if (isset($settings['plugins']) === false || empty($settings['plugins'])) {
            $settings['plugins'] = array();
        }

        $settings['plugins'][] = 'dd_keyboard_shortcuts';
        $settings['plugins'][] = 'link';

        $js->plugins = $settings['plugins'];

        // -----------------------------------------
        // Override buttons
        // -----------------------------------------
        foreach ($settings['buttons'] as $class)
        {
            if (isset($this->EE->editor->buttons[$class]) === FALSE) continue;

            $js->plugins[] = $class;
        }

        // Air
        if (isset($settings['air']) === TRUE && $settings['air'] == 'yes')
        {
            $js->air = TRUE;
            $js->airButtons = $js->buttons;
        }

        $act_url = $this->EE->editor_helper->getRouterUrl('url', 'ACT_file_upload');

        // Local File Upload
        if (isset($settings['upload_service']) === TRUE && $settings['upload_service'] == 'local')
        {
            // File Upload
            if (isset($settings['file_upload_location']) === TRUE && $settings['file_upload_location'] > 0)
            {
                $js->fileUpload = $act_url . '&action=file&upload_location=' . $settings['file_upload_location'];
                $js->fileUploadCallback = new Zend_Json_Expr('Editor.UploadCallback');
            }

            // Image Upload
            if (isset($settings['image_upload_location']) === TRUE && $settings['image_upload_location'] > 0)
            {
                $js->imageUpload = $act_url . '&action=image&upload_location=' . $settings['image_upload_location'];
                $js->imageUploadCallback = new Zend_Json_Expr('Editor.UploadCallback');

                if (isset($settings['image_browsing']) === TRUE && $settings['image_browsing'] == 'yes')
                {
                    $js->imageGetJson = $act_url . '&action=image_browser&upload_location=' . $settings['image_upload_location'];

                    if (isset($settings['image_subdir']) === TRUE && $settings['image_subdir'] == 'yes')
                    {
                        $js->imageGetJson .= '&subdir=yes';
                    }
                }
            }
        }

        // S3
        if (isset($settings['upload_service']) === TRUE && $settings['upload_service'] == 's3')
        {
            $js->s3 = $act_url.'&action=s3_info';
            $string = base64_encode($this->encrypt_string(serialize($settings['s3'])));

            $js->s3 .= "&s3={$string}&";
            //$js->fileUpload = true;
        }




        // Js Call Backs
        if (isset($settings['callbacks']) === true && is_array($settings['callbacks']) === true) {
            foreach ($settings['callbacks'] as $callback => $val) {
                $callback = $callback . 'Callback';
                $val = trim($val);
                if (empty($val) === true) continue;

                $js->{$callback} = new Zend_Json_Expr($val);
            }
        }

        $js->dropdownShowCallback = new Zend_Json_Expr('Editor.dropdownShowCallback');

/*
        // TEST
        $js = new stdClass();
        $js->buttons = array('bold', 'paste_plain', 'styles', 'templates', 'channel_images', 'link');
        $js->plugins = array();
        $js->plugins[] = 'paste_plain';
        $js->plugins[] = 'styles';
        $js->plugins[] = 'templates';
        $js->plugins[] = 'channel_images';
        $js->plugins[] = 'dd_keyboard_shortcuts';

        //$js->plugins[] = 'fontcolor';
        //$js->plugins[] = 'fontsize';
        //$js->plugins[] = 'textdirection';
        //$js->plugins[] = 'fullscreen';
*/

        //$this->EE->firephp->log($js);

        $js = Zend_Json::encode($js, false, array('enableJsonExprFinder' => true));
//      $js = $this->generate_json($js);
        //$this->EE->firephp->log($js);

        return $js;
    }

    // ********************************************************************************* //

    public function get_editor_buttons()
    {
        $buttons = $this->EE->config->item('editor_default_buttons');

        foreach ($this->EE->editor->buttons as $name => $btn)
        {
            if (in_array($name, $buttons) === TRUE) continue;
            $buttons[] = $name;
        }

        return $buttons;
    }

    // ********************************************************************************* //

    public function pages_js()
    {
        $site_pages = array();

        if (isset($this->EE->session->cache['editor']['pages_installed']) === FALSE)
        {
            $query = $this->EE->db->query('SELECT * FROM exp_modules WHERE module_name = "Pages" OR module_name = "Structure" ');
            if ($query->num_rows() > 0) $this->EE->session->cache['editor']['pages_installed'] = TRUE;
            else $this->EE->session->cache['editor']['pages_installed'] = FALSE;
        }

        if ($this->EE->session->cache['editor']['pages_installed'] == FALSE)
        {
            return;
        }

        $this->pages_get();

        $js  = '';

        $string = '';
        foreach ($this->EE->session->cache['editor']['pages'] as $xentry_id => $title)
        {
            $title = htmlspecialchars($title);
            $string .= "<option value='{$this->EE->session->cache['editor']['pages_urls'][$xentry_id]}'>{$title}</option>";
        }

        $js .= 'Editor.site_pages = "'.$string.'";';

        return $js;
    }

    // ********************************************************************************* //

    public function pages_get()
    {
        if (isset($this->EE->session->cache['editor']['pages']) === FALSE)
        {
            $this->EE->session->cache['editor']['pages'] = array();
            $this->EE->session->cache['editor']['pages_urls'] = array();
            $pages = $this->EE->config->item('site_pages');

            // Any pages?
            if (isset($pages[$this->site_id]) === FALSE)
            {
                $pages[$this->site_id] = array( 'uris'=>array() );
            }

            $pages = $pages[$this->site_id];

            if (empty($pages['uris']) === FALSE)
            {
                $this->EE->db->select('entry_id, channel_id, title, url_title, status');
                $this->EE->db->from('exp_channel_titles');
                $this->EE->db->where_in('entry_id', array_keys($pages['uris']) );
                $this->EE->db->order_by('title', 'asc');
                $query = $this->EE->db->get();

                // index entries by entry_id
                $entry_data = array();
                foreach ($query->result_array() as $entry)
                {
                    if (isset($pages['uris'][ $entry['entry_id'] ]) === FALSE) continue;

                    $url = $this->EE->functions->create_page_url($pages['url'], $pages['uris'][ $entry['entry_id'] ]);
                    if (!$url || $url == '/') continue;

                    $this->EE->session->cache['editor']['pages'][ $entry['entry_id'] ] = $entry['title'];
                    $this->EE->session->cache['editor']['pages_urls'][ $entry['entry_id'] ] = $url;
                }
            }
        }
    }

    // ********************************************************************************* //

    /**
     * Get Upload Preferences (Cross-compatible between ExpressionEngine 2.0 and 2.4)
     * @param  int $group_id Member group ID specified when returning allowed upload directories only for that member group
     * @param  int $id       Specific ID of upload destination to return
     * @return array         Result array of DB object, possibly merged with custom file upload settings (if on EE 2.4+)
     */
    public function get_upload_preferences($group_id = NULL, $id = NULL, $ignore_site_id = FALSE)
    {
        if (version_compare(APP_VER, '2.4', '>='))
        {
            $this->EE->load->model('file_upload_preferences_model');
            $row = $this->EE->file_upload_preferences_model->get_file_upload_preferences($group_id, $id, $ignore_site_id);
            $this->EE->session->cache['upload_prefs'][$id] = $row;
            return $row;
        }

        if (version_compare(APP_VER, '2.1.5', '>='))
        {
            // for admins, no specific filtering, just give them everything
            if ($group_id == 1)
            {
                // there a specific upload location we're looking for?
                if ($id != '')
                {
                    $this->EE->db->where('id', $id);
                }

                $this->EE->db->from('upload_prefs');
                if ($ignore_site_id != TRUE) $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
                $this->EE->db->order_by('name');

                $result = $this->EE->db->get();
            }
            else
            {
                // non admins need to first be checked for restrictions
                // we'll add these into a where_not_in() check below
                $this->EE->db->select('upload_id');
                $no_access = $this->EE->db->get_where('upload_no_access', array('member_group'=>$group_id));

                if ($no_access->num_rows() > 0)
                {
                    $denied = array();
                    foreach($no_access->result() as $result)
                    {
                        $denied[] = $result->upload_id;
                    }
                    $this->EE->db->where_not_in('id', $denied);
                }

                // there a specific upload location we're looking for?
                if ($id)
                {
                    $this->EE->db->where('id', $id);
                }

                $this->EE->db->from('upload_prefs');
                if ($ignore_site_id != TRUE) $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
                $this->EE->db->order_by('name');

                $result = $this->EE->db->get();
            }
        }
        else
        {
            $this->EE->load->model('tools_model');
            $result = $this->EE->tools_model->get_upload_preferences($group_id, $id);
        }

        // If an $id was passed, just return that directory's preferences
        if ( ! empty($id))
        {
            $result = $result->row_array();
            $this->EE->session->cache['upload_prefs'][$id] = $result;
            return $result;
        }

        // Use upload destination ID as key for row for easy traversing
        $return_array = array();
        foreach ($result->result_array() as $row)
        {
            $return_array[$row['id']] = $row;
        }

        $this->EE->session->cache['upload_prefs'][$id] = $return_array;

        return $return_array;
    }

    // ********************************************************************************* //

    //public function getRouterUrl($type='url', $method='actionGeneralRouter')
    public function getRouterUrl($type='url', $method='ACT_file_upload')
    {
        // -----------------------------------------
        // Grab action_id
        // -----------------------------------------
        if (isset($this->EE->session->cache[$this->package_name]['router_url'][$method]['action_id']) === false) {
            $this->EE->db->select('action_id');
            $this->EE->db->where('class', ucfirst($this->package_name));
            $this->EE->db->where('method', $method);
            $query = $this->EE->db->get('exp_actions');

            if ($query->num_rows() == 0) {
                return false;
            }

            $action_id = $query->row('action_id');
        } else {
            $action_id = $this->EE->session->cache[$this->package_name]['router_url'][$method]['action_id'];
        }

        // -----------------------------------------
        // Return FULL action URL
        // -----------------------------------------
        if ($type == 'url') {
            // Grab Site URL
            $url = $this->EE->functions->fetch_site_index(0, 0);

            if (defined('MASKED_CP') == false OR MASKED_CP == false) {
                // Replace site url domain with current working domain
                $server_host = (isset($_SERVER['HTTP_HOST']) == true && $_SERVER['HTTP_HOST'] != false) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
                $url = preg_replace('#http\://(([\w][\w\-\.]*)\.)?([\w][\w\-]+)(\.([\w][\w\.]*))?\/#', "http://{$server_host}/", $url);
            }

             // Create new URL
            $ajax_url = $url.QUERY_MARKER.'ACT=' . $action_id;

            // Config Overrife for action URLs?
            $config = $this->EE->config->item('credits');
            $over = isset($config['action_url']) ? $config['action_url'] : array();

            if (is_array($over) === true && isset($over[$method]) === true) {
                $url = $over[$method];
            }

            // Protocol Relative URL
            $ajax_url = str_replace(array('https://', 'http://'), '//', $ajax_url);

            return $ajax_url;
        }

        return $action_id;
    }

    // ********************************************************************************* //

    /**
     * Grab File Module Settings
     * @return array
     */
    public function grab_settings($site_id=FALSE)
    {
        $settings = array();

        if (isset($this->EE->session->cache['editor']['module_settings']) == TRUE)
        {
            $settings = $this->EE->session->cache['editor']['module_settings'];
        }
        else
        {
            $this->EE->db->select('settings');
            $this->EE->db->where('module_name', 'Editor');
            $query = $this->EE->db->get('exp_modules');
            if ($query->num_rows() > 0) $settings = unserialize($query->row('settings'));
            if ($settings == FALSE) $settings = array();
        }

        $conf = $this->EE->config->item('updater_module_defaults');
        $override_conf = $this->EE->config->item('editor');
        if (is_array($override_conf) == FALSE) $override_conf = array();

        $settings = $this->array_extend($conf, $settings);

        if (!empty($override_conf)) $settings = $this->array_extend($settings, $override_conf);

        $this->EE->session->cache['editor']['module_settings'] = $settings;

        return $settings;
    }

    // ********************************************************************************* //

    /**
     * Grab File Module Settings
     * @return array
     */
    public function grab_extension_settings($site_id=FALSE)
    {
        $settings = array();

        if (isset($this->EE->session->cache['editor']['ext_settings']) == TRUE)
        {
            $settings = $this->EE->session->cache['editor']['module_settings'];
        }
        else
        {
            $this->EE->db->select('settings');
            $this->EE->db->where('class', 'Editor_ext');
            $query = $this->EE->db->get('exp_extensions');
            if ($query->num_rows() > 0) $settings = unserialize($query->row('settings'));
        }

        $this->EE->session->cache['editor']['ext_settings'] = $settings;

        if ($site_id)
        {
            $settings = isset($settings['site:'.$site_id]) ? $settings['site:'.$site_id] : array();
        }

        return $settings;
    }

    // ********************************************************************************* //

    public function generate_json($obj)
    {
        if (function_exists('json_encode') === FALSE)
        {
            if (class_exists('Services_JSON') === FALSE) include 'JSON.php';
            $JSON = new Services_JSON();
            return $JSON->encode($obj);
        }
        else
        {
            return json_encode($obj);
        }
    }

    // ********************************************************************************* //

    public function decode_json($obj)
    {
        if (function_exists('json_decode') === FALSE)
        {
            if (class_exists('Services_JSON') === FALSE) include 'JSON.php';
            $JSON = new Services_JSON();
            return $JSON->decode($obj);
        }
        else
        {
            return json_decode($obj);
        }
    }

    // ********************************************************************************* //

    public function recurse_copy($src,$dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) )
        {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir( $src . '/' . $file ) ) {
                    $this->recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    // ********************************************************************************* //

    /**
     * Array Extend
     * "Extend" recursively array $a with array $b values (no deletion in $a, just added and updated values)
     * @param array $a
     * @param array $b
     */
    public function array_extend($a, $b) {
        foreach($b as $k=>$v) {

            if( is_array($v) ) {

                if( !isset($a[$k]) ) {
                    $a[$k] = $v;
                } else {
                    $a[$k] = $this->array_extend($a[$k], $v);
                }
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    // ********************************************************************************* //

    /**
     * Fetch URL with file_get_contents or with CURL
     *
     * @param string $url
     * @return mixed
     */
    function fetch_url_file($url, $user=false, $pass=false)
    {
        $data = '';

        /** --------------------------------------------
        /**  file_get_contents()
        /** --------------------------------------------*/

        if ((bool) @ini_get('allow_url_fopen') !== FALSE && $user == FALSE)
        {
            if ($data = @file_get_contents($url))
            {
                return $data;
            }
        }

        /** --------------------------------------------
        /**  cURL
        /** --------------------------------------------*/

        if (function_exists('curl_init') === TRUE && ($ch = @curl_init()) !== FALSE)
        {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.5) Gecko/2008120122 Firefox/3.0.5 (.NET CLR 3.5.30729)');

            if ($user != FALSE)
            {
                curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
                if (defined('CURLOPT_HTTPAUTH')) curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            $data = curl_exec($ch);
            curl_close($ch);

            if ($data !== FALSE)
            {
                return $data;
            }
        }

        /** --------------------------------------------
        /**  fsockopen() - Last but only slightly least...
        /** --------------------------------------------*/

        $parts  = parse_url($url);
        $host   = $parts['host'];
        $path   = (!isset($parts['path'])) ? '/' : $parts['path'];
        $port   = ($parts['scheme'] == "https") ? '443' : '80';
        $ssl    = ($parts['scheme'] == "https") ? 'ssl://' : '';

        if (isset($parts['query']) && $parts['query'] != '')
        {
            $path .= '?'.$parts['query'];
        }

        $fp = @fsockopen($ssl.$host, $port, $error_num, $error_str, 7);

        if (is_resource($fp))
        {
            fputs ($fp, "GET ".$path." HTTP/1.0\r\n" );
            fputs ($fp, "Host: ".$host . "\r\n" );
            fputs ($fp, "User-Agent: Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.2.1)\r\n");

            if ($user != FALSE)
            {
                fputs ($fp, "Authorization: Basic ".base64_encode("$user:$pass")."\r\n");
            }

            fputs ($fp, "Connection: close\r\n\r\n");

            $header = '';
            $body   = '';

            /* ------------------------------
            /*  This error suppression has to do with a PHP bug involving
            /*  SSL connections: http://bugs.php.net/bug.php?id=23220
            /* ------------------------------*/

            $old_level = error_reporting(0);

            /*
            while ( ! feof($fp))
            {
                $data .= trim(fgets($fp, 128));
            }
            */

            // put the header in variable $header
            do // loop until the end of the header
            {
                $header .= fgets ( $fp, 128 );

            } while ( strpos ( $header, "\r\n\r\n" ) === false );

            // now put the body in variable $body
            while ( ! feof ( $fp ) )
            {
                $body .= fgets ( $fp, 128 );
            }

            error_reporting($old_level);

            $data = $body;

            fclose($fp);
        }

        return $data;
    }

    // ********************************************************************************* //

    public function encrypt_string($string)
    {
        $this->EE->load->library('encrypt');
        if (function_exists('mcrypt_encrypt')) $this->EE->encrypt->set_cipher(MCRYPT_BLOWFISH);

        $string = $this->EE->encrypt->encode($string, substr(sha1(base64_encode($this->ekey)),0, 56));

        // Set it back
        if (function_exists('mcrypt_encrypt')) $this->EE->encrypt->set_cipher(MCRYPT_RIJNDAEL_256);

        return $string;
    }

    // ********************************************************************************* //

    public function decrypt_string($string)
    {
        $this->EE->load->library('encrypt');
        if (function_exists('mcrypt_decrypt')) $this->EE->encrypt->set_cipher(MCRYPT_BLOWFISH);

        $string = $this->EE->encrypt->decode($string, substr(sha1(base64_encode($this->ekey)),0, 56));

        // Set it back
        if (function_exists('mcrypt_encrypt')) $this->EE->encrypt->set_cipher(MCRYPT_RIJNDAEL_256);

        return $string;
    }

    // ********************************************************************************* //

    public function getThemeUrl($root=false)
    {
        if (defined('URL_THIRD_THEMES') === true) {
            $theme_url = URL_THIRD_THEMES;
        } else {
            $theme_url = $this->EE->config->item('theme_folder_url').'third_party/';
        }

        $theme_url = str_replace(array('http://','https://'), '//', $theme_url);

        if ($root) return $theme_url;

        $theme_url .= $this->package_name . '/';

        return $theme_url;
    }

    // ********************************************************************************* //

    public function addMcpAssets($type='', $path='', $package='', $name='', $iecond=false)
    {
        $theme_url = $this->getThemeUrl();
        $url = $this->getThemeUrl() . $path;

        $prefix = ($iecond) ? "<!--[if {$iecond}]>" : '';
        $suffix = ($iecond) ? '<![endif]-->' : '';

        // CSS
        if ($type == 'css') {
            if (isset($this->EE->session->cache['css'][$package][$name]) === false) {
                $this->EE->cp->add_to_head($prefix.'<link rel="stylesheet" href="' . $url . '" type="text/css" media="print, projection, screen" />'.$suffix);
                $this->EE->session->cache['css'][$package][$name] = true;
            }
        }

        // JS
        if ($type == 'js') {
            if (isset($this->EE->session->cache['javascript'][$package][$name]) === false) {
                $this->EE->cp->add_to_foot($prefix.'<script src="' . $url . '" type="text/javascript"></script>'.$suffix);
                $this->EE->session->cache['javascript'][$package][$name] = true;
            }
        }

        // Custom
        if ($type == 'custom') {
            $path = str_replace('{theme_url}', $theme_url, $path);
            $this->EE->cp->add_to_foot($path);
        }

        // Global Inline Javascript
        if ($type == 'gjs') {
            if ( isset($this->EE->session->cache['inline_js'][$this->package_name]) == false ) {

                $ACT_url = $this->getRouterUrl('url');

                /*
                if (isset($this->EE->updater->settings['action_url']['actionGeneralRouter']) === true && $this->EE->updater->settings['action_url']['actionGeneralRouter'] != false) {
                    $ACT_url = $this->EE->updater->settings['action_url']['actionGeneralRouter'];
                }*/

                // Remove those AMP!!!
                $ACT_url = str_replace('&amp;', '&', $ACT_url);
                $theme_url = str_replace('&amp;', '&', $theme_url);

                $js = " var Editor = Editor ? Editor : {};
                        Editor.ACT_URL = '{$ACT_url}';
                        Editor.THEME_URL = '{$theme_url}';
                ";

                $this->EE->cp->add_to_foot('<script type="text/javascript">' . $js . '</script>');
                $this->EE->session->cache['inline_js'][$this->package_name] = true;
            }
        }
    }

    // ********************************************************************************* //

} // END CLASS

/* End of file forms_helper.php  */
/* Location: ./system/expressionengine/third_party/forms/libraries/forms_helper.php */
