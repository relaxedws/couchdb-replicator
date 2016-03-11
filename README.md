[![Build Status](https://travis-ci.org/relaxedws/couchdb-replicator.svg?branch=master)](https://travis-ci.org/relaxedws/couchdb-replicator)

# couchdb-replicator
CouchDB Replicator implemented with PHP

## Example usage
```php
require __DIR__ . '/vendor/autoload.php';

use Doctrine\CouchDB\CouchDBClient;
use Relaxed\Replicator\ReplicationTask;
use Relaxed\Replicator\Replicator;

$source = CouchDBClient::create(['dbname' => 'source']);
$target = CouchDBClient::create(['dbname' => 'target']);

$task = new ReplicationTask();
$replicator = new Replicator($source, $target, $task);

$response = $replicator->startReplication();
```
