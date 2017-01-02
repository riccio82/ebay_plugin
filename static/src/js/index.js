/**
 *  REVISE
 *
 */
if ( config.isReview )
(function ( $, UI, undefined ) {

    $.extend( UI, {
        setProgress: function ( stats ) {

            var s = stats;
            var m = $( 'footer .meter' );

            var t_perc = s.TRANSLATED_PERC;
            var a_perc = s.APPROVED_PERC;
            var d_perc = s.DRAFT_PERC;
            var r_perc = s.REJECTED_PERC;

            var t_perc_formatted = s.TRANSLATED_PERC_FORMATTED;
            var a_perc_formatted = s.APPROVED_PERC_FORMATTED;
            var d_perc_formatted = s.DRAFT_PERC_FORMATTED;
            var r_perc_formatted = s.REJECTED_PERC_FORMATTED;

            var t_formatted = s.TODO_FORMATTED;

            var wph = s.WORDS_PER_HOUR;
            var completion = s.ESTIMATED_COMPLETION;

            if ( typeof wph == 'undefined' ) {
                $( '#stat-wph' ).hide();
            } else {
                $( '#stat-wph' ).show();
            }
            if ( typeof completion == 'undefined' ) {
                $( '#stat-completion' ).hide();
            } else {
                $( '#stat-completion' ).show();
            }

            this.progress_perc = s.PROGRESS_PERC_FORMATTED;

            this.done_percentage = this.progress_perc;

            $( '.approved-bar', m ).css( 'width', a_perc + '%' ).attr( 'title', 'Approved ' + a_perc_formatted + '%' );
            $( '.rejected-bar', m ).css( 'width', r_perc + '%' ).attr( 'title', 'Rejected ' + r_perc_formatted + '%' );

            $( '.translated-bar', m ).css( 'width', 0 + '%' ) ; // force translated to 0
            $( '.draft-bar', m ).css( 'width', 0 + '%' ); // force draft bar to 0

            $( '#stat-progress' ).html( Number((a_perc + r_perc).toFixed(2) )); // show perc of revise segments


            $( '#stat-todo strong' ).html( t_formatted );
            $( '#stat-wph strong' ).html( wph );

            $('#stat-completion strong').html(completion);
            $('#stat-eqwords').remove();
            $('#stat-todo').remove();

            var reviewedWords = $('<li>').append(
                $('<span>Reviewed words:</span>')
            ).append(
                '&nbsp;'
            ).append(
                $('<strong>').text( s.APPROVED + s.REJECTED )
            );

            $('.statistics-core').html( reviewedWords ) ;


        }
    } );

    $(function() {
        var url =  config.basepath + sprintf(
           'api/v1/jobs/%s/%s/stats', config.id_job, config.password
            );

        $.getJSON( url ).done(function(data) {
            UI.setProgress( data.stats );
        });
    });

})( jQuery, UI );


/**
 *  TRANSLATE
 */

if (!config.isReview) {

/**
 * Skipped segments.
 * Due to difficulties with count of skipped segments during propagation, here we override
 * the UI to make sure skipped segments never propagate.
 */

(function initEbayTranslateForSkippedSegments ($, UI, undefined) {

    var skipped_segment = 'XXXX' ;

    var original_shouldSegmentAutoPropagate = UI.shouldSegmentAutoPropagate;

    $.extend( UI, {
        shouldSegmentAutoPropagate : function( segment ) {
            if ( UI.getSegmentTarget( segment ).trim() == skipped_segment ) {
                return false;
            }
            else {
                return original_shouldSegmentAutoPropagate( segment );
            }
        }
    });

})(jQuery, UI);

}