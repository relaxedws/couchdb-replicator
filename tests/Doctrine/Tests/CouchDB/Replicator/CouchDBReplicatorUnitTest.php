<?php

namespace Doctrine\Tests\CouchDB\Replicator;

use Doctrine\CouchDB\Replicator\CouchDBReplicator;

class CouchDBReplicatorUnitTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers CouchDBReplicator::getPeerInfo
     */
    public function testGetPeerInfo()
    {
        $source = $this->getMockBuilder('Doctrine\CouchDB\CouchDBClient')
            ->disableOriginalConstructor()
            ->getMock();
        $target = $this->getMockBuilder('Doctrine\CouchDB\CouchDBClient')
            ->disableOriginalConstructor()
            ->getMock();
        $target
            ->expects($this->once())
            ->method('getDatabase');
        $target
            ->expects($this->once())
            ->method('getDatabaseInfo')
            ->will($this->returnValue(array(
                'db_name' => 'foo',
                'instance_start_time' => '123',
                'update_seq' => '456'
            )));

        $replicator = new CouchDBReplicator($source, $target);
        $replicator->getPeerInfo($target);
    }

    /**
     * @covers CouchDBReplicator::getReplicationID
     */
    public function testGetReplicationId()
    {
        $source = $this->getMockBuilder('Doctrine\CouchDB\CouchDBClient')
            ->disableOriginalConstructor()
            ->getMock();
        $source
            ->expects($this->once())
            ->method('getDatabase')
            ->will($this->returnValue('foo'));

        $target = $this->getMockBuilder('\Doctrine\CouchDB\CouchDBClient')
            ->disableOriginalConstructor()
            ->getMock();
        $target
            ->expects($this->once())
            ->method('getDatabase')
            ->will($this->returnValue('bar'));

        $replicator = new CouchDBReplicator($source, $target);

        $id = md5('foo' . 'bar' . FALSE . FALSE);
        $this->assertEquals($id, $replicator->getReplicationID(), 'Replication ID is correct.');
    }
}
