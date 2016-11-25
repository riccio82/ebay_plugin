<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 12:17 PM
 */

namespace Features\Ebay ;

use \TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\QueueElement ;

class EditDistancePropagationWorker extends AbstractWorker {
    protected $_queueHandler;

    public function process( AbstractElement $queueElement ) {

        $this->_checkDatabaseConnection();

        $this->_doLog( $queueElement->params );

        $out = \Translations_SegmentTranslationDao::updateEditDistanceForPropagation(
                $queueElement->params
        );

    }

    protected function _checkForReQueueEnd( QueueElement $queueElement ){

    }
}