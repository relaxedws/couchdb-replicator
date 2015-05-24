<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 22/5/15
 * Time: 6:28 PM
 */

namespace Relaxed\Replicator;

use Doctrine\CouchDB\CouchDBClient;
use Relaxed\Replicator\ReplicationTask;
use Relaxed\Replicator\Replication;


/**
 * Class Replicator
 * @package Relaxed\Replicator
 */
class Replicator
{
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
    public function __construct(
        CouchDBClient $source = null,
        CouchDBClient $target = null,
        ReplicationTask $task = null
    ) {
        $this->source = $source;
        $this->target = $target;
        $this->task = $task;
    }

    /**
     *
     */
    public function startReplication()
    {
        if ($this->source == null || $this->target == null || $this->task == null) {
            throw new \UnexpectedValueException();
        }

        $replication = new Replication($this->source, $this-> target, $this->task);
        $replication->start();
    }

    /**
     * @throws Exception
     */
    public function cancelReplication()
    {
        throw new \Exception('Not defined');
    }

    /**
     * @return CouchDBClient
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param CouchDBClient $source
     */
    public function setSource(CouchDBClient $source)
    {
        $this->source = $source;
    }

    /**
     * @return CouchDBClient
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param CouchDBClient $target
     */
    public function setTarget(CouchDBClient $target)
    {
        $this->target = $target;
    }

    /**
     * @return ReplicationTask
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param ReplicationTask $task
     */
    public function setTask(ReplicationTask $task)
    {
        $this->task = $task;
    }
}
