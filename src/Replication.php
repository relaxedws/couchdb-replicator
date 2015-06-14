<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 22/5/15
 * Time: 6:51 PM
 */

namespace Relaxed\Replicator;

use Relaxed\Replicator\ReplicationTask;
use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\HTTPException;

/**
 * Class Replication
 * @package Relaxed\Replicator\replicator
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

    public function start()
    {
        list($sourceInfo, $targetInfo) = $this
            ->verifyPeers($this->source, $this->target, $this->task);
        $this->task->setRepId(
            $this->generateReplicationId());
        list($sourceLog, $targetLog) = $this->getReplicationLog();
        $this->task->setSinceSeq($this
            ->compareReplicationLogs($sourceLog, $targetLog));

        //From here the code should be in some kind of loop
        //to repeat the locate-fetch-replicate steps. TBD.
        $revDiff = $this->locateChangedDocuments();
        return $this->replicateChanges($revDiff);



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
        if ($filter != null) {
            if ($filter[0] !== '_') {
                list($designDoc, $functionName) = explode('/', $filter);
                $filterCode = $this->source->getDesignDocument($designDoc)['filters'][$functionName];
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

    public function getReplicationLog()
    {
        $sourceLog = null;
        $targetLog = null;
        try {
            $sourceLog = $this->source->getReplicationLog(
                $this->task->getRepId()
            );
        } catch (HTTPException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        try {
            $targetLog = $this->target->getReplicationLog(
                $this->task->getRepId()
            );
        } catch (HTTPException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
        return array($sourceLog, $targetLog);
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

    public function locateChangedDocuments()
    {
        $changes = $this->source->getChanges(array(
            'feed' => ($this->task->getContinuous() ? 'continuous' : 'normal'),
            'style' => $this->task->getStyle(),
            'heartbeat' => $this->task->getHeartbeat(),
            'since' => $this->task->getSinceSeq(),
            'filter' => $this->task->getFilter(),
            'doc_ids' => $this->task->getDocIds()
            //'limit' => 10000 //taking large value for now, needs optimisation
            ),
            ($this->task->getContinuous() ? true : false)
        );

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
        // to be sent to target/_revs_diff
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


        $revDiff = $this->target->getRevisionDifference($mapping);
        return $revDiff;
    }

    public function replicateChanges(& $revDiff)
    {
        //no missing revisions.
        //replication over
        if (count($revDiff) == 0) {
            return;
        }
        $streamClient = \Doctrine\CouchDB\CouchDBClient::create(array('dbname' => $this->source->getDatabase(),'type' =>
            'stream'));
        $bulkUpdater = $this->target->createBulkUpdater();

        foreach ($revDiff as $docId => $revMisses) {

            $path = $streamClient->getDatabase() . "/" . $docId;
            $params = array('revs' => true ,'latest' => true,'open_revs' => json_encode($revMisses['missing']));

            $rawResponse = $streamClient->myRequest($path,$params, 'GET',true);

            list($docStack, $multipartDocStack) = $streamClient->getHttpClient()->parseMultipartData($rawResponse);

            $bulkUpdater->updateDocuments($docStack);
            $allResponse = '';
            foreach ($multipartDocStack as $key => $value) {
                $response = '';
                try {
                    $allResponse[$docId] = $this->target->putMultipartData($docId, $value, array('Content-Type' =>
                        'multipart/related; boundary='
                        . $key));
                } catch(HTTPException $e) {
                    if ($e->getCode() / 100 > 4) {
                        throw $e;
                    }
                }
            }

        }
        $allResponse['bulkResponse'] = $bulkUpdater->execute();

    }
}