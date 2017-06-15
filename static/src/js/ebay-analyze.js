
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

    $(document).on( 'click', '.undoCompleteBtn', function(e) {
        var jid = $(e.target).closest('tbody').data('jid');
        var pwd = $(e.target).closest('tbody').data('pwd');
    });

    // load project completion info based on current id and password

    $.get('/api/v2/projects/' + config.id_project + '/' + config.password + '/completion_status')
        .done( function( data ) {
            _.each( data.project_status.translate, function( item ) {
                data1 = data ;
                // find tbody
                var selector = sprintf( 'tbody[data-jid=%s][data-pwd=%s]', item.id, item.password );
                var cell = $( selector ).find( 'tr:first td:last') ;
                var element = null ;

                if ( item.completed ) {
                    element = $('<a href="#" class="uploadbtn translate undoCompleteBtn">Undo complete</a>')
                        .data('powertip', sprintf(' Completed on %s', moment(  item.completed_at ).format('LLL') ) )
                        .data('event_id', item.event_id)
                        .on('click', clickUndo )
                        .powerTip()
                }

                else {
                    element = $('');
                }

                cell.append( element ) ;

                if ( item.completed ) {
                    element;
                }
            });
        });
});