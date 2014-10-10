<?php

namespace Doctrine\CouchDB\Replicator;

use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\HTTPException;
use Doctrine\CouchDB\Replicator\Exception\DatabaseException;
use Doctrine\CouchDB\Replicator\Exception\DocumentException;

class CouchDBReplicator
{
    /**
     * Source client.
     *
     * @var \Doctrine\CouchDB\CouchDBClient
     */
    protected $source;

    /**
     * Target client.
     *
     * @var \Doctrine\CouchDB\CouchDBClient
     */
    protected $target;

    /**
     * Flag indicating if replication should be continuous or not.
     *
     * @var boolean
     */
    protected $continuous = FALSE;

    /**
     * Filter query.
     *
     * @var string
     */
    protected $filter = '';

    /**
     * Flag indicating if target database should be created or not.
     *
     * @var boolean
     */
    protected $createTarget = FALSE;

    /**
     * Factory method.
     *
     * @param array $options
     * @return CouchDBReplicator
     */
    static function create(array $options)
    {
        foreach (array('source:dbname', 'target:dbname') as $key) {
            if (!isset($options[$key])) {
                throw new \InvalidArgumentException("'$key' is a required option to create a Doctrine");
            }
        }

        // Proxy options to the source and target clients or the replicator.
        $client_options = array();
        $replicator_options = array();
        foreach ($options as $key => $value) {
            if (strpos($key, ':')) {
                list($destination, $option) = explode(':', $key);
                $client_options[$destination][$option] = $value;
            } else {
                $replicator_options[$key] = $value;
            }
        }

        return new static(
            CouchDBClient::create($client_options['source']),
            CouchDBClient::create($client_options['target']),
            $replicator_options
        );
    }

    /**
     * Constructor.
     *
     * @param \Doctrine\CouchDB\CouchDBClient $source
     * @param \Doctrine\CouchDB\CouchDBClient $target
     * @param array $options
     */
    public function __construct(CouchDBClient $source, CouchDBClient $target, array $options = array())
    {
        $this->source = $source;
        $this->target = $target;

        if (isset($options['continuous'])) {
          $this->continuous = (boolean) $options['continuous'];
        }
        if (isset($options['filter'])) {
          $this->filter = (string) $options['filter'];
        }
        if (isset($options['create_target'])) {
          $this->createTarget = (boolean) $options['create_target'];
        }
    }

    /**
     * Start the replication.
     *
     * @see http://docs.couchdb.org/en/latest/replication/protocol.html
     */
    public function start()
    {
        // @see http://docs.couchdb.org/en/latest/replication/protocol.html#verify-peers
        $source_info = $this->getPeerInfo($this->source);
        try {
            $target_info = $this->getPeerInfo($this->target);
        } catch (HTTPException $e) {
            if ($this->createTarget) {
                $this->target->createDatabase($this->target->getDatabase());
            } else {
                throw DatabaseException::mustCreate($this->source->getDatabase());
            }
        }

        // @see http://docs.couchdb.org/en/latest/replication/protocol.html#find-out-common-ancestry
        $source_log = $this->getReplicationLog($this->source);
        $target_log = $this->getReplicationLog($this->target);
        $seq = $this->findCommonAncenstry($source_log, $target_log);

        // @todo Implement remaining steps.
    }

    /**
     * Get peer info.
     *
     * @param \Doctrine\CouchDB\CouchDBClient $peer
     * @return boolean
     * @throws DatabaseException
     * @see http://docs.couchdb.org/en/latest/replication/protocol.html#get-peers-information
     */
    public function getPeerInfo(CouchDBClient $peer)
    {
        $info = $peer->getDatabaseInfo($peer->getDatabase());
        foreach (array('db_name', 'instance_start_time', 'update_seq') as $field) {
            if (!isset($info[$field])) {
                throw DatabaseException::missingField($field);
            }
        }
        return $info;
    }

    /**
     * Get the replication ID.
     *
     * @return string
     * @see http://docs.couchdb.org/en/latest/replication/protocol.html#generate-replication-id
     */
    public function getReplicationID()
    {
        return md5(
            $this->source->getDatabase() .
            $this->target->getDatabase() .
            $this->createTarget .
            $this->continuous .
            $this->filter
        );
    }

    /**
     * Get peer replication log.
     *
     * @param \Doctrine\CouchDB\CouchDBClient $peer
     * @return array
     * @throws DocumentException
     * @see http://docs.couchdb.org/en/latest/replication/protocol.html#retrieve-replication-logs-from-source-and-target
     */
    public function getReplicationLog(CouchDBClient $peer)
    {
        $id = $this->getReplicationID();
        try {
            $log = $peer->findDocument("_local/$id");
            foreach (array('session_id', 'source_last_seq', 'history') as $field) {
                if (!isset($info[$field])) {
                    throw DocumentException::missingField($field);
                }
            }
            return $log;
        } catch (HTTPException $e) {
            return array();
        }
    }

    /**
     * Find common ancestry.
     *
     * @param array $source_log
     * @param array $target_log
     * @return int
     * @see http://docs.couchdb.org/en/latest/replication/protocol.html#compare-replication-logs
     */
    public function findCommonAncestry(array $source_log, array $target_log) {
        // @todo Implement logic.
        return 0;
    }
}
