<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 12:17 PM
 */

namespace Features\Ebay ;

use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\Context;
use TaskRunner\Commons\QueueElement ;

class EditDistancePropagationWorker extends \TaskRunner\Commons\AbstractWorker {
    protected $_queueHandler;
    protected $_myContext;

    public function __construct( \AMQHandler $queueHandler ) {
        $this->_queueHandler = $queueHandler;
    }

    public function process( AbstractElement $queueElement, Context $queueContext ) {
        $this->_doLog( $queueElement->params );

        $out = \Translations_SegmentTranslationDao::updateEditDistanceForPropagation(
                $queueElement->params
        );

    }

    protected function _checkForReQueueEnd( QueueElement $queueElement ){

    }
}