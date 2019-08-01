(function() {
    let ReviewImprovedSideButton = require( './ReviewImprovedTranslationIssuesSideButton' ).default;

    function overrideTranslationIssuesSideButton( TranslationIssuesSideButton ) {
        TranslationIssuesSideButton.prototype.render = function () {
            return <ReviewImprovedSideButton {...this.props}/>
        }
    }

    function overrideSegmentBodyFunctions( SegmentBody ) {
        SegmentBody.prototype.getStatusMenu = function () {
            if ( this.state.showStatusMenu ) {
                return <ul className="statusmenu" ref={( menu ) => this.statusMenuRef = menu}>
                    <li className="arrow"><span className="arrow-mcolor"/></li>

                    <li>
                        <a className="draftStatusMenu" data-sid={"segment-" + this.props.segment.sid} title="set draft as status"
                           onClick={this.changeStatus.bind( this, 'draft' )}>
                            DRAFT
                        </a>
                    </li>
                    <li>
                        <a className="translatedStatusMenu" data-sid={"segment-" + this.props.segment.sid} title="set translated as status"
                           onClick={this.changeStatus.bind( this, 'translated' )}>
                            TRANSLATED
                        </a>
                    </li>
                    <li><a className="approvedStatusMenu" data-sid={"segment-" + this.props.segment.sid} title="set approved as status"
                           onClick={this.changeStatus.bind( this, 'approved' )}>APPROVED</a></li>

                    {!ReviewImproved && config.reviewType !== 'improved' ? (
                        <li>
                            <a className="rejectedStatusMenu" data-sid={"segment-" + this.props.segment.sid} title="set rejected as status"
                               onClick={this.changeStatus.bind( this, 'rejected' )}>
                                REJECTED
                            </a>
                        </li>
                    ) : (null)}

                    {ReviewImproved || config.reviewType == 'improved' ? (
                        <li>
                            <a className="fx" data-sid={"segment-" + this.props.segment.sid} title="set fixed as status"
                               onClick={this.changeStatus.bind( this, 'fixed' )}>
                                FIXED
                            </a>
                        </li>
                    ) : (null)}
                    {ReviewImproved || config.reviewType == 'improved' ? (
                        <li>
                            <a className="rb" data-sid={"segment-" + this.props.segment.sid} title="set rebutted as status"
                               onClick={this.changeStatus.bind( this, 'rebutted' )}>
                                REBUTTED
                            </a>
                        </li>
                    ) : (null)}

                </ul>
            } else {
                return '';

            }
        };

        const originalbeforeRender = SegmentBody.prototype.beforeRenderOrUpdate;
        SegmentBody.prototype.beforeRenderOrUpdate = function () {
            if ( !(ReviewImproved && ReviewImproved.enabled() && Review.enabled()) ) {
                originalbeforeRender.apply( this )
            }
        };
        const originalAfterRender = SegmentBody.prototype.afterRenderOrUpdate;
        SegmentBody.prototype.afterRenderOrUpdate = function () {
            if ( !(ReviewImproved && ReviewImproved.enabled() && Review.enabled()) ) {
                originalAfterRender.apply( this );
            }
        }
    }

    function overrideSegmentTargetFunctions( SegmentTarget ) {
        let originalGetTargetArea = SegmentTarget.prototype.getTargetArea;
        SegmentTarget.prototype.getTargetArea = function ( translation ) {
            if ( ReviewImproved && ReviewImproved.enabled() && Review.enabled() ) {
                return <div className="textarea-container" onClick={this.onClickEvent.bind( this )}>
                    <div className="targetarea issuesHighlightArea errorTaggingArea"
                         dangerouslySetInnerHTML={this.allowHTML( translation )}/>
                </div>

            } else {
                return originalGetTargetArea.apply( this, [translation] )
            }
        };
    }

    function overrideTargetButtons( SegmentButtons ) {
        SegmentButtons.prototype.getButtons = function () {
            let html;
            if ( this.props.isReview ) {
                //Revise of Review Improved
                html = this.getReviewImprovedButtons()
            } else {
                //Translate of Review Improved
                html = this.getReviewImprovedTranslateButtons()
            }
            return html;
        };

        SegmentButtons.prototype.getReviewImprovedButtons = function () {
            let button;
            let segment = UI.Segment.find( this.props.segment.sid );
            let currentScore = ReviewImproved.getLatestScoreForSegment(segment );
            if ( currentScore == 0 ) {
                button = <li className="right">
                    <a id={"segment-" + this.props.segment.sid + "-button-translated "}
                       onClick={( event ) => this.clickOnApprovedButton( event )}
                       data-segmentid={"segment-" + this.props.segment.sid}
                       href="javascript:;" className="approved"
                    >APPROVED</a>
                    <p>{(UI.isMac) ? 'CMD' : 'CTRL'} ENTER</p>
                </li>;
            } else if ( currentScore > 0 ) {
                button = <li className="right">
                    <a className="button button-reject" href="javascript:;">REJECTED</a>
                    <p>{(UI.isMac) ? 'CMD' : 'CTRL'}+SHIFT+DOWN</p>
                </li>;
            }
            return <ul className="buttons toggle" data-mount="main-buttons" id={"segment-" + this.props.segment.sid + "-buttons"}>
                {button}
            </ul>
        };
        SegmentButtons.prototype.getReviewImprovedTranslateButtons = function () {
            //TODO Remove lokiJs
            let data = MateCat.db.segments.by( 'sid', this.props.segment.sid );
            if ( UI.showFixedAndRebuttedButtons( data.status ) ) {
                return <ul className="buttons toggle" data-mount="main-buttons" id={"segment-" + this.props.segment.sid + "-buttons"}>
                    <MC.SegmentMainButtons
                        status={data.status}
                        sid={data.sid}
                    />
                </ul>
            } else {
                return this.getTranslateButtons()
            }
        };
    }

    overrideTranslationIssuesSideButton(TranslationIssuesSideButton);

    overrideSegmentTargetFunctions(SegmentTarget);

    overrideSegmentBodyFunctions(SegmentBody);

    overrideTargetButtons(SegmentButtons)


})();