<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/18/16
 * Time: 4:56 PM
 */

namespace Features\Ebay\Decorator;

use AbstractModelViewDecorator;
use Analysis\Status;
use Analysis_AnalysisModel;
use Bootstrap;
use DateTime;
use Features\Dqf;
use Features\Ebay;
use Features\Ebay\Utils\Routes;
use Files_FileDao;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use INIT;
use Langs_LanguageDomains;
use SimpleS3\Client;
use Users_UserStruct;
use Utils;
use ZipArchiveExtended;


class AnalyzeDecorator extends AbstractModelViewDecorator {

    /**
     * @var Analysis_AnalysisModel
     */
    protected $model;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    /**
     * @var Users_UserStruct
     */
    private $user;

    public function __construct( Analysis_AnalysisModel $model ) {
        $this->model   = $model;
        $this->project = $this->model->getProject();
    }

    public function decorate( $template ) {

        $this->setTempalteVarsBefore( $template );

        $template->basepath         = INIT::$BASEURL;
        $template->build_number     = INIT::$BUILD_NUMBER;
        $template->enable_outsource = false;

        $template->pid              = $this->model->getProject()->id;
        $template->project_password = $this->model->getProject()->password;

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

        $template->fast_wc_time          = $this->model->fast_wc_time;
        $template->tm_wc_time            = $this->model->tm_wc_time;
        $template->tm_wc_unit            = $this->model->tm_wc_unit;
        $template->fast_wc_unit          = $this->model->fast_wc_unit;
        $template->standard_wc_unit      = $this->model->standard_wc_unit;
        $template->raw_wc_time           = $this->model->raw_wc_time;
        $template->standard_wc_time      = $this->model->standard_wc_time;
        $template->raw_wc_unit           = $this->model->raw_wc_unit;
        $template->project_status        = $this->model->project_status;
        $template->num_segments          = $this->model->num_segments;
        $template->num_segments_analyzed = $this->model->num_segments_analyzed;
        $template->subject               = $this->model->subject;

        $template->reference_files = $this->model->reference_files;

        $template->support_mail = INIT::$SUPPORT_MAIL;

        $langDomains   = Langs_LanguageDomains::getInstance();
        $this->subject = $langDomains::getDisplayDomain( null );

        $template->isLoggedIn = $this->user != null;

        $misconfiguration = Status::thereIsAMisconfiguration();
        if ( $misconfiguration && mt_rand( 1, 3 ) == 1 ) {
            $msg = "<strong>The analysis daemons seem not to be running despite server configuration.</strong><br />Change the application configuration or start analysis daemons.";
            Utils::sendErrMailReport( $msg, "Matecat Misconfiguration" );
        }

        $template->daemon_misconfiguration = var_export( $misconfiguration, true );
        $template->project_data            = $this->getProjectData();

        $template->splittable          = true;
        $template->project_completable = true;

        $template->googleDriveEnabled = Bootstrap::isGDriveConfigured();

        if ( $this->project->hasFeature( Dqf::FEATURE_CODE ) ) {
            $this->__decorateForDqf( $template );
        }
        $template->append( 'footer_js', Routes::staticSrc( 'js/ebay-analyze.js' ) );

        $this->setTemplateVarsAfter( $template );
    }

    private function __decorateForDqf( $template ) {
        $intermediate_project_id = $this->project->getMetadataValue( Dqf::INTERMEDIATE_PROJECT_METADATA_KEY );

        if ( !$intermediate_project_id ) {
            $template->splittable          = false;
            $template->project_completable = false;
        }

        $template->dqf_intermediate_project = $intermediate_project_id;
        $template->user                     = $this->user;

        if ( $this->user ) {
            $template->dqf_user = new Dqf\Model\UserModel( $this->user );
        }

    }

    private function getProjectData() {
        // TODO: this should be moved to a specifi model
        $metadata = $this->model->getProject()->getMetadataAsKeyValue();
        $date     = null;

        if ( $metadata[ 'due_date' ] != null ) {
            $date = new DateTime( $metadata[ 'due_date' ] );
            $date = $date->format( 'Y-m-d H:i:s' );
        }

        return [
                'instructions'                             => $this->getInstructions(),
                'file_name'                                => $metadata[ 'file_name' ],
                'due_date'                                 => $date,
                'word_count'                               => $metadata[ 'word_count' ],
                'project_completion_timestamp'             => $metadata[ Ebay::PROJECT_COMPLETION_METADATA_KEY ],
                'dqf_review_settings_id'                   => $metadata[ 'dqf_review_settings_id' ],
                'dqf_source_segments_submitted'            => $metadata[ 'dqf_source_segments_submitted' ],
                'dqf_master_project_creation_completed_at' => $metadata[ 'dqf_master_project_creation_completed_at' ]
        ];
    }

    private function getInstructions() {
        $files = Files_FileDao::getByProjectId( $this->project->id );

        $fs = FilesStorageFactory::create();

        list( $zip_filename ) = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $files[ 0 ]->filename );

        $zip_path = $fs->getOriginalZipPath(
                $this->project->create_date,
                $this->project->id,
                $zip_filename );

        if ( AbstractFilesStorage::isOnS3() ) {

            $filePath = tempnam( '/tmp', 'ebay_' );
            /** @var $s3Client Client */
            $s3Client            = S3FilesStorage::getStaticS3Client();
            $params[ 'bucket' ]  = \INIT::$AWS_STORAGE_BASE_BUCKET;
            $params[ 'key' ]     = $zip_path;
            $params[ 'save_as' ] = $filePath;
            $s3Client->downloadItem( $params );
            $zip_path = $filePath;

        }

        $zip = new \ZipArchive();
        $zip->open( $zip_path );

        $content = $zip->getFromName( '__meta/instructions.txt' );

        if ( empty( $content ) ) {
            $content = 'No instructions provided.';
        }

        return $content;
    }

    public function setUser( Users_UserStruct $user = null ) {
        if ( is_null( $user ) ) {
            $this->user = new Users_UserStruct();
        } else {
            $this->user = $user;
        }
    }


}