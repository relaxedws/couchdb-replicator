<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 20/5/15
 * Time: 1:49 PM
 */

namespace Doctrine\CouchDB\HTTP;


interface iClient {
    function createRequest(array $params);
    function sendRequest(& $response);
}