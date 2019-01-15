<?php

namespace Features\ReviewImproved\Observer;


use Features\ReviewImproved\SegmentTranslationModel;

class SegmentTranslationObserver implements \SplObserver {

    /**
     * @var \SegmentTranslationModelSubject
     */
    private $subject;

    public function update( \SplSubject $segment_translation_model ) {
        $this->subject = $segment_translation_model ;

        $model = new SegmentTranslationModel( $segment_translation_model );
        $model->addOrSubtractCachedReviewedWordsCount();

        // we need to recount score globally because of autopropagation.
        $model->recountPenaltyPoints();
    }
}