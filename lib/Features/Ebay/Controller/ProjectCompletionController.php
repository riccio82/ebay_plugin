<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/06/2017
 * Time: 13:20
 */

namespace Features\Ebay\Controller;

use Features\Ebay\Model\ProjectCompletionStatusModel;
use Klein\Request;
use Klein\Response;
use Projects_ProjectDao;
use Projects_ProjectStruct;

class ProjectCompletionController {

    /**
     * @var Request
     */
    protected $request ;

    /**
     * @var Response
     */
    protected $response ;

    protected $service ;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    public function __construct( $request, $response, $service ) {
        $this->request = $request ;
        $this->response = $response ;
        $this->service = $service ;

        $this->__findProject();
    }

    public function getCompletion() {
        $model = new ProjectCompletionStatusModel( $this->project ) ;
        $this->response->json( ['status' => $model->getCurrentStaus() ] );
    }

    public function setCompletion() {
        $model = new ProjectCompletionStatusModel( $this->project ) ;

        if ( $model->isCompletable() ) {
            $this->project->setMetadata('ebay_project_completed_at', time() );
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