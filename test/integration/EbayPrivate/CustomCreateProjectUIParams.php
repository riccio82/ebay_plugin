<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/12/2016
 * Time: 12:11
 */
class CustomCreateProjectUIParams extends IntegrationTest
{

    public function setUp()
    {
        parent::setUp();
    }

    protected function prepareUser() {

        $this->test_data->user  = Factory_User::create() ;

        $feature = Factory_OwnerFeature::create( array(
            'uid'          => $this->test_data->user->uid,
            'feature_code' => 'ebay'
        ) );
    }

    public function testUIParamsAreSetToMetadata() {
        $this->prepareUser() ;
        $upload_session = uniqid();

        $sessid = get_sessid_for_user( $this->test_data->user ) ;

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
            'source_lang' => 'en-US',
            'target_lang' => 'it-IT',
            'file_name' => 'amex-test.docx.xlf',
            'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'pretranslate_100' => 1,
            'job_subject' => 'general',
            'file_name' => 'amex-test.docx.xlf',
            'upload_session' => $upload_session,
            'private_tm_key' => '23154e7f6f93a838f7bc',
            'word_count' => 42,
            'project_type' => 'HT',
            'due_date' => '2002-01-01',

            'files' => array( $file ),
            'cookies' => array(
                array( 'PHPSESSID' , $sessid )
            )
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );

        $metadata = $project->getMetadataAsKeyValue() ;

        $this->assertEquals('HT', $metadata['project_type'] ) ;
        $this->assertEquals('42', $metadata['word_count'] ) ;
    }

}