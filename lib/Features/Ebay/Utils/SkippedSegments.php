<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/12/2016
 * Time: 17:04
 */

namespace Features\Ebay\Utils;


use Exception;
use Jobs\MetadataDao;
use Chunks_ChunkStruct ;
use Jobs\MetadataStruct;
use Utils;


class SkippedSegments
{

    const SKIPPED_SEGMENT = "XXXX";
    const METADATA_COUNT_KEY = 'ebay_skipped_segments_count' ;
    const METADATA_LIST_KEY = 'ebay_skipped_segments_list' ;

    public static function getCount( Chunks_ChunkStruct $chunk ) {
        $dao = new MetadataDao() ;
        $struct = $dao->get($chunk->id, $chunk->password, self::METADATA_COUNT_KEY ) ;

        return $struct ? (int) $struct->value : 0 ;
    }

    public static function updateSkippedSegmentsCount( Chunks_ChunkStruct $chunk,
                                                       $old_translation,
                                                       $new_translation,
                                                       $propagated_ids ) {

        /**
         * This code increments and decrements the skipped segments count.
         * It doesn't take into account the account the case for segments that are pretranslated with a skipped string.
         */

        if ( self::isSkipped( $old_translation ) && ! self::isSkipped( $new_translation ) ) {
            self::decrementCount( $chunk, $new_translation['id_segment'], $propagated_ids ) ;
        }

        elseif ( ! self::isSkipped( $old_translation ) && self::isSkipped( $new_translation ) ) {
            self::incrementCount( $chunk, $new_translation['id_segment'], $propagated_ids );
        }

    }

    public static function getDataForStats( Chunks_ChunkStruct $chunk ) {
        $dao = new \Segments_SegmentDao() ;

        $response = array();
        $response ['ebay_plugin_total_segments_count']   = $dao->countByChunk( $chunk );
        $response ['ebay_plugin_skipped_segments_count'] = SkippedSegments::getCount( $chunk ) ;
        return $response ;
    }

    public static function postJobSplitted( $id_job, $password ) {
        $allChunks = \Chunks_ChunkDao::getByJobID( $id_job ) ;

        $dao = new MetadataDao();
        $allSkipped = $dao->getByIdJob( $id_job, self::METADATA_LIST_KEY ) ;

        $fullList = array();

        foreach( $allSkipped as $struct ) {
            if ( $struct->password == $password ) {
                if ( $struct->value ) $fullList = json_decode( $struct->value, true );
            }
        }

        foreach( $allChunks as $chunk ) {
            $new_list = array_filter( $fullList, function( $id_segment ) use ( $chunk ) {
                return (int) $chunk->job_first_segment <= (int) $id_segment && (int) $chunk->job_last_segment >= (int) $id_segment ;
            });
            self::setSkippedIds( $chunk, $new_list ) ;
        }
    }

    public static function postJobMerged( Chunks_ChunkStruct $chunk ) {
        $dao = new MetadataDao();
        $allSkipped = $dao->getByIdJob( $chunk->id, self::METADATA_LIST_KEY ) ;
        /**
         * @var MetadataStruct[]
         */
        $others = array();
        $add_list = array();

        if ( $allSkipped ) {
            foreach( $allSkipped as $metadata ) {
                if ( $metadata->password == $chunk->password ) {
                    $add_list = json_decode( $metadata->value, true );
                }

                else {
                    $others[] = $metadata ;
                }
            }
        }

        if ( !empty( $others ) ) {
            foreach($others as $struct) {
                $list = json_decode( $struct->value, true ) ;
                $add_list = array_values( array_unique( array_merge( $add_list, $list ) ) ) ;

                $dao->delete($struct->id_job, $struct->password, self::METADATA_LIST_KEY ) ;
                $dao->delete($struct->id_job, $struct->password, self::METADATA_COUNT_KEY ) ;
            }
        }

        self::setSkippedIds( $chunk, $add_list ) ;
    }

    private static function incrementCount( Chunks_ChunkStruct $chunk, $id_segment, $propagated_ids ) {
        if ( empty( $propagated_ids ) ) {
            $add_list = array( $id_segment ) ;
        } else {
            $add_list = $propagated_ids ;
            array_push($add_list, $id_segment);
        }

        $list = self::getSkippedIds( $chunk ) ;
        $new_list = array_merge($list, $add_list) ;
        self::setSkippedIds( $chunk, $new_list ) ;
    }

    private static function decrementCount( Chunks_ChunkStruct $chunk, $id_segment, $propagated_ids ) {
        if ( empty( $propagated_ids ) ) {
            $remove_list = array( $id_segment ) ;
        } else {
            $remove_list = $propagated_ids ;
            array_push($remove_list, $id_segment);
        }

        $list = self::getSkippedIds( $chunk ) ;
        $new_list = array_diff($list, $remove_list) ;
        self::setSkippedIds( $chunk, $new_list ) ;
    }

    public static function isSkipped( $translation ) {
        return $translation['translation'] == self::SKIPPED_SEGMENT ;
    }

    private static function getSkippedIds( Chunks_ChunkStruct $chunk ) {
        $dao = new MetadataDao();
        $struct = $dao->get($chunk->id, $chunk->password, self::METADATA_LIST_KEY ) ;

        if ( $struct && $struct->value ) {
            return json_decode( $struct->value, true ) ;
        }
        else {
            return array();
        }
    }

    private static function setSkippedIds( Chunks_ChunkStruct $chunk, $list ) {
        $value = json_encode( array_values ( array_unique( $list ) ) ) ;

        if ( is_null( $value ) ) {
            try {
                Utils::raiseJsonExceptionError();
            } catch ( Exception $e ) {
            }
            $value = json_encode( [] ) ;
        }

        $dao = new MetadataDao();
        $dao->set( $chunk->id, $chunk->password, self::METADATA_COUNT_KEY, count( $list ) ) ;
        $dao->set( $chunk->id, $chunk->password, self::METADATA_LIST_KEY, $value ) ;
    }
}