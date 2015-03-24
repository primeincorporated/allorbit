jQuery(function($){
	"use strict";

	var Solspace = window.Solspace = window.Solspace || {};

	if (typeof Solspace.message !== 'undefined' &&
		Solspace.message !== '')
	{
		$.ee_notice(Solspace.message,{open: true, type:"success"});
		setTimeout(function(){ $.ee_notice.destroy(); }, 3000);
	}

	//magic checkboxes
	$('table.magic_checkbox_table, ' +
		'table.magicCheckboxTable, '  +
		'table.cb_toggle'
	).each(function(){
		var $table		= $(this),
			$magicCB	= $table.find(
				'input[type=checkbox][name=toggle_all_checkboxes]'
			);

		$magicCB.each(function(){
			var $that		= $(this),
				colNum		= $that.parent().index();

			$that.click(function(){
				var checked = ($that.is(':checked')) ? 'checked' : false;

				$table.find('tr').find(
					'th:eq(' + colNum + ') input[type=checkbox], ' +
					'td:eq(' + colNum + ') input[type=checkbox]'
				).attr('checked', checked);
			});
		});
	});

	// Ajax Search

	$("form#favorites_search select").change(function(){

		var theAction = $("form#favorites_search").attr('action');
		var theData = $("form#favorites_search").serialize();

		$.ajax({
					type:     "POST",
					url: theAction,
					data: theData,
					success: function(results){
							$("div#favorites-results div.inner").html(results);
						},
					error: function(results){
							console.log("ERROR" + results);
							$("div#favorites-results div.inner").html(results);
					}
		});
	});

	/**
	 * Collections
	 */

	if($("select.collection_cp_action").val() == 'delete')
	{
		$("#favorites-results").find("table tr.default td input[type=checkbox]").attr("disabled", true).hide();
	}

	$("select.collection_cp_action").change(function(){

		var selectedOption = $(this).val();
		var theCheckbox = $("#favorites-results").find("table tr.default td input[type=checkbox]");

		if(selectedOption == 'delete')
		{
			theCheckbox.attr("disabled", true).hide();
		}
		else
		{
			theCheckbox.attr("disabled", false).show();
		}
	});
});