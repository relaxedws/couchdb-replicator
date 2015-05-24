<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 22/5/15
 * Time: 6:51 PM
 */

namespace Relaxed\Replicator\replicator;

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
    protected $task;

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
        $this->verifyPeers($this->source, $this->target);
    }

    /**
     * @param CouchDBClient $source
     * @param CouchDBClient $target
     * @throws HTTPException
     * @throws \Exception
     */
    protected function verifyPeers(CouchDBClient $source,CouchDBClient $target)
    {
        $sourceInfo = $source->getDatabaseInfo($source->getDatabase());
        try {
            $targetInfo = $target->getDatabaseInfo($target->getDatabase());
        } catch (HTTPException $e) {
            if ($e->getCode() == 404 && $this->task->getCreateTarget()) {
                    $target->createDatabase($target->getDatabase());
            } else {
                throw new \Exception("Target database doesn't exist.");
            }
        }
    }


}