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

    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd($queueElement) ;

        $this->_checkDatabaseConnection();

        $this->_doLog( $queueElement->params );

        $out = \Translations_SegmentTranslationDao::updateEditDistanceForPropagation(
                $queueElement->params
        );

    }

}