(function($, global) {

	function display(element) {
		//console.log('display');
		var config = Editor.contentElementsConfig[element.find('.redactor_editor').data('config_key')];
		element.find('.redactor_editor').redactor(config);
	}

	function remove() {
		//console.log('remove');
	}

	function beforeSort() {
		//console.log('beforeSort');
	}

	function afterSort() {
		//console.log('afterSort');
	}

	jQuery(document).ready(function(){
		$('.content_elements_wrapper .redactor_editor').each(function(i, elem){
			display($(elem).parent());
		});
	});

	global.ContentElements.bind('editor', 'display', display);
	//global.ContentElements.bind('editor', 'remove', remove);
	//global.ContentElements.bind('editor', 'beforeSort', beforeSort);
	//global.ContentElements.bind('editor', 'afterSort', afterSort);


})(jQuery, window);