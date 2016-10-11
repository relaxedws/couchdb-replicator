<?php

namespace Relaxed\Replicator;

use Doctrine\CouchDB\CouchDBClient;

/**
 * Class Replicator
 * @package Relaxed\Replicator
 */
class Replicator
{
    /**
     * @var \Doctrine\CouchDB\CouchDBClient
     */
    protected $source;
    
    /**
     * @var \Doctrine\CouchDB\CouchDBClient
     */
    protected $target;
    
    /**
     * @var \Relaxed\Replicator\ReplicationTask
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
     * Start the replicator. $printStatus can be used to print the status of
     * the continuous replication to the STDOUT. The $getFinalReport can be
     * used to enable/disable returning of an array containing the
     * replication report in case of continuous replication.
     *
     * @param bool $printStatus
     * @param bool $getFinalReport
     * @return array
     */
    public function startReplication($printStatus = true, $getFinalReport = false)
    {
        if ($this->source == null) {
            throw new \UnexpectedValueException('Source is Null.');
        }
        if ($this->target == null) {
            throw new \UnexpectedValueException('Target is Null.');
        }
        if ($this->task == null) {
            throw new \UnexpectedValueException('Task is Null.');
        }

        $replication = new Replication($this->source, $this-> target, $this->task);

        // Start and return the details of the replication.
        return $replication->start($printStatus, $getFinalReport);
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
