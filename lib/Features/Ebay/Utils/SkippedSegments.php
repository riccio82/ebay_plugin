<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/12/2016
 * Time: 17:04
 */

namespace Features\Ebay\Utils;


use Jobs\MetadataDao;
use Chunks_ChunkStruct ;


class SkippedSegments
{

    const SKIPPED_SEGMENT = "XXXX";
    const METADATA_KEY = 'ebay_skipped_segments_count' ;

    public static function getCount( Chunks_ChunkStruct $chunk ) {
        $dao = new MetadataDao() ;
        $struct = $dao->get($chunk->id, $chunk->password, self::METADATA_KEY ) ;

        return $struct ? (int) $struct->value : 0 ;
    }

    public static function setCount( Chunks_ChunkStruct $chunk, $count ) {
        $dao = new MetadataDao() ;
        $dao->set($chunk->id, $chunk->password, self::METADATA_KEY, $count ) ;
    }

    public static function updateSkippedSegmentsCount( \Chunks_ChunkStruct $chunk,
                                                       $old_translation,
                                                       $new_translation ) {

        /**
         * This code increments and decrements the skipped segments count.
         * It doesn't take into account the account the case for segments that are pretranslated with a skipped string.
         */

        if ( self::isSkipped( $old_translation ) && ! self::isSkipped( $new_translation ) ) {
            self::decrementCount( $chunk ) ;
        }

        elseif ( ! self::isSkipped( $old_translation) && self::isSkipped( $new_translation ) ) {
            self::incrementCount( $chunk );
        }

    }

    private static function incrementCount( \Chunks_ChunkStruct $chunk ) {
        $count = self::getCount( $chunk ) ;
        self::setCount( $chunk, $count + 1 ) ;
    }

    private static function decrementCount( \Chunks_ChunkStruct $chunk ) {
        $count = self::getCount( $chunk ) ;
        if ( $count > 0 ) {
            self::setCount( $chunk, $count - 1 ) ;
        }
    }

    private static function isSkipped( $translation ) {
        return $translation['translation'] == self::SKIPPED_SEGMENT ;
    }

}