/**
 * Created by fregini on 27/12/2016.
 */
(function( $, APP, undefined) {

    var original_getCreateProjectParams = APP.getCreateProjectParams ;

    $.extend( APP, {

        getCreateProjectParams : function() {
            var data = original_getCreateProjectParams();

            data.word_count = ( $('#ebay_word_count' ).length == 1) ? $('#ebay_word_count' ).val() : null ;
            data.due_date = ( $('#ebay_due_date' ).length == 1) ? $('#ebay_due_date' ).val() : null ;
            data.project_type = ( $('#ebay_project_type' ).length == 1) ? $('#ebay_project_type').val() : null ;
            data.vendor_id = ( $('#ebay_vendor_id' ).length == 1) ? $('#ebay_vendor_id' ).val() : null ;

            return data ;
        }

    })

    $(function() {
        $( "#ebay_due_date" ).datepicker();
    });
})(jQuery, APP);
