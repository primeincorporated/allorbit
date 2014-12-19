this["Editor"] = this["Editor"] || {};
this["Editor"]["Templates"] = this["Editor"]["Templates"] || {};

this["Editor"]["Templates"]["plugins_link"] = Handlebars.template(function (Handlebars,depth0,helpers,partials,data) {
  this.compilerInfo = [4,'>= 1.0.0'];
helpers = this.merge(helpers, Handlebars.helpers); data = data || {};
  var buffer = "", stack1, helper, self=this, functionType="function", escapeExpression=this.escapeExpression;

function program1(depth0,data) {
  
  
  return "<a href=\"#\" id=\"redactor-tab-control-2\">Site Page</a>";
  }

function program3(depth0,data) {
  
  
  return "<a href=\"#\" id=\"redactor-tab-control-3\">NavEE</a>";
  }

  buffer += "<section id=\"redactor-modal-link-insert\">\n    <input type=\"hidden\" id=\"redactor_tab_selected\" value=\"1\">\n\n    <div id=\"redactor_tabs\">\n        <a href=\"#\" id=\"redactor-tab-control-1\" class=\"redactor_tabs_act\">URL</a>\n        <a href=\"#\" id=\"redactor-tab-control-4\">Email</a>\n        ";
  stack1 = helpers['if'].call(depth0, (depth0 && depth0.showSitePages), {hash:{},inverse:self.noop,fn:self.program(1, program1, data),data:data});
  if(stack1 || stack1 === 0) { buffer += stack1; }
  buffer += "\n        ";
  stack1 = helpers['if'].call(depth0, (depth0 && depth0.showNavee), {hash:{},inverse:self.noop,fn:self.program(3, program3, data),data:data});
  if(stack1 || stack1 === 0) { buffer += stack1; }
  buffer += "\n    </div>\n\n    <div class=\"redactor_tab tab-url\" id=\"redactor_tab1\">\n        <label>URL</label>\n        <input type=\"text\" class=\"redactor_input redactor_link\">\n\n        <label>Text</label>\n        <input type=\"text\" class=\"redactor_input redactor_link_text\">\n\n        <label>\n            <input type=\"checkbox\" class=\"redactor_link_blank\">&nbsp;&nbsp;Open link in new tab\n        </label>\n    </div>\n\n    <div class=\"redactor_tab tab-email\" id=\"redactor_tab2\" style=\"display:none\">\n        <label>Email</label>\n        <input type=\"text\" class=\"redactor_input redactor_link\">\n\n        <label>Text</label>\n        <input type=\"text\" class=\"redactor_input redactor_link_text\">\n    </div>\n\n    <div class=\"redactor_tab tab-site_pages\" id=\"redactor_tab3\" style=\"display:none\">\n        <label>Site Page</label>\n        <select id=\"redactor_site_page\" style=\"width:100%\" class=\"redactor_link\">";
  if (helper = helpers.sitePages) { stack1 = helper.call(depth0, {hash:{},data:data}); }
  else { helper = (depth0 && depth0.sitePages); stack1 = typeof helper === functionType ? helper.call(depth0, {hash:{},data:data}) : helper; }
  if(stack1 || stack1 === 0) { buffer += stack1; }
  buffer += "</select>\n\n        <label>Text</label>\n        <input type=\"text\" class=\"redactor_input redactor_link_text\">\n\n        <label>\n            <input type=\"checkbox\" class=\"redactor_link_blank\">&nbsp;&nbsp;Open link in new tab\n        </label>\n    </div>\n\n    <div class=\"redactor_tab tab-navee\" id=\"redactor_tab3\" style=\"display:none\">\n        <label>Link Item</label>\n        <select id=\"redactor_navee\" style=\"width:100%\" class=\"redactor_link\">";
  if (helper = helpers.navee) { stack1 = helper.call(depth0, {hash:{},data:data}); }
  else { helper = (depth0 && depth0.navee); stack1 = typeof helper === functionType ? helper.call(depth0, {hash:{},data:data}) : helper; }
  buffer += escapeExpression(stack1)
    + "</select>\n\n        <label>Text</label>\n        <input type=\"text\"  class=\"redactor_input redactor_link_text\">\n\n        <label>\n            <input type=\"checkbox\" class=\"redactor_link_blank\">&nbsp;&nbsp;Open link in new tab\n        </label>\n    </div>\n\n\n\n</section>\n\n<footer>\n    <button class=\"redactor_modal_btn redactor_btn_modal_close\">Cancel</button><!-- INLINE WHITESPACE DO NOT REMOVE\n --><button class=\"redactor_modal_btn redactor_modal_action_btn\">Insert</button>\n</footer>";
  return buffer;
  });

this["Editor"]["Templates"]["plugins_paste_plain"] = Handlebars.template(function (Handlebars,depth0,helpers,partials,data) {
  this.compilerInfo = [4,'>= 1.0.0'];
helpers = this.merge(helpers, Handlebars.helpers); data = data || {};
  


  return "<section id=\"redactor-modal-paste_plain_text\">\n    <form>\n        <textarea id=\"redactor_paste_plaintext_area\" style=\"height:400px; width:99%\"></textarea>\n    </form>\n</section>\n\n<footer>\n    <button class=\"redactor_modal_btn redactor_btn_modal_close\">Cancel</button><!-- INLINE WHITESPACE DO NOT REMOVE\n --><button class=\"redactor_modal_btn redactor_modal_action_btn\">Insert</button>\n</footer>";
  });