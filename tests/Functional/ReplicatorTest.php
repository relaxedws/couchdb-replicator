<?php

namespace Relaxed\Replicator\Test\Functional;

use Relaxed\Replicator\Replicator;
use Relaxed\Replicator\Test\ReplicatorFunctionalTestBase;

class ReplicatorTest extends ReplicatorFunctionalTestBase
{
    protected $sourceClient;
    protected $targetClient;
    protected $replicationTask;
    protected $replicator;

    public function setUp()
    {
        $this->sourceClient = $this->getSourceCouchDBClient();
        $this->targetClient = $this->getTargetCouchDBClient();
        $this->replicationTask = $this->getReplicationTask();
        // Disable default Heartbeat and use timeout. This is to make the
        // connection terminate quickly when there are no changes happening on
        // the source in case of the continuous replication.
        $this->replicationTask->setHeartbeat(null);
        // Timeout to be used in the case of continuous replication. It's in
        // milliseconds.
        $this->replicationTask->setTimeout(100);

        // Create the source and the target databases.
        $this->sourceClient->createDatabase($this->getSourceTestDatabase());
        $this->sourceClient->createDatabase($this->getTargetTestDatabase());

        $this->replicator = new Replicator(
            $this->sourceClient,
            $this->targetClient,
            $this->replicationTask
        );
    }


    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Source is Null.
     */
    public function testStartReplicationThrowsExceptionOnNullSource()
    {
        $this->replicator = new Replicator(
            null,
            $this->targetClient,
            $this->replicationTask
        );
        $this->replicator->startReplication();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Target is Null.
     */
    public function testStartReplicationThrowsExceptionOnNullTarget()
    {
        $this->replicator = new Replicator(
            $this->sourceClient,
            null,
            $this->replicationTask
        );
        $this->replicator->startReplication();
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Task is Null.
     */
    public function testStartReplicationThrowsExceptionOnNullTask()
    {
        $this->replicator = new Replicator(
            $this->sourceClient,
            $this->targetClient,
            null
        );
        $this->replicator->startReplication();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Source not reachable.
     */
    public function testStartReplicationThrowsExceptionWhenSourceDoesNotExist()
    {
        // Delete the source database.
        $this->sourceClient->deleteDatabase($this->getSourceTestDatabase());
        try {
            $this->replicator->startReplication();
        } catch (\Exception $e) {
            // Restore state before throwing the raised exception.
            $this->sourceClient->createDatabase($this->getSourceTestDatabase());
            throw $e;
        }

    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Target database does not exist.
     */
    public function testStartReplicationThrowsExceptionWhenTargetDoesNotExist()
    {
        // Delete the target database.
        $this->targetClient->deleteDatabase($this->getTargetTestDatabase());
        try {
            $this->replicator->startReplication();
        } catch (\Exception $e) {
            // Restore state before throwing the raised exception.
            $this->targetClient->createDatabase($this->getTargetTestDatabase());
            throw $e;
        }

    }

    public function testTargetCreation()
    {
        // Delete the target database.
        $this->targetClient->deleteDatabase($this->getTargetTestDatabase());
        // Enable target creation.
        $this->replicationTask->setCreateTarget(true);
        $this->replicator->setTask($this->replicationTask);
        // Start the replication.
        $this->replicator->startReplication();

        $data = $this->targetClient->getDatabaseInfo();

        // The target should have been created.
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('db_name', $data);
        $this->assertEquals($this->getTargetTestDatabase(), $data['db_name']);

    }

    public function isContinuousReplicationProvider()
    {
        return array(
            // Normal replication.
            array(false),
            // Continuous replication.
            array(true)
        );
    }

    /**
     * @dataProvider isContinuousReplicationProvider
     */
    public function testReplicationWithoutAttachments($isContinuous)
    {   // Set the replication type.
        $this->replicationTask->setContinuous($isContinuous);

        // Add three docs to the source db.
        for ($i = 0; $i < 3; $i++) {
            list($id, $rev) = $this->sourceClient->putDocument(
                array("foo" => "bar" . var_export($i, true)),
                'id' . var_export($i, true)
            );
        }
        $this->replicator->startReplication(true, true);
        // Fetch the documents.
        $response = $this->targetClient->findDocuments(
            array('id0', 'id1', 'id2')
        );
        $this->assertInternalType('array', $response->body);
        $body = $response->body['rows'];
        $this->assertEquals(3, count($body));
        $this->assertArrayHasKey('id', $body[0]);
        $this->assertEquals('id0', $body[0]['id']);
        $this->assertEquals('id1', $body[1]['id']);
        $this->assertEquals('id2', $body[2]['id']);
    }

    public function testFilteredReplication()
    {
        // Add four docs to the source db. Replicate only id1 and id3 for
        // checking filtered Replication.
        for ($i = 1; $i <= 4; $i++) {
            list($id, $rev) = $this->sourceClient->putDocument(
                array("foo" => "bar" . var_export($i, true)),
                'id' . var_export($i, true)
            );
        }
        // Specify docs to be replicated. id2 and id4 should not be replicated.
        $this->replicationTask->setDocIds(
            array('id1', 'id3')
        );
        $this->replicator->setTask($this->replicationTask);
        $this->replicator->startReplication();
        $response = $this->targetClient->findDocuments(
            array('id1', 'id2', 'id3', 'id4')
        );
        $this->assertInternalType('array', $response->body);
        $body = $response->body['rows'];
        $this->assertEquals(4, count($body));
        $this->assertArrayHasKey('id', $body[0]);
        $this->assertEquals('id1', $body[0]['id']);
        $this->assertArrayHasKey('error', $body[1]);
        $this->assertEquals('not_found', $body[1]['error']);
        $this->assertEquals('id3', $body[2]['id']);
        $this->assertArrayHasKey('error', $body[3]);
        $this->assertEquals('not_found', $body[3]['error']);

    }

    /**
     * @dataProvider isContinuousReplicationProvider
     */
    public function testReplicationWithAttachments($isContinuous)
    {
        // Set the replication type.
        $this->replicationTask->setContinuous($isContinuous);
        // Test replication with attachments.
        // Doc id.
        $id = 'multiple_attachments';
        // Document with attachments.
        $docWithAttachment1 = array (
            '_id' => $id,
            '_rev' => '1-abc',
            '_attachments' =>
                array (
                    'foo.txt' =>
                        array (
                            'content_type' => 'text/plain',
                            'data' => 'VGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIHRleHQ=',
                        ),
                    'bar.txt' =>
                        array (
                            'content_type' => 'text/plain',
                            'data' => 'VGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIHRleHQ=',
                        ),
                ),
        );
        // Doc without any attachment. The id of both the docs is same.
        // So we will get two leaf revisions.
        $doc = array('_id' => $id, '_rev' => '1-bcd', 'foo' => 'bar');
        // Another document with attachments.
        $docWithAttachment2 = array (
            '_id' => $id . '2',
            '_rev' => '1-lala',
            '_attachments' =>
                array (
                    'abhi.txt' =>
                        array (
                            'content_type' => 'text/plain',
                            'data' => 'VGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIHRleHQ=',
                        ),
                    'dixon.txt' =>
                        array (
                            'content_type' => 'text/plain',
                            'data' => 'VGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIHRleHQ=',
                        ),
                    'druplicon.png' =>
                        array (
                            'content_type' => 'image/png',
                            'digest' => 'md5-ZTBlM2JmOWQwM2YwMjExNTgxMTVlMmUzNGExODA2OTk=',
                            'length' => '3905',
                            'data' => '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',
                        ),
                ),
        );

        // Add the documents to the test db using Bulk API.
        $updater = $this->sourceClient->createBulkUpdater();
        $updater->updateDocument($docWithAttachment1);
        $updater->updateDocument($docWithAttachment2);
        $updater->updateDocument($doc);
        // Set newedits to false to use the supplied _rev instead of assigning
        // new ones.
        $updater->setNewEdits(false);
        $updater->execute();

        // Start the replication. Print the status to STDOUT and also get the
        // details in an array.
        $repDetails = $this->replicator->startReplication(true, true);

        // Test the replication.
        // Fetch all the revisions of the first doc.
        $response = $this->targetClient->findRevisions($id, true);
        $this->assertObjectHasAttribute('body', $response);
        $this->assertInternalType('array', $response->body);
        $this->assertEquals(2, count($response->body));
        // Doc with _rev = 1-bcd
        $this->assertEquals(array('ok' => $doc), $response->body[0]);
        // Doc with _rev = 1-abc
        $this->assertEquals(3, count($response->body[1]['ok']));
        $this->assertEquals($id, $response->body[1]['ok']['_id']);
        $this->assertEquals('1-abc', $response->body[1]['ok']['_rev']);
        $this->assertEquals(
            2,
            count($response->body[1]['ok']['_attachments'])
        );
        $this->assertArrayHasKey(
            'foo.txt',
            $response->body[1]['ok']['_attachments']
        );
        $this->assertArrayHasKey(
            'bar.txt',
            $response->body[1]['ok']['_attachments']
        );
        // Fetch the second document. This has only one revision.
        $response = $this->targetClient->findDocument($id . '2');
        $this->assertObjectHasAttribute('body', $response);
        $this->assertInternalType('array', $response->body);
        $this->assertEquals(3, count($response->body));
        $this->assertEquals($id . '2', $response->body['_id']);
        $this->assertEquals('1-lala', $response->body['_rev']);
        $this->assertEquals(
            3,
            count($response->body['_attachments'])
        );
        $this->assertArrayHasKey(
            'abhi.txt',
            $response->body['_attachments']
        );
        $this->assertArrayHasKey(
            'dixon.txt',
            $response->body['_attachments']
        );
        $this->assertArrayHasKey(
            'druplicon.png',
            $response->body['_attachments']
        );
    }

    public function tearDown()
    {
        $this->sourceClient->deleteDatabase($this->getSourceTestDatabase());
        $this->sourceClient->deleteDatabase($this->getTargetTestDatabase());
    }

}