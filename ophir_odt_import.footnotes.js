(function ($) {

  Drupal.behaviors.ophir_odt_import = {
    attach: function (context, settings) {
    	$("a[class='odt-footnote-anchor']").each(function(){
    		$(this).click(function(){
    		  href_parts = this.href.split("#");
    		  dialog = {
    		  	modal: true,
    		  	show: 'slide',
    		  	hide: 'slide',
    		  	title: 'Note',
    		  	width: 500,
    		  }
    		  $('#'+href_parts[href_parts.length-1]).dialog(dialog);
    		  return false;
    		});
    	});
    }
  };

})(jQuery);
