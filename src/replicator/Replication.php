<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 22/5/15
 * Time: 6:51 PM
 */

namespace Relaxed\Replicator\replicator;

use Relaxed\Replicator\replicator\ReplicationTask;
use Doctrine\CouchDB\CouchDBClient;

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
    public function __construct(CouchDBClient $source, CouchDBClient $target, ReplicationTask $task){

        $this->source = $source;
        $this->target = $target;
        $this->task = $task;
    }

    public function start(){

    }
}