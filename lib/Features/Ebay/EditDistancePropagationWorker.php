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
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;

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

    public function _checkForReQueueEnd( QueueElement $queueElement ) {
        /**
         * check for loop re-queuing
         */
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {

            $msg = "\n\n Error Set Contribution  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
//            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }
    }

}