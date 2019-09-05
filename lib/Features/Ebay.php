<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/29/16
 * Time: 1:08 PM
 */

namespace Features;

use Chunks_ChunkStruct;
use controller;
use Constants_TranslationStatus;
use Exception;
use Features;
use Features\Dqf\Model\ExtendedTranslationStruct;
use Features\Ebay\Utils\Metadata;
use Features\Ebay\Utils\Routes as Routes;
use Features\Ebay\Utils\SkippedSegments;
use Features\ProjectCompletion\CompletionEventStruct;
use Features\ReviewExtended\Model\QualityReportModel;
use FilesStorage\AbstractFilesStorage;
use Klein\Klein;
use Projects_ProjectStruct;
use Features\ReviewImproved ;

class Ebay extends BaseFeature {

    const FEATURE_CODE = 'ebay';

    private $translation;
    private $old_translation;
    private $edit_distance;

    public static $dependencies = [
            Features::PROJECT_COMPLETION,
            Features::TRANSLATION_VERSIONS,
            ReviewImproved::FEATURE_CODE
    ] ;

    const PROJECT_COMPLETION_METADATA_KEY = 'ebay_project_completed_at';

    const PROJECT_TYPE_MT = 'MT' ;
    const PROJECT_TYPE_HT = 'HT' ;

    public function postProjectCreate( $projectStructure ) {
        $projectStructure[ 'result' ][ 'analyze_url' ] = Routes::analyze( array(
                'project_name' => $projectStructure[ 'project_name' ],
                'id_project'   => $projectStructure[ 'result' ][ 'id_project' ],
                'password'     => $projectStructure[ 'result' ][ 'ppassword' ]
        ) );
    }

    /**
     * This method handle the incompatibility between review extended and improved by removing the review_extended feature.
     *
     * In project creation, the callback "filterCreateProjectFeatures" is invoked by the mandatory plugin review_extended to add itself to the project features.
     *
     * When the project is subsequently handled by ProjectManager and the user has review_improved feature enabled ( ONLY happens to Ebay user ) those features are both added to the project
     * and the project creation crashes because of a duplicate call to \AbstractRevisionFeature::postProjectCreate
     * and a duplicate insert on qa_chunk_reviews is performed breaking database index integrity.
     *
     * @see ReviewExtended::filterCreateProjectFeatures
     *
     * @param array $projectFeatures
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     */
    public function filterOverrideReviewExtended( $projectFeatures, $controller ){
        if( $projectFeatures[ ReviewExtended::FEATURE_CODE ] ){
            unset( $projectFeatures[ ReviewExtended::FEATURE_CODE ] );
        }
        if( $projectFeatures[ SecondPassReview::FEATURE_CODE ] ){
            unset( $projectFeatures[ SecondPassReview::FEATURE_CODE ] );
        }
        return $projectFeatures;
    }

    /**
     * This function overrides the default analyze page, doing a redirect
     * to the custom analyze page.
     *
     * Every project that was created by a user who has this feature enabled
     * should fall into this case.
     *
     * @param controller $controller
     * @param            $params
     *
     * @throws Exception
     */
    public function beginDoAction( controller $controller, $params = [] ) {

        $controllerName = get_class( $controller );
        if ( $controllerName == 'analyzeController' ) {
            $project = $params['project'];

            if ( $params['page_type'] == 'job_analysis' ) {
                throw new Exception('Not found', 404) ;
            }

            $route = Routes::analyze( array(
                    'project_name' => $project->name,
                    'id_project'   => $project->id,
                    'password'     => $project->password,
            ) );

            header('Location: ' . $route);
            die();
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
     * @param Projects_ProjectStruct|int $project
     * 
     * TODO: this code needs to be refactored, does not works for multiple language jobs
     */
    public function beforeTMAnalysisCloseProject( $project ) {
        $db = \Database::obtain()->getConnection() ;

        $sql_project_id = 'SELECT id FROM jobs WHERE id_project = ?';
        $stmt = $db->prepare( $sql_project_id );

        $stmt->setFetchMode( \PDO::FETCH_ASSOC );

        if( $project instanceof Projects_ProjectStruct ){
            $pid = $project->id;
        } else {
            $pid = $project;
        }

        $stmt->execute( [ $pid ] ) ;
        $result = $stmt->fetch(); //TODO this takes only one job, manage multi language

        $sql = "UPDATE segment_translations SET eq_word_count = null WHERE id_job = ? ";
        $stmt = $db->prepare( $sql );
        $stmt->execute( [ $result['id'] ] ) ;

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
            if ( AbstractFilesStorage::pathinfo_fix( $file, PATHINFO_EXTENSION ) != 'g' ) {
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
        if ( $projectStructure[ 'metadata' ][ 'project_type' ] == self::PROJECT_TYPE_MT  ) {
            $status = Constants_TranslationStatus::STATUS_DRAFT;
        }

        return $status;
    }

    public static function loadRoutes( Klein $klein ) {
        $klein->respond( 'GET',  '/analyze/[:name]/[:id_project]-[:password]',              [__CLASS__, 'analyzeRoute'] );
        $klein->respond( 'GET',  '/reference-files/[:id_project]/[:password]/[:zip_index]', [__CLASS__, 'referenceFilesRoute' ] );
        $klein->respond( 'POST', '/projects/[:id_project]/[:password]/completion',          [__CLASS__, 'setProjectCompletedRoute' ] ) ;
        $klein->respond( 'GET',  '/api/v1/projects/[:id_project]/[:password]/completion_status',        [__CLASS__, 'getCompletionRoute' ] ) ;
        $klein->respond( 'POST', '/api/app/projects/[:id_project]/[:password]/dqf_intermediate_project', [__CLASS__, 'createIntermediateProject' ] ) ;
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
        $controller = new Features\Ebay\Controller\ProjectCompletionController($request, $response, $server, $app );
        $controller->respond('getCompletion') ;
    }

    public static function createIntermediateProject($request, $response, $server, $app) {
        $controller = new Features\Ebay\Controller\DqfIntermediateProjectController($request, $response, $server, $app);
        $controller->respond('create') ;
    }

    /**
     * Append the filter config to the post params which are coming from the UI.
     *
     * @param $inputFilter
     *
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

        $my_metadata = array_intersect_key( $options['input'], array_flip( Metadata::$keys ) ) ;
        $my_metadata = array_filter( $my_metadata ); // <-- remove all `empty` array elements

        return  array_merge( $my_metadata, $metadata );
    }

    /**
     * @param Chunks_ChunkStruct    $chunk
     * @param CompletionEventStruct $params
     * @param                       $lastId
     */
    public function project_completion_event_saved( Chunks_ChunkStruct $chunk, CompletionEventStruct $params, $lastId ) {
        $project = $chunk->getProject() ;

        // reload quality report and dump it to file
        $quality_report = new QualityReportModel( $chunk ) ;
        $structure = $quality_report->getStructure();

        $this->getLogger()->info( "ChunkCompletionEvent LASTID: $lastId" );
        $this->getLogger()->info( json_encode( $params ) ) ;
        $this->getLogger()->info( json_encode( $structure ) ) ;
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
        if ( $projectStructure['metadata']['project_type'] == self::PROJECT_TYPE_MT ) {
            return true ;
        }
        else {
            return $originalValue ;
        }
    }

    public function filterDqfIntermediateProjectRequired($value) {
        return true ;
    }

    /**
     * Ebay projects are either MT or HT by default so we override MateCat's computation and provide an hardcoded value.
     *
     * @param                           $name
     * @param ExtendedTranslationStruct $translationStruct
     * @param Chunks_ChunkStruct        $chunk
     *
     * @return mixed|string
     */
    public function filterDqfSegmentOriginAndMatchRate( $data, ExtendedTranslationStruct $translationStruct, Chunks_ChunkStruct $chunk ) {
        $projectType = $chunk->getProject()->getMetadataValue('project_type') ;
        if ( $projectType == 'MT' ) {
            $data[ 'originName' ] = 'MT' ;
            $data[ 'matchRate' ] = 100 ;
        }
        else  {
            $data[ 'originName' ] = 'HT' ;
            $data[ 'matchRate' ] = null ;
        }

        return $data ;
    }

}
