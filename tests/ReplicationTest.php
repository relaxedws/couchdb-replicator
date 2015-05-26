<?php


namespace Relaxed\Replicator;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\HTTPException;
use Doctrine\CouchDB\HTTP\Response;


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

        $this->response=new Response(0,array(),array('reason' => 'someReasonAsIAmTesting'), true);
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

    /**
     *
     */
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

    /**
     *
     */
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

    /**
     *
     */
    public function testGenerateReplicationIdWithFilter()
    {
        $filterCode = "function(doc, req) { if (doc._deleted) { return true; } if(!doc.clientId) { return false; } }";
        $this->source->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_source_database');
        $this->source->expects($this->once())
            ->method('getDesignDocument')
            ->willReturn(array('filters'
            => array('testFilterFunction'
                => $filterCode)));
        $this->target->expects($this->once())
            ->method('getDatabase')
            ->willReturn('test_target_database');
        $task = new ReplicationTask(
            null,false,'test/testFilterFunction', true,
            null, 10000, false, 'all_docs', 0
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
            null,false,'_doc_ids', true,
            array(1, 2, 3, 'jfajs57s868'),
            10000, false, 'all_docs', 0
        );
        $expectedId = md5(
            'test_source_database' .
            'test_target_database' .
            \var_export(array(1, 2, 3, 'jfajs57s868'), true) .
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

    protected function tearDown()
    {
    }
}
