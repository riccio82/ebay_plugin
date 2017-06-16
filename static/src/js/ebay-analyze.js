
$.extend( UI, {
    setTranslateButtonEvent: function () {},
    setFocusEvent: function () {},
    getProjectInfo: function () {}
});

function precomputeOutsourceQuotes() {} ;

$(function() {
    $( '.mergebtn, .splitbtn' ).removeClass( 'disabled' );

    function clickUndo(e) {
        var jid = $(e.target).closest('tbody').data('jid'),
            password = $(e.target).closest('tbody').data('pwd');

        $.ajax({
            type: 'DELETE',
            url: sprintf('/api/app/jobs/%s/%s/completion-events/%s',
                jid, password, $(e.target).data('event_id') )
        }).done( function() {
            $(e.target).remove();
        });
    }

    function drawButtonsByData( data ) {
        data1 = data ;
        _.each( data.project_status.translate, function( item ) {

            var selector = sprintf( 'tbody[data-jid=%s][data-pwd=%s]', item.id, item.password ),
                cell = $( selector ).find( 'tr:first td:last' ) ,
                element = null ;

            if ( item.completed ) {
                element = $('<a href="#" class="standardbtn undoCompleteBtn">Undo complete</a>')
                    .data('powertip', sprintf(' Completed on %s', moment(  item.completed_at ).format('LLL') ) )
                    .data('event_id', item.event_id)
                    .on('click', clickUndo )
                    .powerTip()

            }

            else {
                element = $('<a href="#" class="standardbtn undoCompleteBtn disabled">Undo complete</a>') ;
            }

            cell.append( element ) ;
        });
    }

    function shouldButtonBeEnalbed( data ) {
        var completedChunks = _.filter( data.project_status.translate, function( item ) {
            return item.completed_at != null ;
        });

        if ( completedChunks.length != data.project_status.translate.length ) {
            return false ;
        }

        if ( config.project_completion_timestamp === null ) {
            return true ;
        }

        mostRecentChunkCompletion = _.map( completedChunks, function( item ) {
            return moment( item.completed_at ).format('x');
        }).sort().reverse()[ 0 ] ;

        if (
            moment( mostRecentChunkCompletion * 1000 ).format('x') >
            moment( config.project_completion_timestamp * 1000 ).format('x')
        ) {
            return true ;
        }
        else {
            return false ;
        }
    }

    function enableProjectCompletionButtonByData( data ) {
        if ( shouldButtonBeEnalbed( data ) ) {
            $('.completeProjectButton').removeClass('disabled');
        }
    }

    function dataLoaded( data ) {
        drawButtonsByData( data ) ;
        enableProjectCompletionButtonByData( data ) ;
    }

    $(document).on('click', '.completeProjectButton', function(e) {
        e.preventDefault();
        if ( $(e.target).hasClass('disabled') ) return ;

        var path = sprintf('/plugins/ebay/projects/%s/%s/completion', config.id_project, config.password ) ;
        $.post( path, {} ).done( function() {

            disableButtons() ;
            $('.undoCompleteBtn').addClass('disabled');
            $(e.target).addClass('disabled');
        });
    });

    $.get('/api/v2/projects/' + config.id_project + '/' + config.password + '/completion_status')
        .done( dataLoaded  );

});