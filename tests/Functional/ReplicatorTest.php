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
        $this->replicator->startReplication();
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
        $response = $updater->execute();

        // Start the replication. Print the status to STDOUT and also get the
        // details in an array.
        $repDetails = $this->replicator->startReplication(true, true);

        // Check the replication report returned by the replicator.
        if ($isContinuous) {
            $this->assertEquals(2, $repDetails['successCount']);
        }
        $this->assertArrayHasKey('multipartResponse', $repDetails);
        $this->assertEquals(2, count($repDetails['multipartResponse']));
        $this->assertArrayHasKey($id, $repDetails['multipartResponse']);
        $this->assertArrayHasKey($id . '2', $repDetails['multipartResponse']);
        // The 1-abc revision of the doc is posted.
        $this->assertEquals(
            '1-abc',
            $repDetails['multipartResponse'][$id][0]['rev']
        );
        $this->assertEquals(
            '1-lala',
            $repDetails['multipartResponse'][$id . '2'][0]['rev']
        );
        // Successful bulk posting.
        $this->assertEquals(201, $repDetails['bulkResponse'][$id][0]);
        $this->assertEquals(0, count($repDetails['errorResponse']));

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
            2,
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
    }

    public function tearDown()
    {
        $this->sourceClient->deleteDatabase($this->getSourceTestDatabase());
        $this->sourceClient->deleteDatabase($this->getTargetTestDatabase());
    }

}