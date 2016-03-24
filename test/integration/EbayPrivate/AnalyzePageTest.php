<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/21/16
 * Time: 1:58 PM
 */
class AnalyzePageTest extends IntegrationTest {

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

    function tests_analyze_page_is_custom() {
        $result = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers,
                'files'   => array(
                        test_file_path( 'xliff/sdlxliff-with-mrk-and-note.xlf.sdlxliff' )
                ),
                'params'  => array(
                        'metadata' => '{"project_type" : "MT"}',
                        'source_lang'   => 'en',
                        'target_lang'   => 'it',
                        'name' => 'foo',
                )
        ) );

        $this->assertEquals( \Features\Ebay\Utils\Routes::analyze(
            array(
                'id_project' => $result->id_project,
                'password' => $result->project_pass,
                'project_name' => 'foo'
            ), array( 'http_host' => 'http://localhost')
        ), $result->analyze_url );
    }
}