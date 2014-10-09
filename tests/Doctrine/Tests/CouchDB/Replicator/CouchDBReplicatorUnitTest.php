<?php

namespace Doctrine\Tests\CouchDB\Replicator;

use Doctrine\CouchDB\Replicator\CouchDBReplicator;

class CouchDBReplicatorUnitTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {

    }

    public function testStart()
    {
        $source = $this->getMock('Doctrine\CouchDB\CouchDBClient');
        $target = $this->getMock('Doctrine\CouchDB\CouchDBClient');

        $replicator = new CouchDBReplicator($source, $target);
        $replicator->start();
    }
}
