<?php

use Relaxed\Replicator\ReplicationTask;
use Relaxed\Replicator\Replication;

require 'vendor/autoload.php';

ini_set('memory_limit', '2M');

$source = Doctrine\CouchDB\CouchDBClient::create(array('dbname' => 'check','socket'=>'stream'));
$target = Doctrine\CouchDB\CouchDBClient::create(array('dbname' => 'check_check','socket'=>'stream'));
$task = new ReplicationTask();
$task->setCreateTarget(true);
$task->setContinuous(true);

$replication = new Replication($source, $target, $task);
$replication->task->setRepId($replication->generateReplicationId());

$replicationResult = $replication->start();

print_r($replicationResult);

/*
$client = Doctrine\CouchDB\CouchDBClient::create(array('dbname' => 'abhishek','type' => 'stream'));
//json
$path[0] = 'abhishek/598c6bd17ba9dc0dfa27e82fa00001e0';
$params[0] = array('revs' => true ,'latest' => true, 'open_revs'=> json_encode(array
('1-e6156c039ff1ace56ca9e24944d9edd0')));

//image + .html file
$path[1] = 'albums/6e1295ed6c29495e54cc05947f18c8af';
$params[1] = array('revs' => true ,'latest' => true,'open_revs' => json_encode(array('9-2b3a0e1012e9f22ae5eb6261745e1463')) );

//text, .py file
$path[2] = 'albums/21b00ae460f9c6b936baf3bf120350d7';
$params[2]=array('revs' => true ,'latest' => true,'open_revs' => json_encode(array('3-8b375e2de5b624f3e6bc9fd8c03df2ba')) );

$path[3] = 'large_attachment/hey';
$params[3] = array('revs' => true ,'latest' => true,'open_revs' => json_encode(array('7-cb1b4c3ffa5fc38a549b3eefe93d432f')) );


$val=1;
//$str = $client->myRequest($path[$val],$params[$val], 'GET',true);



function getFirstLine(& $str)
{
    $i = 0;
    $firstLine = '';
    while($str[$i] != "\r" && $str[$i] != "\n"){
        $firstLine.=$str[$i++];
    }
    return $firstLine;
}
function parseMultipartData(& $rawData)
{
    $mainBoundary = getFirstLine($rawData);
    $arr = explode($mainBoundary, $rawData);
    $docStack = array();
    $multipartDocStack = array();

    foreach ($arr as $strBlock) {
        if (!strlen($strBlock) || $strBlock === '--') {
           continue;
        }
        $strBlock = ltrim($strBlock);
        $firstLine = getFirstLine($strBlock);
        if (strpos($firstLine, "Content-Type") !== false) {

            list($header, $value) = explode(":", $firstLine);
            $header = trim($header);
            $value = trim($value);
            $boundary = '';

            if (strpos($value, ";") !==false) {

                list($type,$info) = explode(";", $value,2);
                $info = trim($info);

                if (strpos($info, "boundary") !==false) {
                    $boundary = $info; //includes "boundary=" string also.

                } elseif (strpos($info, "error") !== false) {
                    continue;//missing revs at source

                } else {
                    //echo $strBlock;
                    throw new \Exception("Unknown parameter with Content-Type.");
                }

            }

            if (strpos($value, "multipart/related") !==false) {

                if ($boundary == '') {
                    throw new \Exception("Boundary not set for multipart/related data.");
                }
                $boundary = explode("=",$boundary,2)[1];
                $multipartDocStack[$boundary] = trim(explode($firstLine, $strBlock,2)[1]);

            } elseif ($value == 'application/json') {
                $jsonDoc = trim(preg_split( '/\n|\r\n?/', $strBlock,3)[2]);
                array_push($docStack, $jsonDoc);

            } else {
                throw new \UnexpectedValueException("This value is not supported.");
            }

        } else {
            throw new \Exception('The first line is not the Content-Type.');
        }
    }
    return array($docStack, $multipartDocStack);

}

$ss='--7b1596fc4940bc1be725ad67f11ec1c4
    Content-Type: application/json

{ "_id": "SpaghettiWithMeatballs", "_rev": "1-917fa23", "_revisions": { "ids": [ "917fa23" ], "start": 1 }, "description": "An Italian-American delicious dish", "ingredients": [ "spaghetti", "tomato sauce", "meatballs" ], "name": "Spaghetti with meatballs" }
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: multipart/related; boundary="a81a77b0ca68389dda3243a43ca946f2"

--a81a77b0ca68389dda3243a43ca946f2
Content-Type: application/json

{
    "_attachments": {
      "recipe.txt": {
          "content_type": "text/plain",
          "digest": "md5-R5CrCb6fX10Y46AqtNn0oQ==",
          "follows": true,
          "length": 87,
          "revpos": 7
      }
    },
    "_id": "SpaghettiWithMeatballs",
    "_rev": "7-474f12e",
    "_revisions": {
        "ids": [
            "474f12e",
            "5949cfc",
            "00ecbbc",
            "fc997b6",
            "3552c87",
            "404838b",
            "5defd9d",
            "dc1e4be"
        ],
        "start": 7
    },
    "description": "An Italian-American delicious dish",
    "ingredients": [
        "spaghetti",
        "tomato sauce",
        "meatballs",
        "love"
    ],
    "name": "Spaghetti with meatballs"
}
--a81a77b0ca68389dda3243a43ca946f2
Content-Disposition: attachment; filename="recipe.txt"
Content-Type: text/plain
Content-Length: 87

1. Cook spaghetti
2. Cook meetballs
3. Mix them
4. Add tomato sauce
5. ...
6. PROFIT!

--a81a77b0ca68389dda3243a43ca946f2--
--7b1596fc4940bc1be725ad67f11ec1c4
Content-Type: application/json; error="true"

{"missing":"3-6bcedf1"}
--7b1596fc4940bc1be725ad67f11ec1c4--';


//var_dump($str);
//var_dump(parseMultipartData($str));
*/
