
$.extend( UI, {
    setTranslateButtonEvent: function () {},
    setFocusEvent: function () {},
    getProjectInfo: function () {}
});

function precomputeOutsourceQuotes() {} ;

var removeAssignmentData   ;

function dqfDeleteAssignment() {
    $.ajax({
        type: 'DELETE',
        url: sprintf('/api/app/dqf/jobs/%s/%s/%s/assignment/revoke',
            removeAssignmentData.jid, removeAssignmentData.pwd, removeAssignmentData.what )
    }).done( function() {
        var node = findDqfCell(removeAssignmentData.jid, removeAssignmentData.pwd, removeAssignmentData.what );
        node.find('.stat-email').text();
        node.find('.ui.cancel').hide();
    });
}

function findDqfCell(jid, pwd, page) {
    return $(sprintf('tbody.tablestats[data-jid=%s][data-pwd=%s] .stat-email-%s', jid, pwd, page ));
}

$.extend( UI, {
    dqfDeleteAssignment : dqfDeleteAssignment
});

$(function() {
    /**
     * Code for chunk and project completion
     */

    var STATUS_COMPLETED = 'completed' ;
    var STATUS_NON_COMPLETED = 'non_completed';
    var STATUS_RECOMPLETABLE = 'recompletable' ;
    var STATUS_MISSING_COMPLETED_CHUNKS = 'missing_completed_chunks' ;

    var currentStatus = null ;

    $( '.mergebtn, .splitbtn' ).removeClass( 'disabled' );

    function clickUndo(e) {
        var jid = $(e.target).closest('tbody').data('jid'),
            password = $(e.target).closest('tbody').data('pwd'),
            id_event = $(e.currentTarget).closest('a').data('event_id') ;

        $.ajax({
            type: 'DELETE',
            url: sprintf('/api/app/jobs/%s/%s/completion-events/%s', jid, password, id_event )
        }).done( function() {

            var cell = $(e.currentTarget).closest('td');
            $(e.currentTarget).remove();
            element = $('<span class="job-undoComplete-label">Not Completed yet</span>') ;
            cell.empty();
            cell.append(element);

            // reload status data from server
            reloadStatusFromServer()
                .done(function() {
                    loadChunkCompletionData().done( enableOrDisableSplitAndMerge )
                }) ;
        });
    }

    function drawButtonsByChunkCompletionData( data ) {
        data1 = data ;
        _.each( data.project_status.translate, function( item ) {

            var selector = sprintf( 'tbody[data-jid=%s][data-pwd=%s]', item.id, item.password ),
                cell = $( selector ).find( 'tr:first td.undoCompleteBtnContainer' ) ,
                element = null ;

            if ( item.completed ) {
                element = $('<div><a data-event_id="'+ item.event_id +'" href="#" class="standardbtn undoCompleteBtn">Undo Complete</a><span class="job-completed-label">' + sprintf(' Completed on %s', moment(  item.completed_at ).format('LLL') )+ '</span></div>') ;
                cell.addClass("completed");

                element.find('.undoCompleteBtn').on('click', clickUndo);
            }

            else {
                element = $('<span class="job-undoComplete-label">Not completed yet</span>') ;
            }

            cell.append( element ) ;
        });
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

    function chunkDataLoaded( data ) {
        drawButtonsByChunkCompletionData( data ) ;
        enableOrDisableSplitAndMerge( data );
        return ( new $.Deferred() ).resolve( data ) ;
    }

    function enableOrDisableButtons() {
        if (currentStatus === STATUS_COMPLETED) {
            $('.completeProjectButton').addClass('disabled');
            $('.undoCompleteBtn').addClass('disabled');
        } else if ( currentStatus === STATUS_NON_COMPLETED || currentStatus === STATUS_RECOMPLETABLE ) {
            $('.completeProjectButton').removeClass('disabled');
        } else {
            $('.completeProjectButton').addClass('disabled');
        }
    }

    /**
     *
     * @param data
     */
    function enableOrDisableSplitAndMerge( data ) {
        console.log( 'enableOrDisableSplitAndMerge', data ) ;

        var completed_translate = _.reject( data.project_status.translate, function( item ) {
            return item.completed_at === null ;
        });

        if ( completed_translate.length > 0 ) {

            $('.domerge').hide();
            $('.splitbtn-cont').hide();
        }
        else {
            if (data.project_status.translate.length > 1 ) $('.domerge').show();
            else $('.splitbtn-cont').show();
        }
    }

    function statusChanged() {
        displayMessage() ;
        enableOrDisableButtons() ;
    }

    function completeProjectConfirmed() {
        var path = sprintf('/plugins/ebay/projects/%s/%s/completion', config.id_project, config.password ) ;

        $.post( path, {} )
            .done( function(dat) {
                $('.undoCompleteBtn').addClass('disabled');
                $('.completeProjectButton').addClass('disabled');

                currentStatus = STATUS_COMPLETED ;
                completionDate = new Date();

                statusChanged() ;
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

                statusChanged();
            });
    };

    function loadChunkCompletionData() {
        return $.get('/api/v2/projects/' + config.id_project + '/' + config.password + '/completion_status' ) ;
    }

    loadChunkCompletionData()
        .done( chunkDataLoaded )
        .done( reloadStatusFromServer ) ;

    function activateUserCell(node, email) {
        $(node).find('.stat-email ').text( email );
        $(node).find('.ui.cancel.label').show();
    }

    $.get( sprintf('/api/app/dqf/projects/%s/%s/assignments', config.id_project, config.password ), {})
        .done( function( data ) {
            $.each( data, function( index, element ) {
                var email, node ;

                if ( element.translate_user ) {
                    email = element.translate_user.email ;
                    node = findDqfCell( element.id, element.password, 'translate' ) ;
                    activateUserCell(node, email);

                }

                if ( element.review_user ) {
                    email = element.review_user.email ;
                    node = findDqfCell( element.id, element.password, 'revise' ) ;
                    activateUserCell(node, email) ;
                }
            });
        });

    function confirmRemoveAssignment(event) {
        var jid  = $(event.target).closest('tbody').data('jid');
        var pwd  = $(event.target).closest('tbody').data('pwd');
        var what = $(event.target).closest('td').hasClass('stat-email-translate') ? 'translate' : 'revise' ;
        var email = $(event.target).closest('td').text ().trim() ;

        removeAssignmentData = { jid  : jid, pwd : pwd, what : what };

        APP.confirm({
            cancelTxt : 'Cancel',
            okTxt : 'Yes, remove assignment',
            callback : 'dqfDeleteAssignment',
            msg : 'Are you sure you want to remove DQF assignment to ' + email + ' for ' + what + ' on chunk ' + jid + ' and password ' + pwd + '?'
        });
    }

    $('.stat-email-translate .cancel, .stat-email-revise .cancel').on('click', confirmRemoveAssignment) ;

});

function createDqfProject() {
    if ( $('#createIntermediateProjectButton').hasClass('disabled') ) {
        return ;
    }

    $('.dqf-info .loader').show();

    $('#createIntermediateProjectButton').addClass('disabled');

    return $.post('/plugins/ebay/api/app/projects/' + config.id_project + '/' + config.password + '/dqf_intermediate_project' )
        .done( function(data) {
            window.location.href = window.location.href ;
            console.log( data ) ;
        })
        .error( function(data) {
            console.error( data ) ;
        });
}
