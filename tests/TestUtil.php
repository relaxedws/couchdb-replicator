<?php

namespace Relaxed\Replicator\Test;

class TestUtil
{
    static public function getSourceTestDatabase()
    {
        if (isset($GLOBALS['RELAXED_REPLICATOR_SOURCE_DATABASE'])) {
            return $GLOBALS['RELAXED_REPLICATOR_SOURCE_DATABASE'];
        }
        return 'relaxed_replicator_source_test_database';
    }

    static public function getTargetTestDatabase()
    {
        if (isset($GLOBALS['RELAXED_REPLICATOR_TARGET_DATABASE'])) {
            return $GLOBALS['RELAXED_REPLICATOR_TARGET_DATABASE'];
        }
        return 'relaxed_replicator_target_test_database';
    }

}
