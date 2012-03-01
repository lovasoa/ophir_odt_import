(function ($) {

  Drupal.behaviors.ophir_odt_import = {
    attach: function (context, settings) {
    	$("a[class='odt-footnote-anchor']").each(function(){
    		$(this).click(function(){
    		  href_parts = this.href.split("#");
    		  $('#'+href_parts[href_parts.length-1]).dialog({ modal: true, show: 'slide', width: 500});
    		  return false;
    		});
    	});
    }
  };

})(jQuery);
