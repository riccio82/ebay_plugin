<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/29/16
 * Time: 1:08 PM
 */

namespace Features;

use Exception;
use Exceptions\ValidationError;

use Features\Ebay\Utils\Metadata;
use Features\Ebay\Utils\Routes as Routes ;
use Klein\Klein;
use LQA\ChunkReviewStruct;

use Features\Ebay\Utils\SkippedSegments;
use Features ;
use Projects_ProjectStruct;

class Ebay extends BaseFeature {

    private $translation;
    private $old_translation;
    private $edit_distance;

    const PROJECT_COMPLETION_METADATA_KEY = 'ebay_project_completed_at';

    public function postProjectCreate( $projectStructure ) {
        $projectStructure[ 'result' ][ 'analyze_url' ] = Routes::analyze( array(
                'project_name' => $projectStructure[ 'project_name' ],
                'id_project'   => $projectStructure[ 'result' ][ 'id_project' ],
                'password'     => $projectStructure[ 'result' ][ 'ppassword' ]
        ) );
    }

    /**
     * This function overrides the default analyze page, doing a redirect
     * to the custom analyze page.
     *
     * Every project that was created by a user who has this feature enabled
     * should fall into this case. 
     *
     * @param $controller
     * @param $params
     */
    public function beginDoAction($controller, $params) {
        if ( $controller == 'analyzeController' ) {
            $project = $params['project'];

            $route = Routes::analyze( array(
                    'project_name' => $project->name,
                    'id_project'   => $project->id,
                    'password'     => $project->password,
            ) );

            header('Location: ' . $route);
        }
    }

    public function setTranslationCommitted( $params ) {

        $this->translation     = $params[ 'translation' ];
        $this->old_translation = $params[ 'old_translation' ];
        $this->edit_distance   = $this->getEditDistance();

        $this->__setTranslation();

        if ( ! $params['propagated_ids'] ) {
            $this->__setPropagation();
        }

        SkippedSegments::updateSkippedSegmentsCount(
            $params['chunk'],
            $params['old_translation'],
            $params['translation'],
            $params['propagated_ids']
        ) ;
    }

    public function validateProjectCreation( $projectStructure ) {
        $this->__validateDueDate( $projectStructure );

        $projectStructure[ 'metadata' ][ 'word_count_type' ] = \Projects_MetadataDao::WORD_COUNT_RAW;
    }

    public function filterSetTranslationResult( $response, $params ) {
        $response ['stats']  = array_merge( $response ['stats'], SkippedSegments::getDataForStats( $params['chunk'] ) );
        return $response ;
    }

    public function filterStatsControllerResponse( $response, $params ) {
        $response ['stats']  = array_merge( $response ['stats'], SkippedSegments::getDataForStats( $params['chunk'] ) );
        return $response ;
    }

    public function filterIsChunkCompletionUndoable( $undoable, Projects_ProjectStruct $project, $chunk ) {
        $model = new Features\Ebay\Model\ProjectCompletionStatusModel( $project ) ;
        return $model->isChunkCompletionUndoable() ;
    }

    private function __setTranslation() {
        $count = \Translations_SegmentTranslationDao::updateEditDistanceForSetTranslation(
                array(
                        'id_segment'    => $this->translation[ 'id_segment' ],
                        'id_job'        => $this->translation[ 'id_job' ],
                        'segment_hash'  => $this->old_translation[ 'segment_hash' ],
                        'edit_distance' => $this->edit_distance
                )
        );
    }

    private function __validateDueDate( $projectStructure ) {
        if ( array_key_exists( 'due_date', $projectStructure[ 'metadata' ] ) ) {

            try {
                new \DateTime( $projectStructure[ 'metadata' ][ 'due_date' ] );
            } catch ( Exception $e ) {
                if ( !array_key_exists('errors', $projectStructure[ 'result' ])) {
                    $projectStructure[ 'result' ]['errors'] = array();
                }
                $projectStructure[ 'result' ]['errors'][] = ['message' => "Due date is not valid"];
            }
        }
    }

    /**
     *
     */
    private function __setPropagation() {
        $options    = array( 'persistent' => true );
        $class_name = '\Features\Ebay\EditDistancePropagationWorker';

        $data = array(
                'segment_hash'  => $this->old_translation[ 'segment_hash' ],
                'id_segment'    => $this->translation[ 'id_segment' ],
                'id_job'        => $this->translation[ 'id_job' ],
                'edit_distance' => $this->edit_distance
        );


        \WorkerClient::init(); // TODO: this should not be needed, to investigate.
        \WorkerClient::enqueue( 'P2', $class_name, $data, $options );
    }

    /**
     *
     * Gets the edit distance.
     *
     * TODO: change this to static funciton, it's wrong to have instance methods
     * on this class because it's too generic and will likely to become polluted.
     *
     * @param $translation
     *
     * @return int
     */
    private function getEditDistance() {
        $original = null;

        if ( intval( $this->old_translation[ 'version_number' ] ) == 0 ) {
            $original = $this->old_translation[ 'translation' ];
        } else {
            $version0 = \Translations_TranslationVersionDao::getVersionNumberForTranslation(
                    $this->translation[ 'id_job' ], $this->translation[ 'id_segment' ], 0
            );
            $original = $version0->translation;
        }

        $similarity    = \MyMemory::TMS_MATCH( $original, $this->translation[ 'translation' ] );
        $edit_distance = ( 1 - $similarity ) * 1000;

        return round( $edit_distance );
    }


    /**
     * This function updates the eq_word_count setting it to null right before the project is
     * closed by the TM Analysis. This way we force the project to always use raw word count.
     *
     * @param Projects_ProjectStruct $project
     * 
     * TODO: this code needs to be refactored
     */
    public function beforeTMAnalysisCloseProject( Projects_ProjectStruct $project) {
        $db = \Database::obtain()->getConnection() ;

        $sql_project_id = 'SELECT id FROM jobs WHERE id_project = ?';
        $stmt = $db->prepare( $sql_project_id );

        $stmt->setFetchMode( \PDO::FETCH_ASSOC );
        $stmt->execute( array( $project->id ) ) ;
        $result = $stmt->fetch();

        $sql = "UPDATE segment_translations SET eq_word_count = null " .
                " WHERE id_job = ? ";
        $stmt = $db->prepare( $sql );
        $stmt->execute( array( $result['id'] ) ) ;
    }

    /**
     * If segment is marked as skipped, do no send contribution
     *
     * @param $new_translation
     * @param $old_translation
     *
     * @return bool
     */
    public function filter_skip_set_contribution( $skip_set_contribution, $new_translation, $old_translation ) {
        return ( SkippedSegments::isSkipped( $new_translation ) );
    }

    /**
     * Ignore all glossaries. Temporary hack to avoid something unknown on MyMemory side.
     * We simply change the array_files key to avoid any glossary to be sent to MyMemory.
     *
     * TODO: glossary detection based on extension is brittle.
     *
     */
    public function filter_project_manager_array_files( $files, $projectStructure ) {
        $new_files = array() ;
        foreach ( $files as $file ) {
            if ( \FilesStorage::pathinfo_fix( $file, PATHINFO_EXTENSION ) != 'g' ) {
                $new_files[] = $file ;
            }
        }

        return $new_files   ;
    }

    /**
     * When project_type is 'MT', pretranslated segments are to be saved as DRAFT
     * for Ebay.
     *
     * @param $status
     * @param $projectStructure
     *
     * @return string
     */
    public function filter_status_for_pretranslated_segments( $status, $projectStructure ) {
        // TODO: constantize MT
        if ( $projectStructure[ 'metadata' ][ 'project_type' ] == 'MT' ) {
            $status = \Constants_TranslationStatus::STATUS_DRAFT;
        }

        return $status;
    }

    public static function loadRoutes( Klein $klein ) {
        $klein->respond( 'GET', '/analyze/[:name]/[:id_project]-[:password]',              [__CLASS__, 'analyzeRoute'] );
        $klein->respond( 'GET', '/reference-files/[:id_project]/[:password]/[:zip_index]', [__CLASS__, 'referenceFilesRoute' ] );
        $klein->respond( 'POST', '/projects/[:id_project]/[:password]/completion',         [__CLASS__, 'setProjectCompletedRoute' ] ) ;
        $klein->respond( 'GET', '/api/v1/projects/[:id_project]/[:password]/completion_status',         [__CLASS__, 'getCompletionRoute' ] ) ;
    }

    public static function analyzeRoute($request, $response, $service, $app) {
        $controller    = new Ebay\Controller\AnalyzeController( $request, $response, $service );
        $template_path = dirname( __FILE__ ) . '/Ebay/View/Html/analyze.html';
        $controller->setView( $template_path );
        $controller->respond();
    }

    public static function referenceFilesRoute($request, $response, $service, $app) {
        $controller    = new Ebay\Controller\ReferenceFilesController( $request, $response, $service );
        $controller->downloadFile();
    }

    public static function setProjectCompletedRoute( $request, $response, $service, $app ) {
        $controller = new Features\Ebay\Controller\ProjectCompletionController($request, $response, $service, $app );
        $controller->respond('setCompletion') ;
    }

    public static function getCompletionRoute( $request, $response, $server, $app ) {
        $controller = new Features\Ebay\Controller\ProjectCompletionController($request, $response, $service, $app );
        $controller->respond('getCompletion') ;
    }

    /**
     * Append the filter config to the post params which are coming from the UI.
     *
     * @param $filter
     * @return mixed
     */
    public function filterCreateProjectInputFilters( $inputFilter ) {
        return array_merge( $inputFilter, Metadata::getInputFilter() ) ;
    }

    /**
     * This filter is necessary to assign input params to the
     * metadata array when post comes from UI.
     *
     * @param $metadata
     * @param $options
     *
     * @return array
     */
    public function createProjectAssignInputMetadata( $metadata, $options ) {
        $options = \Utils::ensure_keys( $options, array('input'));

        $metadata = array_intersect_key( $options['input'], array_flip( Metadata::$keys ) ) ;
        $metadata = array_filter( $metadata ); // <-- remove all `empty` array elements

        return  $metadata ;
    }

    /**
     * @param $event ChunkReviewStruct
     */
    public function project_completion_event_saved( $chunk, $params, $lastId ) {
        $project = $chunk->getProject() ;

        if ( in_array( Features::REVIEW_IMPROVED, $project->getFeatures()->getCodes() ) ) {
            // reload quality report and dump it to file
            $quality_report = new Features\ReviewImproved\Model\QualityReportModel( $chunk ) ;
            $structure = $quality_report->getStructure();

            $this->getLogger()->info( "ChunkCompletionEvent LASTID: $lastId" );
            $this->getLogger()->info( json_encode( $params ) ) ;
            $this->getLogger()->info( json_encode( $structure ) ) ;
        }
    }

    public function postJobMerged( $projectStructure ) {
        $id_job = $projectStructure[ 'job_to_merge' ];

        $chunk = \Chunks_ChunkDao::getByJobID( $id_job ) [ 0 ] ;
        SkippedSegments::postJobMerged( $chunk ) ;

    }

    public function postJobSplitted( $projectStructure ) {
        SkippedSegments::postJobSplitted( $projectStructure['job_to_split'], $projectStructure['job_to_split_pass'] ) ;
    }

    /**
     * Ebay customisation requires that identical source and target are considered identical
     */
    public function filterIdenticalSourceAndTargetIsTranslated($originalValue, $projectStructure ) {

        if ( !isset( $projectStructure['metadata']) && !isset( $projectStructure['metadata']['project_type'] )) {
            throw new Exception( 'Expected project_type was not found' ) ;
        }
        if ( $projectStructure['metadata']['project_type'] == 'MT' ) {
            return true ;
        }
        else {
            return $originalValue ;
        }
    }

    public function getDependencies() {
    }

}