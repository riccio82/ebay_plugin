<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/07/16
 * Time: 17:45
 */

namespace Features\Ebay\Utils;

/**
 * TODO:
 * This is meant to be a temporary solution, the correct key should be passed at project
 * creation, remove the need for this override as soon as possible.
 */
if ( \INIT::$ENV == 'test' || \INIT::$ENV == 'development' ) {
    define('HARDCODED_TM_KEY', '94f38f04b201939b25be');
} else {
    define('HARDCODED_TM_KEY', 'bbd1221285eeff616428');
}

class PrivateTmKeys {

    private $lang_keys_map = array(
            'en-US|de-DE' => HARDCODED_TM_KEY,
            'en-US|es-MX' => HARDCODED_TM_KEY,
            'en-US|es-CO' => HARDCODED_TM_KEY,
            'en-US|fr-FR' => HARDCODED_TM_KEY,
            'en-US|it-IT' => HARDCODED_TM_KEY,
            'en-US|pt-BR' => HARDCODED_TM_KEY,
            'en-US|ru-RU' => HARDCODED_TM_KEY,
            'de-DE|en-US' => HARDCODED_TM_KEY,
            'de-DE|fr-FR' => HARDCODED_TM_KEY,
            'de-DE|it-IT' => HARDCODED_TM_KEY,
    );

    private $source;
    private $targets;

    public static function getHardCodedKey() {
        return HARDCODED_TM_KEY;
    }

    public function __construct($source, $targets) {
        $this->source = $source;
        $this->targets = $targets ;
    }

    public function getKeys() {
        $keys = array();

        foreach( $this->targets as $target ) {
            $comb = "{$this->source}|$target";
            if ( array_key_exists($comb, $this->lang_keys_map) ) {
                array_push($keys, array(
                        'key' => $this->lang_keys_map[ $comb ],
                        'name' => null,
                        'r' => true,
                        'w' => true
                ));
            }
        }

        return $keys ;
    }

}