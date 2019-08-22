<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 22/08/19
 * Time: 15.04
 *
 */

namespace Features\ReviewImproved\Controller\API\V2\Validators;


use API\V2\Exceptions\ValidationError;
use Exception;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use Utils;

class SegmentTranslationIssue extends \API\V2\Validators\SegmentTranslationIssue {

    /**
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function __ensureSegmentRevisionIsCompatibileWithIssueRevisionNumber() {

        $latestSegmentEvent = ( new SegmentTranslationEventDao() )->getLatestEventForSegment( $this->chunk_review->id_job, $this->segment->id );

        if ( !$latestSegmentEvent && !$this->translation->isICE() ) {
            throw new Exception( 'Unable to find the current state of this segment. Please report this issue to support.' );
        }

    }

}