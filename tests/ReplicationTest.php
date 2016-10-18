<?php


namespace Relaxed\Replicator\Test;

use Doctrine\CouchDB\HTTP\HTTPException;
use Doctrine\CouchDB\HTTP\Response;
use Relaxed\Replicator\ReplicationTask;
use Relaxed\Replicator\Replication;

class ReplicationTest extends \PHPUnit_Framework_TestCase
{
    protected $source = null;
    protected $target = null;
    protected $response = null;

    public function setUp()
    {
        $this->source = $this->getMockBuilder('Doctrine\CouchDB\CouchDBClient')
            ->disableOriginalConstructor()
            ->getMock();
        $this->target = $this->getMockBuilder('Doctrine\CouchDB\CouchDBClient')
            ->disableOriginalConstructor()
            ->getMock();

        $this->response=new Response(200, array(), array('reason' => 'someReasonAsIAmTesting'), true);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Source not reachable.
     */
    Public function testVerifyPeersRaisesExceptionWhenSourceIsNotReachable()
    {
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->source->expects($this->once())
            ->method('getDatabaseInfo')
            ->willThrowException(new HTTPException());
        $task = new ReplicationTask();

        $replication = new Replication($this->source, $this->target, $task);
        $replication->verifyPeers();

    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Target database does not exist.
     */
    Public function testVerifyPeersRaisesExceptionWhenTargetDoesNotExistAndIsNotToBeCreated()
    {
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->source->expects($this->once())
            ->method('getDatabaseInfo')
            ->willReturn(array(
                'db_name' => 'test_source_database',
                'instance_start_time' => '123',
                'update_seq' => '456'
            ));
        $this->target->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $this->response->status = 404;
        $this->target->expects($this->once())
            ->method('getDatabaseInfo')
            ->willThrowException(HTTPException::fromResponse(null, $this->response));
        $task = new ReplicationTask();

        $replication = new Replication($this->source, $this->target, $task);
        $replication->verifyPeers();

    }

    Public function testVerifyPeersWhenWhenTargetDoesNotExistAndIsToBeCreated()
    {
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->source->expects($this->once())
            ->method('getDatabaseInfo')
            ->willReturn(array(
                'db_name' => 'test_source_database',
                'instance_start_time' => '123',
                'update_seq' => '456'
            ));
        $this->target->expects($this->exactly(3))
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $this->response->status = 404;
        $this->target->expects($this->exactly(2))
            ->method('getDatabaseInfo')
            ->will($this->onConsecutiveCalls(
                $this->throwException(HTTPException::fromResponse('path', $this->response)),
                array(
                'db_name' => 'test_target_database',
                'instance_start_time' => '123',
                'update_seq' => '456'
            )));
        $this->target->expects($this->once())
            ->method('createDatabase')
            ->willReturn('');
        $task = new ReplicationTask();
        $task->setCreateTarget(true);

        $replication = new Replication($this->source, $this->target, $task);
        $response = $replication->verifyPeers();
        $this->assertEquals(\count($response), 2,
            'Source and target info not correctly returned.');
    }

    Public function testVerifyPeersWhenSourceAndTargetAlreadyExist()
    {
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->source->expects($this->once())
            ->method('getDatabaseInfo')
            ->willReturn(array(
                'db_name' => 'test_source_database',
                'instance_start_time' => '123',
                'update_seq' => '456'
            ));
        $this->target->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $this->target->expects($this->once())
            ->method('getDatabaseInfo')
            ->willReturn(array(
                'db_name' => 'test_target_database',
                'instance_start_time' => '123',
                'update_seq' => '456'
            ));
        $task = new ReplicationTask();

        $replication = new Replication($this->source, $this->target, $task);
        list($sourceInfo, $targetInfo) = $replication->verifyPeers();

        $this->assertArrayHasKey("update_seq", $sourceInfo, 'Source info not correctly returned.');
        $this->assertArrayHasKey("instance_start_time", $sourceInfo, 'Source info not correctly returned.');
        $this->assertArrayHasKey("update_seq", $targetInfo, 'Target info not correctly returned.');
        $this->assertArrayHasKey("instance_start_time", $targetInfo, 'Target info not correctly returned.');
    }

    public function testGenerateReplicationId()
    {
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->target->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $task = new ReplicationTask();
        $expectedId = md5(
            'test_source_database' .
            'test_target_database' .
            \var_export(null, true) .
            '0' .
            '0' .
            null .
            null .
            'all_docs' .
            '10000'
        );
        $replication = new Replication($this->source, $this->target, $task);
        $this->assertEquals($expectedId, $replication->generateReplicationId(), 'Incorrect Replication Id Generation.');
    }

    public function testGenerateReplicationIdWithFilter()
    {
        $filterCode = "function(doc, req) { if (doc._deleted) { return true; } if(!doc.clientId) { return false; } }";
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->response->status = 200;
        $this->response->body = array('filters' => array('testFilterFunction' => $filterCode));
        $this->source->expects($this->once())
            ->method('findDocument')
            ->willReturn($this->response);
        $this->target->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $task = new ReplicationTask(
            null,false,'test/testFilterFunction', [], true,
            null, 10000, 10000, false, 'all_docs', 0
        );
        $expectedId = md5(
            'test_source_database' .
            'test_target_database' .
            \var_export(null, true) .
            '1' .
            '0' .
            'test/testFilterFunction' .
            $filterCode .
            'all_docs' .
            '10000'
        );
        $replication = new Replication($this->source, $this->target, $task);
        $this->assertEquals($expectedId, $replication->generateReplicationId(), 'Incorrect Replication Id Generation.');
    }

    public function testGenerateReplicationIdWithDocIds()
    {
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->target->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $task = new ReplicationTask(
            null, false, '_doc_ids', [], true,
            array(1, 3, 2, 'jfajs57s868'),
            10000, 10000, false, 'all_docs', 0
        );
        $expectedId = md5(
            'test_source_database' .
            'test_target_database' .
            \var_export(array('jfajs57s868', 1, 2, 3), true) .
            '1' .
            '0' .
            '_doc_ids' .
            '' .
            'all_docs' .
            '10000'
        );
        $replication = new Replication($this->source, $this->target, $task);
        $this->assertEquals($expectedId, $replication->generateReplicationId(), 'Incorrect Replication Id Generation.');
    }

    public function testGetReplicationLog()
    {
        $sourceResponse = $this->response;
        $sourceResponse->body = array("log" => "source_replication_log");
        $sourceResponse->status = 200;
        $this->source->expects($this->once())
            ->method('findDocument')
            ->willReturn($sourceResponse);

        $targetResponse = clone $this->response;
        $targetResponse->status = 404;

        $this->target->expects($this->once())
        ->method('findDocument')
        ->willReturn($targetResponse);

        $task = new ReplicationTask();
        $replication = new Replication($this->source, $this->target, $task);
        list($sourceLog, $targetLog) = $replication->getReplicationLog();
        $this->assertEquals($sourceLog, array("log" => "source_replication_log"));
        $this->assertEquals($targetLog, null);

    }

    /**
     * @expectedException \Doctrine\CouchDB\HTTP\HTTPException
     */
    public function testGetReplicationLogRaisesExceptionWhenPeerNotReachable()
    {
        $this->response->status = 500;
        $this->source->expects($this->once())
            ->method('findDocument')
            ->willThrowException(HTTPException::fromResponse(null, $this->response));

        $task = new ReplicationTask();
        $replication = new Replication($this->source, $this->target, $task);
        list($sourceLog, $targetLog) = $replication->getReplicationLog();
        $this->assertEquals($targetLog, array("log" => "source_replication_log"));
        $this->assertEquals($sourceLog, null);

    }

    /**
     * @dataProvider replicationLogsProvider
     */
    public function testCompareReplicationLogs($sourceLog, $targetLog, $expectedSequence)
    {
        $task = new ReplicationTask();
        $replication = new Replication($this->source, $this->target, $task);
        $this->assertEquals($expectedSequence, $replication->compareReplicationLogs($sourceLog,$targetLog));

    }

    public function replicationLogsProvider()
    {
        return array(
            array(
                array (
                    '_id' => '_local/b3e44b920ee2951cb2e123b63044427a',
                    '_rev' => '0-8',
                    'history' =>
                        array (
                            0 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 5,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 5,
                                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                                    'start_last_seq' => 3,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                ),
                            1 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 1,
                                    'docs_written' => 1,
                                    'end_last_seq' => 3,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                    'missing_checked' => 1,
                                    'missing_found' => 1,
                                    'recorded_seq' => 3,
                                    'session_id' => '11a79cdae1719c362e9857cd1ddff09d',
                                    'start_last_seq' => 2,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                ),
                            2 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 2,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 2,
                                    'session_id' => '77cdf93cde05f15fcb710f320c37c155',
                                    'start_last_seq' => 0,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                ),
                        ),
                    'replication_id_version' => 3,
                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                    'source_last_seq' => 5,
                ),
                array (
                    '_id' => '_local/b3e44b920ee2951cb2e123b63044427a',
                    '_rev' => '0-8',
                    'history' =>
                        array (
                            0 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 5,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 5,
                                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                                    'start_last_seq' => 3,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                ),
                            1 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 1,
                                    'docs_written' => 1,
                                    'end_last_seq' => 3,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                    'missing_checked' => 1,
                                    'missing_found' => 1,
                                    'recorded_seq' => 3,
                                    'session_id' => '11a79cdae1719c362e9857cd1ddff09d',
                                    'start_last_seq' => 2,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                ),
                            2 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 2,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 2,
                                    'session_id' => '77cdf93cde05f15fcb710f320c37c155',
                                    'start_last_seq' => 0,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                ),
                        ),
                    'replication_id_version' => 3,
                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                    'source_last_seq' => 5,
                ),
                5
            ),
            array(
                array (
                    '_id' => '_local/b3e44b920ee2951cb2e123b63044427a',
                    '_rev' => '0-8',
                    'history' =>
                        array (
                            0 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 5,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 5,
                                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                                    'start_last_seq' => 3,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                ),
                            1 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 1,
                                    'docs_written' => 1,
                                    'end_last_seq' => 3,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                    'missing_checked' => 1,
                                    'missing_found' => 1,
                                    'recorded_seq' => 3,
                                    'session_id' => '11a79cdae1719c362e9857cd1ddff09d',
                                    'start_last_seq' => 2,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                ),
                            2 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 2,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 2,
                                    'session_id' => '77cdf93cde05f15fcb710f320c37c155',
                                    'start_last_seq' => 0,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                ),
                        ),
                    'replication_id_version' => 3,
                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                    'source_last_seq' => 5,
                ),
                array (
                    '_id' => '_local/b3e44b920ee2951cb2e123b63044427a',
                    '_rev' => '0-8',
                    'history' =>
                        array (
                            0 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 5,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 5,
                                    'session_id' => 'cbbdafa70e0db5cb57d02a6b955',
                                    'start_last_seq' => 3,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                ),
                            1 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 1,
                                    'docs_written' => 1,
                                    'end_last_seq' => 3,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                    'missing_checked' => 1,
                                    'missing_found' => 1,
                                    'recorded_seq' => 3,
                                    'session_id' => '11a79cdae1719c362e9857cd1ddff09d',
                                    'start_last_seq' => 2,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                ),
                            2 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 2,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 2,
                                    'session_id' => '77cdf93cde05f15fcb710f320c37c155',
                                    'start_last_seq' => 0,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                ),
                        ),
                    'replication_id_version' => 3,
                    'session_id' => 'zzz34cbbdafa70e0db5cb57d02a6b955',
                    'source_last_seq' => 5,
                ),
                3,

            ),
            array(
                array (
                    '_id' => '_local/b3e44b920ee2951cb2e123b63044427a',
                    '_rev' => '0-8',
                    'history' =>
                        array (
                            0 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 5,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 5,
                                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                                    'start_last_seq' => 3,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:38 GMT',
                                ),
                            1 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 1,
                                    'docs_written' => 1,
                                    'end_last_seq' => 3,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                    'missing_checked' => 1,
                                    'missing_found' => 1,
                                    'recorded_seq' => 3,
                                    'session_id' => '11a79cdae1719c362e9857cd1ddff09d',
                                    'start_last_seq' => 2,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:12 GMT',
                                ),
                            2 =>
                                array (
                                    'doc_write_failures' => 0,
                                    'docs_read' => 2,
                                    'docs_written' => 2,
                                    'end_last_seq' => 2,
                                    'end_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                    'missing_checked' => 2,
                                    'missing_found' => 2,
                                    'recorded_seq' => 2,
                                    'session_id' => '77cdf93cde05f15fcb710f320c37c155',
                                    'start_last_seq' => 0,
                                    'start_time' => 'Thu, 10 Oct 2013 05:56:04 GMT',
                                ),
                        ),
                    'replication_id_version' => 3,
                    'session_id' => 'd5a34cbbdafa70e0db5cb57d02a6b955',
                    'source_last_seq' => 5,
                ),
                null,
                0
            ),
            array(
                null,
                null,
                0
            )
        );
    }

    /**
     * Test the mapping done in getMapping
     *
     * @dataProvider changesFeedProvider
     */
    public function testGetMapping($changes, $continuous, $expected)
    {
        $task = new ReplicationTask();
        $task->setContinuous($continuous);
        $replication = new Replication($this->source, $this->target, $task);
        $mapping = $replication->getMapping($changes);
        $this->assertEquals($expected, $mapping, 'Incorrect mapping in getMapping.');

    }

    public function changesFeedProvider()
    {
        $normal = array (
            'results' =>
                array (
                    0 =>
                        array (
                            'seq' => 14,
                            'id' => 'f957f41e',
                            'changes' =>
                                array (
                                    0 =>
                                        array (
                                            'rev' => '3-46a3',
                                        ),
                                ),
                            'deleted' => true,
                        ),
                    1 =>
                        array (
                            'seq' => 29,
                            'id' => 'ddf339dd',
                            'changes' =>
                                array (
                                    0 =>
                                        array (
                                            'rev' => '10-304b',
                                        ),
                                ),
                        ),
                    2 =>
                        array (
                            'seq' => 39,
                            'id' => 'f13bd08b',
                            'changes' =>
                                array (
                                    0 =>
                                        array (
                                            'rev' => '1-b35d',
                                        ),
                                        array(
                                            'rev' => '1-535d',
                                        )
                                ),
                        ),
                ),
            'last_seq' => 78,
        );
        $continuous = '{"seq":14,"id":"f957f41e","changes":[{"rev":"3-46a3"}],"deleted":true}
{"seq":29,"id":"ddf339dd","changes":[{"rev":"10-304b"}]}
{"seq":39,"id":"f13bd08b","changes":[{"rev":"1-b35d"},{"rev":"1-535d"}]}';

        $expected = array (
            'f957f41e' =>
                array (
                    0 => '3-46a3',
                ),
            'ddf339dd' =>
                array (
                    0 => '10-304b',
                ),
            'f13bd08b' =>
                array (
                    0 => '1-b35d',
                    1 => '1-535d',
                ),
        );
        return array(
            array(
                $normal,
                false,
                $expected
            ),
            array(
                $continuous,
                true,
                $expected
            )
        );
    }

    protected function tearDown()
    {
    }
}
