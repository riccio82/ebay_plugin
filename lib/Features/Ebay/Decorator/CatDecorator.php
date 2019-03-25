<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/30/16
 * Time: 8:33 AM
 */

namespace Features\Ebay\Decorator;

use AbstractDecorator;
use Constants_TranslationStatus;
use Features\Ebay\Model\ProjectCompletionStatusModel;
use Features\Ebay\Utils\Routes;


class CatDecorator extends \AbstractDecorator {

    /**
     * @var \catController
     */
    protected $controller;

    protected $statuses;

    protected $metadata ;

    /**
     * @var \PHPTALWithAppend
     */
    protected $template ;

    public function decorate() {

        $project = $this->controller->getChunk()->getProject() ;

        $this->template->append('footer_js', Routes::staticBuild('js/ebay-core.js') );
        $this->template->append('footer_js', Routes::staticBuild('js/ebay-components.js') );
        $this->template->append('css_resources', Routes::staticBuild('css/review_improved.css') );
//        $this->template->append('footer_js', Routes::staticSrc('js/ebay-cat.js') );

        $this->metadata = $this->controller->getChunk()->getProject()->getMetadataAsKeyValue();
        $this->statuses = new SegmentStatuses( $project ) ;

        $this->template->searchable_statuses = $this->statuses->getSearchableStatuses();
        $this->template->project_type = $this->metadata['project_type'];

        $this->template->status_labels = json_encode( $this->statuses->getLabelsMap() );

        $projectCompletionModel = new ProjectCompletionStatusModel( $project ) ;
        $this->template->chunk_completion_undoable = $projectCompletionModel->isChunkCompletionUndoable() ;

        $this->template->allow_link_to_analysis = false ;
        $this->template->translation_matches_enabled = false ;

        $this->template->quality_report_href =  $this->template->quality_report_href = \Routes::pluginsBase() .
                "/review_improved/quality_report/" .
                "{$this->controller->getChunk()->id}/" .
                "{$this->controller->getChunk()->password}";


    }

}
