<?php

namespace Features ;

use Features\ReviewImproved\ChunkReviewModel;
use Features\ReviewImproved\Controller\QualityReportController;
use LQA\ChunkReviewDao;

class ReviewImproved extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_improved' ;

    protected static $conflictingDependencies = [
            ReviewExtended::FEATURE_CODE
    ];

    /**
     * postJobSplitted
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param \ArrayObject $projectStructure
     *
     * @throws \Exceptions\ValidationError
     */
    public function postJobSplitted( \ArrayObject $projectStructure ) {

        $id_job = $projectStructure['job_to_split'];
        $old_reviews = ChunkReviewDao::findByIdJob( $id_job );
        $first_password = $old_reviews[0]->review_password ;

        ChunkReviewDao::deleteByJobId( $id_job );

        $this->createQaChunkReviewRecord( $id_job, $projectStructure[ 'id_project' ], [
                'first_record_password' => $first_password
        ] );

        $reviews = ChunkReviewDao::findByIdJob( $id_job );
        foreach( $reviews as $review ) {
            $model = new ChunkReviewModel($review);
            $model->recountAndUpdatePassFailResult();
        }
    }

    /**
     * Install routes for this plugin
     *
     * @param \Klein\Klein $klein
     */
    public static function loadRoutes( \Klein\Klein $klein ) {
        $klein->respond('GET', '/quality_report/[:id_job]/[:password]',                    array(__CLASS__, 'callbackQualityReport')  );
        $klein->respond('GET', '/quality_report/[:id_job]/[:password]/versions/[:version]', array(__CLASS__, 'callbackQualityReport')  );
    }

    public static function callbackQualityReport($request, $response, $service, $app) {
        $controller = new QualityReportController( $request, $response, $service, $app);
        $template_path = dirname(__FILE__) . '/ReviewImproved/View/Html/quality_report.html' ;
        $controller->setView( $template_path );
        $controller->respond();
    }
}
