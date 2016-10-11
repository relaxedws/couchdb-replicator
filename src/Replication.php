<?php

namespace Relaxed\Replicator;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\HTTPException;

/**
 * Class Replication
 * @package Relaxed\Replicator
 */
class Replication {

    /**
     * @var CouchDBClient
     */
    protected $source;

    /**
     * @var CouchDBClient
     */
    protected $target;

    /**
     * @var ReplicationTask
     */
    public $task;

    /**
     * @var \DateTime
     */
    protected $startTime;

    /**
     * @var \Datetime
     */
    protected $endTime;

    protected $sourceLog;

    protected $targetLog;

    /**
     * @param CouchDBClient $source
     * @param CouchDBClient $target
     * @param ReplicationTask $task
     */
    public function __construct(CouchDBClient $source, CouchDBClient $target, ReplicationTask $task)
    {
        $this->source = $source;
        $this->target = $target;
        $this->task = $task;
    }

    /**
     * Starts the replication process. $printStatus can be used to print the
     * status of the continuous replication to the STDOUT. The $getFinalReport
     * can be used to enable/disable returning of an array containing the
     * replication report in case of continuous replication. This is useful
     * when there are large number of documents. So when the replication is
     * continuous,to see the status set $printStatus to true and $getFinalReport
     * to false.
     *
     * @param bool $printStatus
     * @param bool $getFinalReport
     * @return array
     * @throws \Doctrine\CouchDB\HTTP\HTTPException
     * @throws \Exception
     */

    public function start($printStatus = true, $getFinalReport = true)
    {
        $this->startTime = new \DateTime();
        // DB info (via GET /{db}) for source and target.
        $this->verifyPeers();
        $this->task->setRepId($this->generateReplicationId());
        // Replication log (via GET /{db}/_local/{docid}) for source and target.
        list($sourceLog, $targetLog) = $this->getReplicationLog();

        $this->task->setSinceSeq($this->compareReplicationLogs($sourceLog, $targetLog));

        // Main replication processing
        $response = $this->locateChangedDocumentsAndReplicate($printStatus, $getFinalReport);

        $this->endTime = new \DateTime();
        $replicationLog = $this->putReplicationLog($response);

        $this->ensureFullCommit();

        unset($replicationLog['_id']);
        unset($replicationLog['_rev']);
        unset($replicationLog['_revisions']);

        return $replicationLog;
    }


    /**
     * @return array
     * @throws HTTPException
     * @throws \Exception
     */
    public function verifyPeers()
    {
        $sourceInfo = null;
        try {
            $sourceInfo = $this->source->getDatabaseInfo($this->source->getDatabase());
        } catch (HTTPException $e) {
            throw new \Exception('Source not reachable.');
        }

        $targetInfo = null;
        try {
            $targetInfo = $this->target->getDatabaseInfo($this->target->getDatabase());
        } catch (HTTPException $e) {
            if ($e->getCode() == 404 && $this->task->getCreateTarget()) {
                $this->target->createDatabase($this->target->getDatabase());
                $targetInfo = $this->target->getDatabaseInfo($this->target->getDatabase());
            } else {
                throw new \Exception("Target database does not exist.");
            }
        }
        return array($sourceInfo, $targetInfo);
    }

    /**
     * @return string
     * @throws HTTPException
     */
    public function generateReplicationId()
    {
        $filterCode = '';
        $filter = $this->task->getFilter();
        $parameters = $this->task->getParameters();
        if ($filter != null && empty($parameters)) {
            if ($filter[0] !== '_') {
                list($designDoc, $functionName) = explode('/', $filter);
                $designDocName = '_design/' . $designDoc;
                $response = $this->source->findDocument($designDocName);
                if ($response->status != 200) {
                    throw HTTPException::fromResponse('/' . $this->source->getDatabase() . '/' . $designDocName, $response);
                }
                $filterCode = $response->body['filters'][$functionName];
            }
        }
        return \md5(
          $this->source->getDatabase() .
          $this->target->getDatabase() .
          \var_export($this->task->getDocIds(), true) .
          ($this->task->getCreateTarget() ? '1' : '0') .
          ($this->task->getContinuous() ? '1' : '0') .
          $filter .
          $filterCode .
          $this->task->getStyle() .
          \var_export($this->task->getHeartbeat(), true)
        );
    }

    /**
     * @return array
     * @throws HTTPException
     * @throws \Exception
     */
    public function getReplicationLog()
    {
        $replicationDocId = '_local' . '/' . $this->task->getRepId();
        $sourceResponse = $this->source->findDocument($replicationDocId);
        $targetResponse = $this->target->findDocument($replicationDocId);

        if ($sourceResponse->status == 200) {
            $this->sourceLog = $sourceResponse->body;
        } elseif ($sourceResponse->status != 404) {
            throw HTTPException::fromResponse('/' . $this->source->getDatabase() . '/' .$replicationDocId, $sourceResponse);
        }
        if ($targetResponse->status == 200) {
            $this->targetLog = $targetResponse->body;
        } elseif ($targetResponse->status != 404) {
            throw HTTPException::fromResponse('/' . $this->target->getDatabase() . '/' .$replicationDocId, $targetResponse);
        }
        return array($this->sourceLog, $this->targetLog);
    }

    /**
     * @param array $response
     * @return array
     * @throws \Doctrine\CouchDB\HTTP\HTTPException
     */
    public function putReplicationLog($response) {
        $sessionId = \md5((\microtime(true) * 1000000));
        $sourceInfo = $this->source->getDatabaseInfo($this->source->getDatabase());
        $data = [
          '_id' => '_local/' . $this->task->getRepId(),
          'history' => [
            'recorded_seq' => $sourceInfo['update_seq'],
            'session_id' => $sessionId,
            'start_time' => $this->startTime->format('D, d M Y H:i:s e'),
            'end_time' => $this->endTime->format('D, d M Y H:i:s e'),
          ],
          'replication_id_version' => 3,
          'session_id' => $sessionId,
          'source_last_seq' => $sourceInfo['update_seq']
        ];

        if (isset($response['doc_write_failures'])) {
            $data['history']['doc_write_failures'] = $response['doc_write_failures'];
        }
        if (isset($response['docs_read'])) {
            $data['history']['docs_read'] = $response['docs_read'];
        }
        if (isset($response['missing_checked'])) {
            $data['history']['missing_checked'] = $response['missing_checked'];
        }
        if (isset($response['missing_found'])) {
            $data['history']['missing_found'] = $response['missing_found'];
        }
        if (isset($response['start_last_seq'])) {
            $data['history']['start_last_seq'] = $response['start_last_seq'];
        }
        if (isset($response['docs_written'])) {
            $data['history']['docs_written'] = $response['docs_written'];
        }

        // Creating dedicated source and target data arrays.
        $sourceData = $data;
        $targetData = $data;
        // Adding _rev to data array if it was in original replication log
        if (isset($this->sourceLog['_rev'])) {
            $sourceData['_rev'] = $this->sourceLog['_rev'];
        }
        if (isset($this->targetLog['_rev'])) {
            $targetData['_rev'] = $this->targetLog['_rev'];
        }

        // Having to work around CouchDBClient not supporting _local.
        $sourceResponse = $this->source->getHttpClient()->request('PUT', '/' . $this->source->getDatabase() . '/' . $data['_id'], json_encode($sourceData));
        $targetResponse = $this->target->getHttpClient()->request('PUT', '/' . $this->target->getDatabase() . '/' . $data['_id'], json_encode($targetData));

        if ($sourceResponse->status != 201) {
            throw HTTPException::fromResponse('/' . $this->source->getDatabase() . '/' . $data['_id'], $sourceResponse);
        }

        if ($targetResponse->status != 201) {
            throw HTTPException::fromResponse('/' . $this->target->getDatabase() . '/' . $data['_id'], $targetResponse);
        }

        return $data;
    }

    /**
     * @param $sourceLog
     * @param $targetLog
     * @return int|mixed
     */
    public function compareReplicationLogs(&$sourceLog, &$targetLog)
    {
        $sinceSeq = 0;
        if ($sourceLog == null || $targetLog == null) {
            $sinceSeq = $this->task->getSinceSeq();
        } elseif ($sourceLog['session_id'] === $targetLog['session_id']) {
            $sinceSeq = $sourceLog['source_last_seq'];
        } else {
            foreach ($sourceLog['history'] as &$sDoc) {
                $matchFound = 0;
                foreach ($targetLog['history'] as &$tDoc) {
                    if ($sDoc['session_id'] === $tDoc['session_id']) {
                        $sinceSeq = $sDoc['recorded_seq'];
                        $matchFound = 1;
                        break;
                    }
                }
                unset($tDoc);
                if ($matchFound === 1) {
                    break;
                }
            }
            unset($sDoc);
        }
        return $sinceSeq;
    }

    /**
     * @param $changes
     * @return array
     */
    public function getMapping(& $changes)
    {
        $rows = '';
        if ($this->task->getContinuous() == false) {
            $rows = $changes['results'];
        } else {
            $arr = \explode("\n",$changes);
            foreach ($arr as $line) {
                if (\strlen($line) > 0) {
                    $rows[] = json_decode($line, true);
                }
            }

        }
        // To be sent to target/_revs_diff.
        $mapping = array();

        foreach ($rows as &$row) {
            $mapping[$row['id']] = array();
            $mapping[$row['id']];
            foreach ($row['changes'] as &$revision) {
                $mapping[$row['id']][] = $revision['rev'];
            }
            unset($revision);
            //unset($arr);
        }
        unset($row);
        return $mapping;
    }

    /**
     * When $printStatus is true, the replication details are written to the
     * STDOUT. When $getFinalReport is true, detailed replication report is
     * returned and if false, only the success and failure counts are returned.
     * Both $printStatus and $getFinalReport are used only when the
     * replication is continuous and are ignored in case of normal replication.
     *
     * @param bool $printStatus
     * @param bool $getFinalReport
     * @return array
     * @throws HTTPException
     */
    public function locateChangedDocumentsAndReplicate($printStatus, $getFinalReport)
    {
        $finalResponse = array(
          'multipartResponse' => array(),
          'bulkResponse' => array(),
          'errorResponse' => array(),
          'start_last_seq' => '',
        );
        // Filtered changes stream is not supported. So Don't use the doc_ids
        // to specify the specific document ids.
        if ($this->task->getContinuous()) {
            $options = array(
              'feed' => 'continuous',
              'style' => $this->task->getStyle(),
              'heartbeat' => $this->task->getHeartbeat(),
              'since' => $this->task->getSinceSeq(),
              'filter' => $this->task->getFilter(),
              'parameters' => $this->task->getParameters(),
                //'doc_ids' => $this->task->getDocIds(), // Not supported.
                //'limit' => 10000 //taking large value for now, needs optimisation
            );
            if ($this->task->getHeartbeat() != null) {
                $options['heartbeat'] = $this->task->getHeartbeat();
            } else {
                $options['timeout'] = ($this->task->getTimeout() != null ? $this->task->getTimeout() : 10000);
            }
            $changesStream = $this->source->getChangesAsStream($options);
            $successCount = 0;
            $failureCount = 0;

            while (!feof($changesStream)) {
                $changes = fgets($changesStream);
                if ($changes == false || trim($changes) == '' || strpos($changes,'last_seq') !==false) {
                    sleep(2);
                    continue;
                }
                $mapping = $this->getMapping($changes);
                $docId = array_keys($mapping)[0];
                try {
                    // getRevisionDifference throws bad request when JSON is
                    // empty. So check before sending.
                    $revDiff = (count($mapping) > 0 ? $this->target->getRevisionDifference($mapping) : array());
                    $response = $this->replicateChanges($revDiff);
                    if ($getFinalReport == true) {
                        foreach ($response['multipartResponse'] as $docID => $res) {
                            // Add the response of posting each revision of the
                            // doc that had attachments.
                            foreach ($res as $singleRevisionResponse) {
                                // An Exception.
                                if (is_a($singleRevisionResponse, 'Exception')) {
                                    // Note: In this case there is no 'rev' field in
                                    // the response.
                                    $finalResponse['errorResponse'][$docID][] = $singleRevisionResponse;
                                } else {
                                    $finalResponse['multipartResponse'][$docID][] = $singleRevisionResponse;
                                }
                            }
                        }
                        foreach ($response['bulkResponse'] as $docID => $status) {
                            $finalResponse['bulkResponse'][$docID][] = $status;
                        }
                    }

                    if ($printStatus == true) {
                        echo 'Document with id = ' . $docId . ' successfully replicated.'. "\n";
                    }

                    $successCount++;

                } catch (\Exception $e) {
                    if ($getFinalReport == true) {
                        $finalResponse['errorResponse'][$docID][] = $e;
                    }

                    if ($printStatus == true) {
                        echo 'Replication of document with id = ' . $docId . ' failed with code: ' . $e->getCode() . ".\n";
                    }

                    $failureCount++;
                }
            }
            $finalResponse['successCount'] = $successCount;
            $finalResponse['failureCount'] = $failureCount;
            // The final response in case of continuous replication.
            // In case where $getFinalReport is true, response has five keys:
            // (i)multipartResponse, (ii) bulkResponse, (iii)errorResponse,
            // (iv) successCount, (v) failureCount. The errorResponse has the
            // responses from the failed replication attempt of docs having
            // attachments. To check failures related to bulk posting, the
            // returned status codes can be used.
            // When $getFinalReport is false, the returned response has only the
            // successCount and failureCount.
            return $finalResponse;

        } else {
            $changes = $this->source->getChanges(
              array(
                'feed' => 'normal',
                'style' => $this->task->getStyle(),
                'since' => $this->task->getSinceSeq(),
                'filter' => $this->task->getFilter(),
                'parameters' => $this->task->getParameters(),
                'doc_ids' => $this->task->getDocIds()
                  //'limit' => 10000 //taking large value for now, needs optimisation
              )
            );
            $mapping = $this->getMapping($changes);
            $revDiff = (count($mapping) > 0 ? $this->target->getRevisionDifference($mapping) : array());

            $response = $this->replicateChanges($revDiff);
            $finalResponse['doc_write_failures'] = 0;
            $finalResponse['docs_written'] = 0;
            $finalResponse['docs_read'] = $response['docs_read'];
            $finalResponse['missing_checked'] = count($changes['results']);
            $finalResponse['missing_found'] = $response['missing_found'];
            $finalResponse['start_last_seq'] = $changes['last_seq'];
            foreach ($response['multipartResponse'] as $docID => $res) {
                // Add the response of posting each revision of the
                // doc that had attachments.
                foreach ($res as $singleRevisionResponse) {
                    // An Exception.
                    if (is_a($singleRevisionResponse, 'Exception')) {
                        // Note: In this case there is no 'rev' field in
                        // the response.
                        $finalResponse['errorResponse'][$docID][] = $singleRevisionResponse;
                        $finalResponse['doc_write_failures']++;
                    } else {
                        $finalResponse['multipartResponse'][$docID][] = $singleRevisionResponse;
                        $finalResponse['docs_written']++;
                    }
                }
            }
            foreach ($response['bulkResponse'] as $docID => $res) {
                $finalResponse['bulkResponse'][$docID][] = $res;
            }
            // In case of normal replication the $finalResponse has three
            // keys: (i) multipartResponse, (ii) bulkResponse, (iii)
            // errorResponse.
            return $finalResponse;
        }
    }

    /**
     * @param array $revDiff
     * @return array|void
     * @throws HTTPException
     */
    public function replicateChanges(& $revDiff)
    {
        $allResponse = array(
            'multipartResponse' => array(),
            'bulkResponse' => array(),
            'docs_read' => 0,
            'missing_found' => 0
        );

        foreach ($revDiff as $docId => $revMisses) {
            $bulkUpdater = $this->target->createBulkUpdater();
            $bulkUpdater->setNewEdits(false);

            $allResponse['docs_read']++;
            $allResponse['missing_found'] += count($revMisses['missing']);
            try {
                list($docStack, $multipartResponse) = $this
                    ->source
                    ->transferChangedDocuments($docId, $revMisses['missing'], $this->target);
            } catch (\Exception $e) {
                // Deal with the failures. Try again once to deal with the
                // connection establishment failures of the client.
                // It's better to deal this in the client itself.
                usleep(500);
                list($docStack, $multipartResponse) = $this
                    ->source
                    ->transferChangedDocuments($docId, $revMisses['missing'], $this->target);
            }
            $bulkUpdater->updateDocuments($docStack);
            // $multipartResponse is an empty array in case there was no
            // transferred revision that had attachment in the current doc.
            $allResponse['multipartResponse'][$docId] = $multipartResponse;
            $allResponse['bulkResponse'][$docId] = $bulkUpdater->execute()->status;
        }
        return $allResponse;
    }

    /**
     * @throws \Doctrine\CouchDB\HTTP\HTTPException
     */
    public function ensureFullCommit()
    {
        $this->target->ensureFullCommit();
    }

}
