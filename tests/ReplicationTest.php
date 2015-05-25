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

        $this->response=new Response(0,array(),array('reason' => 'someReasonAsIamTesting'), true);
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
        $response = $replication->verifyPeers();
        $this->assertEquals(\count($response), 2,
            'Source and target info not correctly returned.');
    }

    protected function tearDown()
    {
    }
}
