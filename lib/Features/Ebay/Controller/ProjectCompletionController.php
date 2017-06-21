<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/06/2017
 * Time: 13:20
 */

namespace Features\Ebay\Controller;

use API\V2\KleinController;
use Features\Ebay\Model\ProjectCompletionStatusModel;
use Projects_ProjectDao;
use Projects_ProjectStruct;

class ProjectCompletionController extends KleinController {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected function afterConstruct() {
        parent::afterConstruct();

        $this->__findProject();
    }

    public function getCompletion() {
        $model = new ProjectCompletionStatusModel( $this->project ) ;
        $this->response->json( [ 'status' => $model->getCurrentStaus() ] );
    }

    public function setCompletion() {
        $model = new ProjectCompletionStatusModel( $this->project ) ;

        if ( $model->isCompletable() ) {
            $this->project->setMetadata( 'ebay_project_completed_at', time() );
            $this->response->code( 200 ) ;
        }
        else {
            $this->response->code( 400 ) ;
        }

    }

    protected function __findProject() {
        $this->project = Projects_ProjectDao::findByIdAndPassword(
                $this->request->param( 'id_project' ),
                $this->request->param( 'password' )
        );

    }

}