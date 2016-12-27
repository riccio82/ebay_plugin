<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/29/16
 * Time: 1:08 PM
 */

namespace Features;


use Exceptions\ValidationError;

use Features\Ebay\Utils\Metadata;
use Features\Ebay\Utils\Routes as Routes ;
use Teams\TeamDao;

use Exception ;

class Ebay extends BaseFeature {

    private $translation;
    private $old_translation;
    private $propagation;
    private $edit_distance;

    public static function getHardcodedVar( $name ) {
        if ( \INIT::$ENV == 'production' ) {
            $map = array( 'team_id' => 32785 );
        } else {
            $map = array( 'team_id' => 7 );
        }
        return $map[ $name ] ;
    }

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
        $this->propagation     = $params[ 'propagation' ];
        $this->edit_distance   = $this->getEditDistance();

        $this->__setTranslation();

        if ( !empty( $this->propagation ) ) {
            $this->__setPropagation();
        }
    }

    public function validateProjectCreation( $projectStructure ) {
        $this->__validateDueDate( $projectStructure );

        $projectStructure[ 'metadata' ][ 'word_count_type' ] = \Projects_MetadataDao::WORD_COUNT_RAW;
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
            } catch ( \Exception $e ) {
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
     * @param \Projects_ProjectStruct $project
     * 
     * TODO: this code needs to be refactored
     */
    public function beforeTMAnalysisCloseProject(\Projects_ProjectStruct $project) {
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


    public function filter_get_projects_team( $team ) {
        return static::getTeam();
    }

    public function filter_team_for_project_creation( $team ) {
        if ( is_null( $team ) ) {
            return static::getTeam();
        } else {
            return $team ;
        }
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

    public static function loadRoutes( \Klein\Klein $klein ) {
        $klein->respond( 'GET', '/analyze/[:name]/[:id_project]-[:password]', function ( $request, $response, $service ) {
            $controller    = new Ebay\Controller\AnalyzeController( $request, $response, $service );
            $template_path = dirname( __FILE__ ) . '/Ebay/View/Html/analyze.html';
            $controller->setView( $template_path );
            $controller->respond();
        } );

        $klein->respond( 'GET', '/reference-files/[:id_project]/[:password]/[:zip_index]', function ( $request, $response, $service ) {
            $controller    = new Ebay\Controller\ReferenceFilesController( $request, $response, $service );
            $controller->downloadFile();
        } );
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
     * This filter is necessary to assign input params to the metadata array when post comes from UI.
     *
     * @param $metadata
     * @param $options
     */
    public function createProjectAssignInputMetadata( $metadata, $options ) {
        $options = \Utils::ensure_keys( $options, array('input'));

        $metadata = array_intersect_key( $options['input'], array_flip( Metadata::$keys ) ) ;
        $metadata = array_filter( $metadata );

        return  $metadata ;
    }

    /**
     * Ebay customisation requires that identical source and target are considered identical
     */
    public function filterIdenticalSourceAndTargetIsTranslated($originalValue) {
        return true ;
    }

    public function getDependencies() {
    }

    private static function getTeam() {
        $team = ( new TeamDao() )->findById( self::getHardcodedVar( 'team_id' ) );
        if ( !$team ) {
            throw new Exception('Team not found');
        }
        return $team ;
    }

}