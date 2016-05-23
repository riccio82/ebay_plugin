<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/18/16
 * Time: 2:48 PM
 */

namespace Features\Ebay\Controller;

use Features\Ebay\Decorator\AnalyzeDecorator ;

use Analysis_AnalysisModel ;

class AnalyzeController {

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    /**
     * @var \PHPTAL ;
     */
    private $view;

    public function __construct( \Klein\Request $request, \Klein\Response $response, $service) {
        $this->request = $request;
        $this->response = $response;
        $this->service = $service;

        $this->findProject();

        $this->model = new Analysis_AnalysisModel( $this->project, new \Chunks_ChunkStruct() );
        $this->model->loadData();
    }

    public function setView( $template_name ) {
        $this->view = new \PHPTALWithAppend( $template_name );
    }


    public function respond() {
        $decorator = new AnalyzeDecorator( $this->model );

        $decorator->setUser( $this->currentUser() ) ;
        $decorator->decorate( $this->view );

        $this->response->body( $this->view->execute() );
        $this->response->send();
    }

    private function currentUser() {
        \Bootstrap::sessionStart();
        $user = null;
        if ( !empty( $_SESSION['uid'] ) ) {
            $user_dao = new \Users_UserDao( \Database::obtain());
            $user = $user_dao->getByUid( $_SESSION['uid']);
        }
        return $user;
    }

    private function findProject() {
        $this->project = \Projects_ProjectDao::findByIdAndPassword(
                $this->request->param('id_project'),
            $this->request->param('password')
        );
    }



}