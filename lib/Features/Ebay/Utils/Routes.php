<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/21/16
 * Time: 12:05 PM
 */
namespace Features\Ebay\Utils;

use \INIT ;

class Routes {

    public static function staticBuild( $file, $options=array() ) {
        $host = \Routes::httpHost($options);
        return $host . "/plugins/ebay/static/build/$file" ;
    }

    public static function analyze( $params, $options=array()) {
        $params = \Utils::ensure_keys( $params, array(
                'project_name', 'id_project', 'password'
        ) );

        $host = \Routes::httpHost($options);

        return $host . "/plugins/ebay/analyze/" .
            $params[ 'project_name' ] . "/" .
            $params[ 'id_project' ] . "-" .
            $params[ 'password' ];
    }
}