<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/22/16
 * Time: 11:47 AM
 */
class ValidateMetadataTest extends IntegrationTest {

    function setUp() {
        $this->test_data = new StdClass();
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


    function test_due_date_is_valid() {
        $result = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers,
                'params'  => array(
                        'metadata' => urlencode('{"due_date" : "2016-03-21T06:01:00+00:00"}')
                )
        ) );

        $this->assertEquals('OK', $result->status);
    }

    function test_due_date_is_not_valid() {
        $result = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers,
                'params'  => array(
                        'metadata' => '{"due_date" : "foo bar"}'
                )
        ) );

        $this->assertEquals('FAIL', $result->status);
        $this->assertContains('Due date is not valid', $result->debug);
    }
}