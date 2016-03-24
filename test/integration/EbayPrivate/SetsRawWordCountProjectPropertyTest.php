<?php

class SetsRawWordCountProjectPropertyTest extends IntegrationTest {

    function setUp() {
        $this->test_data->user = Factory_User::create();

        $feature = Factory_OwnerFeature::create( array(
                'uid'          => $this->test_data->user->uid,
                'feature_code' => 'ebay'
        ) );

        $this->test_data->api_key = Factory_ApiKey::create( array(
                'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
                "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
                "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }


    function test_sets_raw_word_count_type() {
        $result = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers,
                'files'   => array(
                        test_file_path( 'xliff/sdlxliff-with-mrk-and-note.xlf.sdlxliff' )
                ),
                'params'  => array(
                        'project_type' => 'MT',
                        'source_lang'   => 'en',
                        'target_lang'   => 'it'
                )
        ) );

        // check
        $project = Projects_ProjectDao::findById( $result->id_project );

        $metadata = $project->getMetadataAsKeyValue();

        $this->assertTrue( array_key_exists('word_count_type', $metadata) );
        $this->assertEquals('raw', $metadata['word_count_type']);

    }

}