
$.extend( UI, {
    setTranslateButtonEvent: function () {},
    setFocusEvent: function () {},
    getProjectInfo: function () {}
});

function precomputeOutsourceQuotes() {} ;

$(function() {

    var STATUS_COMPLETED = 'completed' ;
    var STATUS_NON_COMPLETED = 'non_completed';
    var STATUS_RECOMPLETABLE = 'recompletable' ;
    var STATUS_MISSING_COMPLETED_CHUNKS = 'missing_completed_chunks' ;

    currentStatus = null ;

    $( '.mergebtn, .splitbtn' ).removeClass( 'disabled' );

    function clickUndo(e) {
        var jid = $(e.target).closest('tbody').data('jid'),
            password = $(e.target).closest('tbody').data('pwd');

        $.ajax({
            type: 'DELETE',
            url: sprintf('/api/app/jobs/%s/%s/completion-events/%s',
                jid, password, $(e.currentTarget).find('a').data('event_id') )
        }).done( function() {
            var cell = $(e.currentTarget).closest('td');
            $(e.currentTarget).remove();
            element = $('<span class="job-undoComplete-label">Not Completed yet</span>') ;
            cell.append(element);
            reloadStatusFromServer() ;
        });
    }

    function drawButtonsByData( data ) {
        data1 = data ;
        _.each( data.project_status.translate, function( item ) {

            var selector = sprintf( 'tbody[data-jid=%s][data-pwd=%s]', item.id, item.password ),
                cell = $( selector ).find( 'tr:first td:last' ) ,
                element = null ;

            if ( item.completed ) {
                element = $('<div><a data-event_id="'+ item.event_id +'" href="#" class="standardbtn undoCompleteBtn">Undo Complete</a><span class="job-completed-label">' + sprintf(' Completed on %s', moment(  item.completed_at ).format('LLL') )+ '</span></div>')
                    // .data('powertip', sprintf(' Completed on %s', moment(  item.completed_at ).format('LLL') ) )
                    .on('click', clickUndo )
                    // .powerTip()
                cell.addClass("completed");
            }

            else {
                element = $('<span class="job-undoComplete-label">Not Completed yet</span>') ;
            }

            cell.append( element ) ;
        });
    }

    function enableProjectCompletionButtonByData( ) {
        if (currentStatus === STATUS_COMPLETED) {
            $('.completeProjectButton').addClass('disabled');
            $('.undoCompleteBtn').addClass('disabled');
        } else if ( currentStatus == STATUS_NON_COMPLETED || currentStatus == STATUS_RECOMPLETABLE ) {
            $('.completeProjectButton').removeClass('disabled');
        } else {
            $('.completeProjectButton').addClass('disabled');
        }
    }

    function getExplanatoryText() {

        switch( currentStatus ) {
            case STATUS_NON_COMPLETED :
                return 'All chunks completed, you can now complete this project' ;
            case STATUS_RECOMPLETABLE :
                return 'All chunks completed, you can now complete this project' ;
            case STATUS_COMPLETED :
                return 'This project was already completed on ' + moment( completionDate ).format('LLL') ;
            case STATUS_MISSING_COMPLETED_CHUNKS :
                return 'Not all chunks have been marked as complete yet.' ;
            default :
                throw 'invalid value for currentStatus' ;
        }
    }

    function displayMessage() {
        $('.completableStatus').text( getExplanatoryText()  );
    }

    function dataLoaded( data ) {
        drawButtonsByData( data ) ;
        return ( new $.Deferred() ).resolve() ;
    }

    function completeProjectConfirmed() {
        var path = sprintf('/plugins/ebay/projects/%s/%s/completion', config.id_project, config.password ) ;

        $.post( path, {} )
            .done( function(data) {
                $('.undoCompleteBtn').addClass('disabled');
                $('.completeProjectButton').addClass('disabled');

                currentStatus = STATUS_COMPLETED ;
                completionDate = new Date();
                displayMessage() ;
            }) ;
    }

    UI.completeProjectConfirmed = completeProjectConfirmed ;

    $(document).on('click', '.completeProjectButton', function(e) {
        e.preventDefault();
        if ( $(e.target).hasClass('disabled') ) return ;

        APP.confirm({
            msg: 'Are you sure you want to set the whole project as completed? This action cannot canceled.',
            callback: 'completeProjectConfirmed'
        });
    });

    function reloadStatusFromServer() {
        return $.get('/plugins/ebay/api/v1/projects/' + config.id_project + '/' + config.password + '/completion_status' )
            .done( function( data ) {
                currentStatus = data.status ;
                completionDate = data.completed_at ;

                enableProjectCompletionButtonByData(  ) ;
                displayMessage() ;
            });
    };

    function loadCompletionData() {
        return $.get('/api/v2/projects/' + config.id_project + '/' + config.password + '/completion_status' )
            .done( dataLoaded )
            .done( reloadStatusFromServer ) ;
    }

    loadCompletionData();

});