<?php

namespace Relaxed\Replicator\Test;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\SocketClient;
use Relaxed\Replicator\ReplicationTask;

abstract class ReplicatorFunctionalTestBase extends \PHPUnit_Framework_TestCase
{
    public function getSourceTestDatabase()
    {
        return TestUtil::getSourceTestDatabase();
    }

    public function getTargetTestDatabase()
    {
        return TestUtil::getTargetTestDatabase();
    }

    public function getSourceCouchDBClient()
    {
        return new CouchDBClient(
            new SocketClient(),
            $this->getSourceTestDatabase()
        );
    }

    public function getTargetCouchDBClient()
    {
        return new CouchDBClient(
            new SocketClient(),
            $this->getTargetTestDatabase()
        );
    }

    public function getReplicationTask()
    {
        return new ReplicationTask();
    }
    
}