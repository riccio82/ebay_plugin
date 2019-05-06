(function() {
    let ReviewImprovedSideButton = require('./ReviewImprovedTranslationIssuesSideButton').default;

    function overrideTranslationIssuesSideButton( TranslationIssuesSideButton ) {
        TranslationIssuesSideButton.prototype.render = function (  ) {
            return <ReviewImprovedSideButton {...this.props}/>
        }
    }

    function overrideSegmentBodyFunctions( SegmentBody ) {
        SegmentBody.prototype.getStatusMenu = function (  ) {
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
        SegmentBody.prototype.beforeRenderOrUpdate = function (  ) {
            if ( !(ReviewImproved && ReviewImproved.enabled() && Review.enabled()) ) {
                originalbeforeRender.apply(this)
            }
        };
        const originalAfterRender = SegmentBody.prototype.afterRenderOrUpdate;
        SegmentBody.prototype.afterRenderOrUpdate = function (  ) {
            if ( !(ReviewImproved && ReviewImproved.enabled() && Review.enabled()) ) {
                originalAfterRender.apply(this);
            }
        }
    }

    function overrideSegmentTargetFunctions( SegmentTarget ) {
        let originalGetTargetArea = SegmentTarget.prototype.getTargetArea;
        SegmentTarget.prototype.getTargetArea = function ( translation ) {
            if ( ReviewImproved && ReviewImproved.enabled() && Review.enabled() ) {
                return <div data-mount="segment_text_area_container">
                    <div className="textarea-container" onClick={this.onClickEvent.bind( this )}>
                        <div className="targetarea issuesHighlightArea errorTaggingArea"
                             dangerouslySetInnerHTML={this.allowHTML( translation )}/>
                    </div>
                </div>
            } else {
                return originalGetTargetArea.apply(this, [translation])
            }
        };
    }

    overrideTranslationIssuesSideButton(TranslationIssuesSideButton);

    overrideSegmentTargetFunctions(SegmentTarget);

    overrideSegmentBodyFunctions(SegmentBody);


})();