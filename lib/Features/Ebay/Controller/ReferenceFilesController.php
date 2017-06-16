<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 15/04/16
 * Time: 11:40
 */

namespace Features\Ebay\Controller;

use Files_FileDao;
use Klein\Request;
use Klein\Response;
use ZipArchiveExtended;
use ZipArchive;

class ReferenceFilesController {

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;
    private $service;
    private $response;
    private $request;


    public function __construct( Request $request, Response $response, $service ) {
        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;

        $this->findProject();
    }

    public function downloadFile() {
        $file = $this->findZipFile();

        $this->response->header( 'Content-Description', 'File Transfer' );
        $this->response->header( 'Content-Type', 'application/octet-stream' );
        $this->response->header( 'Content-Disposition', 'attachment; filename=' . $file[ 'name' ] );
        $this->response->body( $file[ 'content' ] );

    }

    /**
     *
     * NOTE: It would be nice to have this method refactored to reduce
     * code duplication between what we have here and what's already in
     * Ebay\AnalyzeController.
     *
     * This methods looks up the file content and name inside the first
     * and only uploaded file, that we expect to be a Zip file.
     *
     *
     */
    private function findZipFile() {
        $folder = ZipArchiveExtended::REFERENCE_FOLDER;
        $fs     = new \FilesStorage();
        $jobs   = $this->project->getJobs();
        $files  = Files_FileDao::getByJobId( $jobs[ 0 ]->id );

        $zipName = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $files[ 0 ]->filename );
        $zipName = $zipName[ 0 ];

        $originalZipPath = $fs->getOriginalZipPath( $this->project->create_date, $this->project->id, $zipName );

        $zip = new ZipArchive();
        $zip->open( $originalZipPath );

        $file[ 'content' ] = $zip->getFromIndex( $this->request->param( 'zip_index' ) );
        $file[ 'name' ]    = $zip->getNameIndex( $this->request->param( 'zip_index' ) );
        $file[ 'name' ]    = preg_replace( "/$folder\/(\w+)/", '${1}',
                $zip->getNameIndex( $this->request->param( 'zip_index' ) )
        );
        $zip->close();

        return $file;
    }

    private function findProject() {
        $this->project = \Projects_ProjectDao::findByIdAndPassword(
                $this->request->param( 'id_project' ),
                $this->request->param( 'password' )
        );
    }

}