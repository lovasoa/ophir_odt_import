(function ($) {

  Drupal.behaviors.ophir_odt_import = {
    attach: function (context, settings) {
    $("#edit-importfile").change(function(){
    	if($("#edit-importfile").val().substr(-3) != "odt" && !confirm('The file you choosed does not seem to be an ODT file. Do you want to continue?')){
    		$("#edit-importfile").val()="";
    		return false;
    	}
    	if ($("#edit-body").val()!="" && !confirm('You have chosen to import an ODT file. This will overwrite the content body. Do you want to continue ?')){
    		$("#edit-importfile").val()="";
    		return false;
    	}
    });
    }
  };

})(jQuery);
