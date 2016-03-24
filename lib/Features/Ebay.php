<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/29/16
 * Time: 1:08 PM
 */

namespace Features;


use Exceptions\ValidationError;

use Features\Ebay\Utils\Routes as Routes ;

class Ebay extends BaseFeature {

    private $translation;
    private $old_translation;
    private $propagation;
    private $edit_distance;

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
                $projectStructure[ 'result' ]['errors'][] = "Due date is not valid";
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

        \WorkerClient::enqueue( 'P2', $class_name, $data, $options );
    }

    /**
     *
     * Gets the edit distance
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
     * When project_type is 'MT', pretranslated segments are to be saved as DRAFT
     * for Ebay.
     *
     * @param $args
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
    }


}