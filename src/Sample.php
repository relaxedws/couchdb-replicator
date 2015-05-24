<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 20/5/15
 * Time: 10:57 PM
 */

namespace Relaxed\Replicator\replicator;
use Relaxed\Replicator\HTTP\Client;

require '../../vendor/autoload.php';

class Sample {

}

$cl=new Client();
$cl->createRequest(array('method'=>'GET','URL'=>'http://google.com/','headers'=>['debug' => true]));
$response='';
$cl->sendRequest($response);
//var_dump($response);
echo $response->getBody();