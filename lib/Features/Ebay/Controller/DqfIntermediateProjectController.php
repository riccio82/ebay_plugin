<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 16:37
 */

namespace Features\Ebay\Controller;


use API\V2\KleinController;
use API\V2\Validators\ProjectPasswordValidator;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\IntermediateRootProject;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;

class DqfIntermediateProjectController extends KleinController {
    /**
     * @var ProjectPasswordValidator
     */
    protected $projectValidator ;

    public function afterConstruct() {
        $this->projectValidator = new ProjectPasswordValidator($this) ;
        $this->appendValidator( $this->projectValidator );
    }

    public function create() {
        $project = $this->projectValidator->getProject() ;

        $user = new UserModel( $this->user );
        $intermediateRootProject = new IntermediateRootProject( $user, $project );

        $projects = $intermediateRootProject->create() ;

        $this->response->json( [ 'projects' => array_map( function(CreateProjectResponseStruct $el) {
            return $el->toArray()  ;
        }, $projects ) ] ) ;

    }

}