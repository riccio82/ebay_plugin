<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/06/2017
 * Time: 17:55
 */

namespace Features\Ebay\Model ;

use Features;
use Projects_ProjectStruct;

use Features\ProjectCompletion\Model\ProjectCompletionStatusModel as ParentModel ;

class ProjectCompletionStatusModel {

    const STATUS_COMPLETED = 'completed' ;
    const STATUS_NON_COMPLETED = 'non_completed';
    const STATUS_RECOMPLETABLE = 'recompletable' ;
    const STATUS_MISSING_COMPLETED_CHUNKS = 'missing_completed_chunks' ;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    /**
     * @var ParentModel
     */
    protected $parentModel ;

    protected $currentStatus ;

    public function __construct( Projects_ProjectStruct $project ) {
        $this->project = $project ;
        $this->parentModel = new ParentModel( $this->project ) ;
    }

    public function getCurrentStaus() {
        if ( count( $this->getCompleteTranslateChunks() ) != count( $this->project->getChunks() ) ) {
            return self::STATUS_MISSING_COMPLETED_CHUNKS ;
        }

        $project_completion_date = $this->project->getMetadataValue(Features\Ebay::PROJECT_COMPLETION_METADATA_KEY) ;

        if ( is_null( $project_completion_date ) ) {
            return self::STATUS_NON_COMPLETED ;
        }

        if ( strtotime($this->mostRecentCompletedTranslation()['completed_at']) > (int) $project_completion_date) {
            return self::STATUS_RECOMPLETABLE ;
        }
        else {
            return self::STATUS_COMPLETED  ;
        }
    }

    public function isCompletable() {
        return in_array($this->getCurrentStaus(), [ self::STATUS_NON_COMPLETED, self::STATUS_RECOMPLETABLE ] );
    }

    public function isChunkCompletionUndoable() {
        return $this->getCurrentStaus() != self::STATUS_COMPLETED ;
    }

    public function mostRecentCompletedTranslation() {
        $completed = $this->getCompleteTranslateChunks() ;
        usort( $completed , function( $item1, $item2 ) {
            if ( $item1['completed_at'] == $item2['completed_at'] ) return 0 ;
            return ( $item1['completed_at'] > $item2['completed_at'] ) ? 1 : -1 ;
        });

        return $completed[0];
    }

    public function getCompleteTranslateChunks() {
        return array_filter( $this->parentModel->getStatus()['translate'], function( $item ) {
            return $item['completed'] ;
        } );
    }

}