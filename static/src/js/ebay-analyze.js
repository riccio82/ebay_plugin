
$.extend( UI, {
    setTranslateButtonEvent: function () {},
    setFocusEvent: function () {},
    getProjectInfo: function () {}
});

function precomputeOutsourceQuotes() {} ;




$(function() {

    var uncompletableBecauseAlreadyCompleted = 1 ;
    var uncompletableForMissingCompletedChunks = 2 ;
    var completableForMissingCompletionDate = 3 ;
    var completableBecauseRecompledChunks = 4 ;

    currentStatus = null ;

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
                element = $('<a href="#" class="standardbtn undoCompleteBtn">Undo</a>')
                    .data('powertip', sprintf(' Completed on %s', moment(  item.completed_at ).format('LLL') ) )
                    .data('event_id', item.event_id)
                    .on('click', clickUndo )
                    .powerTip()

            }

            else {
                element = $('<a href="#" class="standardbtn undoCompleteBtn disabled">waiting</a>') ;
            }

            cell.append( element ) ;
        });
    }


    function setCurrentStatus( data ) {
        var completedChunks = _.filter( data.project_status.translate, function( item ) {
            return item.completed_at != null ;
        });

        if ( completedChunks.length != data.project_status.translate.length ) {
            currentStatus = uncompletableForMissingCompletedChunks ;
        }

        if ( config.project_completion_timestamp === null ) {
            currentStatus = completableForMissingCompletionDate ;
        }

        mostRecentChunkCompletion = _.map( completedChunks, function( item ) {
            return moment( item.completed_at ).format('x');
        }).sort().reverse()[ 0 ] ;

        if (
            moment( mostRecentChunkCompletion * 1000 ).format('x') >
            moment( config.project_completion_timestamp * 1000 ).format('x')
        ) {
            currentStatus = completableBecauseRecompledChunks ;
        }
        else {
            currentStatus = uncompletableBecauseAlreadyCompleted ;
        }
    }

    function enableProjectCompletionButtonByData( ) {
        if ( currentStatus == completableBecauseRecompledChunks || currentStatus == completableForMissingCompletionDate ) {
            $('.completeProjectButton').removeClass('disabled');
        }
    }

    function getExplanatoryText() {

        switch( currentStatus ) {
            case completableBecauseRecompledChunks :
                return 'All chunks completed, you can now complete this project' ;
            case completableForMissingCompletionDate :
                return 'All chunks completed, you can now complete this project' ;
            case uncompletableBecauseAlreadyCompleted :
                return 'This project was already completed' ;
            case uncompletableForMissingCompletedChunks :
                return 'Not all chunks have been marked as complete yet.' ;
            default :
                throw 'invalid value for currentStatus' ;
        }
    }

    function displayMessage() {
        $('.completableStatus').text( getExplanatoryText()  );
    }

    function dataLoaded( data ) {
        setCurrentStatus( data )

        drawButtonsByData( data ) ;
        enableProjectCompletionButtonByData(  ) ;
        displayMessage() ;
    }

    $(document).on('click', '.completeProjectButton', function(e) {
        e.preventDefault();
        if ( $(e.target).hasClass('disabled') ) return ;

        var path = sprintf('/plugins/ebay/projects/%s/%s/completion', config.id_project, config.password ) ;
        $.post( path, {} ).done( function() {

            $('.undoCompleteBtn').addClass('disabled');
            $(e.target).addClass('disabled');
        });
    });

    $.get('/api/v2/projects/' + config.id_project + '/' + config.password + '/completion_status')
        .done( dataLoaded  );

});