<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/18/16
 * Time: 4:56 PM
 */

namespace Features\Ebay\Decorator;

use AbstractModelViewDecorator ;

use Analysis\Status;
use INIT ;
use DateTime ;

class AnalyzeDecorator extends AbstractModelViewDecorator {

    /**
     * @var \Analysis_AnalysisModel
     */
    protected $model ;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project ;

    private $user ;

    public function __construct( \Analysis_AnalysisModel $model ) {
        $this->model = $model ;

        $this->project = $this->model->getProject();
    }

    public function decorate( $template ) {

        $template->basepath     = INIT::$BASEURL;
        $template->build_number = INIT::$BUILD_NUMBER;
        $template->enable_outsource = false;

        $template->pid                        = $this->model->getProject()->id;
        $template->project_password           = $this->model->getProject()->password;

        $template->jobs                       = $this->model->jobs;
        $template->fast_analysis_wc           = $this->model->fast_analysis_wc;
        $template->fast_analysis_wc_print     = $this->model->fast_analysis_wc_print;
        $template->tm_analysis_wc             = $this->model->tm_analysis_wc;
        $template->tm_analysis_wc_print       = $this->model->tm_analysis_wc_print;
        $template->standard_analysis_wc       = $this->model->standard_analysis_wc;
        $template->standard_analysis_wc_print = $this->model->standard_analysis_wc_print;
        $template->total_raw_word_count       = $this->model->total_raw_word_count;
        $template->total_raw_word_count_print = $this->model->total_raw_word_count_print;
        $template->pname                      = $this->model->pname;

        $template->fast_wc_time               = $this->model->fast_wc_time;
        $template->tm_wc_time                 = $this->model->tm_wc_time;
        $template->tm_wc_unit                 = $this->model->tm_wc_unit;
        $template->fast_wc_unit               = $this->model->fast_wc_unit;
        $template->standard_wc_unit           = $this->model->standard_wc_unit;
        $template->raw_wc_time                = $this->model->raw_wc_time;
        $template->standard_wc_time           = $this->model->standard_wc_time;
        $template->raw_wc_unit                = $this->model->raw_wc_unit;
        $template->project_status             = $this->model->project_status;
        $template->num_segments               = $this->model->num_segments;
        $template->num_segments_analyzed      = $this->model->num_segments_analyzed;
        $template->subject                    = $this->model->subject;

        $template->reference_files            = $this->model->reference_files ;

        $client                 = \OauthClient::getInstance()->getClient();

        $template->support_mail    = INIT::$SUPPORT_MAIL;

        $langDomains = \Langs_LanguageDomains::getInstance();
        $this->subject = $langDomains::getDisplayDomain(null);

        $template->isLoggedIn = $this->user != null ;

        $misconfiguration = Status::thereIsAMisconfiguration();
        if ( $misconfiguration && mt_rand( 1, 3 ) == 1 ) {
            $msg = "<strong>The analysis daemons seem not to be running despite server configuration.</strong><br />Change the application configuration or start analysis daemons.";
            \Utils::sendErrMailReport( $msg, "Matecat Misconfiguration" );
        }

        $template->daemon_misconfiguration = var_export( $misconfiguration, true );
        $template->project_data = $this->getProjectData() ;
    }

    private function getProjectData() {
        // TODO: this should be moved to a specifi model
        $metadata = $this->model->getProject()->getMetadataAsKeyValue();
        $date = null;
        if ( $metadata['due_date'] != null ) {
            $date = new DateTime( $metadata['due_date'] );
            $date = $date->format('Y-m-d H:i:s');
        }

        return array(
                'instructions' => $this->getInstructions(),
                'file_name'    => $metadata[ 'file_name' ],
                'due_date'     => $date,
                'word_count'   => $metadata[ 'word_count' ],
        );
    }

    private function getInstructions() {
        $files = \Files_FileDao::getByProjectId( $this->project->id );
        $fs = new \FilesStorage();

        list($zip_filename) = explode(\ZipArchiveExtended::INTERNAL_SEPARATOR, $files[0]->filename);

        $zip_path = $fs->getOriginalZipPath(
                $this->project->create_date,
                $this->project->id,
                $zip_filename);

        $zip = new \ZipArchive();
        $zip->open( $zip_path );

        $content = $zip->getFromName( '__meta/instructions.txt');

        if ( empty( $content ) ) {
            $content = 'No instructions provided.' ;
        }
        return $content ;
    }

    public function setUser( \Users_UserStruct $user=null ) {
        $this->user = $user ;
    }



}