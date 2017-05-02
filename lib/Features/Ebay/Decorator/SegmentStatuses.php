<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/30/16
 * Time: 11:22 AM
 */

namespace Features\Ebay\Decorator;

use Constants_TranslationStatus;

class SegmentStatuses {

    private $project;
    private $project_type;

    public function __construct( \Projects_ProjectStruct $project ) {
        $this->project = $project;
        $metadata      = $project->getMetadataAsKeyValue();

        $this->project_type = $metadata['project_type'];
    }

    public function getLabelsMap() {
        return array(
                Constants_TranslationStatus::STATUS_NEW        => $this->labelForNew(),
                Constants_TranslationStatus::STATUS_DRAFT      => $this->labelForDraft(),
                Constants_TranslationStatus::STATUS_TRANSLATED => $this->labelForTranslated(),
                Constants_TranslationStatus::STATUS_APPROVED   => 'Approved',
                Constants_TranslationStatus::STATUS_REJECTED   => 'Rejected',
                Constants_TranslationStatus::STATUS_FIXED      => 'Fixed',
                Constants_TranslationStatus::STATUS_REBUTTED   => 'Rebutted'
        );
    }

    /**
     * Gets an array of objects of searchable statuses to populate
     * the search select in Cat page.
     *
     * @return array
     */
    public function getSearchableStatuses() {
        $statuses = array();
        $labels = $this->getLabelsMap();
        foreach ( $labels as $value => $label ) {
            if ($this->isMt() && $value == Constants_TranslationStatus::STATUS_NEW ) {
                continue;
            }

            $statuses[] = (object)array(
                    'value' => $value,
                    'label' => $label
            );
        }

        return $statuses;
    }

    private function labelForNew() {
        return $this->isMt() ? 'MT' : 'New';
    }

    private function labelForDraft() {
        return $this->isMt() ? 'MT' : 'Draft';
    }

    private function labelForTranslated() {
        return $this->isMt() ? 'Post-Edited' : 'Translated';
    }

    private function isMt() {
        return $this->project_type == 'MT';
    }


}