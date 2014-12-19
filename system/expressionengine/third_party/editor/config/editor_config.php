<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (isset($this->EE) == FALSE) $this->EE =& get_instance(); // For EE 2.2.0+

$config['editor_defaults']['editor_settings'] = 'predefined';
$config['editor_defaults']['editor_conf'] = '';
$config['editor_defaults']['height'] = '200';
$config['editor_defaults']['direction'] = 'ltr';
$config['editor_defaults']['toolbar'] = 'yes';
$config['editor_defaults']['source'] = 'yes';
$config['editor_defaults']['focus'] = 'no';
$config['editor_defaults']['autoresize'] = 'yes';
$config['editor_defaults']['fixed'] = 'no';
$config['editor_defaults']['convertlinks'] = 'yes';
$config['editor_defaults']['convertdivs'] = 'yes';
$config['editor_defaults']['overlay'] = 'yes';
$config['editor_defaults']['observeimages'] = 'yes';
$config['editor_defaults']['shortcuts'] = 'yes';
$config['editor_defaults']['linebreaks'] = 'no';
$config['editor_defaults']['air'] = 'no';
$config['editor_defaults']['wym'] = 'no';
$config['editor_defaults']['remove_empty_tags'] = 'yes';
$config['editor_defaults']['protocol'] = 'yes';
$config['editor_defaults']['allowedtags_option'] = 'default';
$config['editor_defaults']['allowedtags'] = array();
$config['editor_defaults']['deniedtags_option'] = 'default';
$config['editor_defaults']['deniedtags'] = array();
$config['editor_defaults']['formattingtags'] = array('p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4');
$config['editor_defaults']['language'] = 'en';
$config['editor_defaults']['css_file'] = '';
$config['editor_defaults']['callbacks']['init'] = '';
$config['editor_defaults']['callbacks']['enter'] = '';
$config['editor_defaults']['callbacks']['change'] = '';
$config['editor_defaults']['callbacks']['pasteBefore'] = '';
$config['editor_defaults']['callbacks']['pasteAfter'] = '';
$config['editor_defaults']['callbacks']['focus'] = '';
$config['editor_defaults']['callbacks']['blur'] = '';
$config['editor_defaults']['callbacks']['keyup'] = '';
$config['editor_defaults']['callbacks']['keydown'] = '';
$config['editor_defaults']['callbacks']['textareaKeydown'] = '';
$config['editor_defaults']['callbacks']['syncBefore'] = '';
$config['editor_defaults']['callbacks']['syncAfter'] = '';
$config['editor_defaults']['callbacks']['autosave'] = '';
$config['editor_defaults']['callbacks']['imageUpload'] = '';
$config['editor_defaults']['callbacks']['imageUploadError'] = '';
$config['editor_defaults']['callbacks']['fileUpload'] = '';
$config['editor_defaults']['callbacks']['fileUploadError'] = '';
$config['editor_defaults']['buttons'] = array();
$config['editor_defaults']['plugins'] = array();

$config['editor_defaults']['upload_service'] = 'local';
$config['editor_defaults']['file_upload_location'] = '';
$config['editor_defaults']['image_upload_location'] = '';
$config['editor_defaults']['image_browsing'] = 'yes';
$config['editor_defaults']['image_subdir'] = 'yes';

$config['editor_defaults']['s3']['file']['bucket'] = '';
$config['editor_defaults']['s3']['image']['bucket'] = '';
$config['editor_defaults']['s3']['image']['endpoint'] = 's3.amazonaws.com';
$config['editor_defaults']['s3']['aws_access_key'] = '';
$config['editor_defaults']['s3']['aws_secret_key'] = '';

$config['editor_default_buttons'] = array(
'html',
'formatting',
'bold',
'italic',
'deleted',
'unorderedlist',
'orderedlist',
'outdent',
'indent',
'image',
'video',
'file',
'table',
'link',
'alignment',
'horizontalrule',
'underline',
'alignleft',
'aligncenter',
'alignright',
'alignjustify', // 'justify??'
);


/*air
airButtons
allowedTags
autoresize
autosave
autosaveCallback
autosaveInterval
boldTag
buttons
buttonsHideOnMobile
buttonSource
cleanFontTag
cleanSpaces
cleanup
clipboardUploadUrl
convertDivs
convertImageLinks
convertLinks
convertVideoLinks
css
deniedTags
direction
dragUpload
fileUpload
fileUploadCallback
fileUploadErrorCallback
focus
formattingPre
formattingTags
fullpage
iframe
imageFloatMargin
imageGetJson
imageTabLink
imageUpload
imageUploadCallback
imageUploadErrorCallback
fileUploadParam
italicTag
lang
linebreaks
linkNofollow
linkProtocol
linkSize
maxHeight
minHeight
mobile
modalOverlay
observeImages
observeLinks
paragraphy
pastePlainText
phpTags
placeholder
removeEmptyTags
s3
shortcuts
tabFocus
tabindex
tabSpaces
tidyHtml
toolbar
toolbarExternal
toolbarFixed
toolbarFixedBox
toolbarFixedTarget
toolbarFixedTopOffset
toolbarOverflow
uploadFields
visual
wym
xhtml
*/