<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 18/5/15
 * Time: 8:51 PM
 */
echo "hi all!\n";
require 'vendor/autoload.php';
/*use GuzzleHttp\Client;
echo "here1\n";
$client = new Client();
$response = $client->get('http://google.com/');
$code = $response->getStatusCode();
echo 'code is '. $code.'\n';
$body = $response->getBody();
var_dump($response);
echo $body;
echo "here3\n";
*/

$client = Doctrine\CouchDB\CouchDBClient::create(array('dbname' =>
    'abhishek'));
$res = $client->getDesignDocument('replicateFilter');
//var_dump($res);
$changes = $client->getChanges(array(
    'feed' =>  'continuous',
    'timeout' => 100
   // 'continuous' => true
),true
    );
//var_dump($changes);
$arr = explode("\n",$changes);
var_dump($arr);
echo "completed.. \n";
//array($id, $rev) = $client->postDocument(array('foo' => 'bar'));
//$client->putDocument(array('foo' => 'baz'), $id, $rev);

//$doc = $client->findDocument($id);

