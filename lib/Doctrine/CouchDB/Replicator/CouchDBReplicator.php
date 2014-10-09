<?php

namespace Doctrine\CouchDB\Replicator;

use Doctrine\CouchDB\CouchDBClient;

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
    protected $createTarget = TRUE;

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
     */
    public function start()
    {

    }

    /**
     * Generate a replication ID.
     *
     * @return string
     */
    protected function generateReplicationID()
    {
        return md5(
            $this->source->getDatabase() .
            $this->target->getDatabase() .
            $this->createTarget .
            $this->continuous .
            $this->filter
        );
    }
}
