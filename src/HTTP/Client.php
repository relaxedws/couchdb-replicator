<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 20/5/15
 * Time: 1:38 PM
 */

namespace Doctrine\CouchDB\HTTP;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

require '../../vendor/autoload.php';


/**
 * Class Client
 * @package Doctrine\CouchDB\HTTP
 */
class Client implements iClient{
    private $_client;
    private $_request;


    /**
     * @param array $params
     */
    public function __construct(array $params=array()){
        $this->_client=new GuzzleClient(array(
            //'base_url' =>$params['base_url'],
            'defaults' => array(
                /*    'headers' => isset($params['headers'])?$params['headers']:'',
                    'query'   => isset($params['query'])?$params['query']:'',*/
                'auth'    => isset($params['auth'])?$params['auth']:null,
                'proxy'   => isset($params['proxy'])?$params['proxy']:null
            )
        ));
    }

    /**
     * @param array $params
     */
    public function createRequest(array $params){
        $this->_request=$this->_client->createRequest($params['method'],$params['URL']);
        if(isset($params['scheme']))$this->_request->setScheme($params['scheme']);
        if(isset($params['port']))$this->_request->setScheme($params['port']);
        if(isset($params['headers']))$this->_request->setHeaders($params['headers']);
        if(isset($params['query']))$this->_request->setQuery($params['query']);
        if(isset($params['query']))$this->_request->setBody($params['body']);


    }


    /**
     * @param $response
     */
    public function sendRequest(& $response){
        $response=null;
        try {

            $response=$this->_client->send($this->_request);
        }

        catch (RequestException $e) {

            echo $e->getCode(). " ::code\n";
            echo $e->getRequest() . "--1\n";
            if ($e->hasResponse()) {
                echo $e->getResponse() . "--2\n";
            }
        }


    }
}

