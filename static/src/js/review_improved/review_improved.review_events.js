if ( ReviewImproved.enabled() ) {
    $(document).ready(function() {
        // first step in the direction to not rely on HTML rendering.
        // we fetch quality-report data on page load to get the score
        // to show in quality-report button.
        ReviewImproved.reloadQualityReport();
    });
}

// ---------------- specific for review page

if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, RI, UI, undefined) {

    var versions = MateCat.db.segment_versions;

    $(document).on('click', 'section .textarea-container .errorTaggingArea', function(e) {
        var section = $(e.target).closest('section') ;
        var id = UI.getSegmentId(section);
        if ( section.hasClass('muted') || section.hasClass('readonly') ) {
            return ;
        }

        if ( ! section.hasClass('opened') ) {
            SegmentActions.openSegment(id);
            UI.scrollSegment( id );
        }
    });

    function getPreviousTranslationText( segment ) {
        var record = RI.getSegmentRecord(segment);
        var version ;
        var prevBase = record.version_number ;
        version = db.segment_versions.findObject({
            id_segment : parseInt(record.sid),
            version_number : (prevBase -1) + ''
        });
        if ( version ) {
            return version.translation;
        } else {
            return false;
        }
    }

    $(document).on('translation:change', function() {
        ReviewImproved.reloadQualityReport();
    });

    $(document).on('click', 'a.approved', function(e) {
        UI.changeStatus( this , 'approved', 0);
        var goToNextNotApproved = ($(e.currentTarget).hasClass('approved')) ? false : true;
        if (goToNextNotApproved) {
            UI.openNextTranslated();
        } else {
            UI.gotoNextSegment(UI.currentSegmentId);
        }
    });

    $(document).on('click', '.button-reject', function(e) {
        UI.rejectAndGoToNext();
    });

    var textSelectedInsideSelectionArea = function( selection, container ) {
        // return $.inArray( selection.focusNode, container.contents() ) !==  -1 &&
        //     $.inArray( selection.anchorNode, container.contents() ) !== -1 &&
        return container.contents().text().indexOf(selection.focusNode.textContent)>=0 &&
            container.contents().text().indexOf(selection.anchorNode.textContent)>=0 &&
            selection.toString().length > 0 ;
    };

    $(document).on('click', 'section .goToNextToReview', function(e) {
        e.preventDefault();
        UI.gotoNextSegment();
    });


    $(document).on('mouseup', 'section.opened .errorTaggingArea', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var segment = new UI.Segment( $(e.target).closest('section'));
        var selection = document.getSelection();
        var container = $(e.target).closest('.errorTaggingArea') ;

        if ( textSelectedInsideSelectionArea(selection, container ) )  {
            var selection = getSelectionData( selection, container ) ;
            SegmentActions.openIssuesPanel({ sid: segment.id,  selection : selection }, true);
        }
    });
    function renderButtons(segment) {
        if (segment === undefined) {
            segment = UI.Segment.find( UI.currentSegmentId );
        }

        var container = segment.el.find('.buttons') ;
        container.empty();

        var currentScore = getLatestScoreForSegment( segment ) ;

        var buttonData = {
            disabled : !container.hasClass('loaded'),
            id_segment : segment.id,
            ctrl : ( (UI.isMac) ? 'CMD' : 'CTRL'),
            show_approve : currentScore == 0,
            show_reject : currentScore > 0
        };

        var buttonsHTML = MateCat.Templates['review_improved/segment_buttons']( buttonData ) ;

        var data = {
            versions : versions.findObjects({ id_segment : segment.absId })
        };

        container.append(buttonsHTML);
    }



    getLatestScoreForSegment = function( segment ) {
        if (! segment) {
            return ;
        }
        var db_segment = MateCat.db.segments.findObject({ sid : '' + segment.absId });
        var latest_issues = MateCat.db.segment_translation_issues.findObjects({
            id_segment : parseInt(segment.absId) ,
            translation_version : '' + db_segment.version_number
        });

        var total_penalty = _.reduce(latest_issues, function(sum, record) {
            return sum + parseInt(record.penalty_points) ;
        }, 0) ;

        return total_penalty ;
    };

    var issuesChanged = function( record ) {
        var segment = UI.Segment.find(record.id_segment);
        if ( segment ) renderButtons( segment ) ;
    };

    MateCat.db.addListener('segment_translation_issues', ['insert', 'delete', 'update'], issuesChanged );


    $.extend( ReviewImproved, {
        renderButtons : renderButtons,
        getLatestScoreForSegment: getLatestScoreForSegment
    });
})($, window, ReviewImproved, UI);
}
