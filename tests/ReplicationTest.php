<?php


namespace Relaxed\Replicator;

use Doctrine\CouchDB\CouchDBClient;


class ReplicationTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $client = CouchDBClient::create(array('dbname' => 'some_random_database1'));
        $client->createDatabase('test_source_database');
        $client->createDatabase('test_target_database');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Source not reachable.
     */
    Public function testVerifyPeersRaisesExceptionWhenSourceIsNotReachable()
    {
        $source = CouchDBClient::create(array('dbname' => 'some_random_database1'));
        $target = CouchDBClient::create(array('dbname' => 'some_random_database2'));
        $task = new ReplicationTask();

        $replication = new Replication($source, $target, $task);
        $replication->verifyPeers();

    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Target database does not exist.
     */
    Public function testVerifyPeersRaisesExceptionWhenTargetDoesNotExistAndIsNotToBeCreated()
    {
        $source = CouchDBClient::create(array('dbname' => 'test_source_database'));
        $target = CouchDBClient::create(array('dbname' => 'some_random_database'));
        $task = new ReplicationTask();
        $replication = new Replication($source, $target, $task);
        $replication->verifyPeers();

    }

    /**
     *
     */
    Public function testVerifyPeers()
    {
        $source = CouchDBClient::create(array('dbname' => 'test_source_database'));
        $target = CouchDBClient::create(array('dbname' => 'test_target_database'));
        $task = new ReplicationTask();
        $replication = new Replication($source, $target, $task);
        $response = $replication->verifyPeers();
        $this->assertEquals(count($response), 2);
    }

    protected function tearDown()
    {
        $client = CouchDBClient::create(array('dbname' => 'some_random_database1'));
        $client->deleteDatabase('test_source_database');
        $client->deleteDatabase('test_target_database');
    }
}
