<?php

namespace Doctrine\CouchDB\Replicator\Exception;

class DocumentException extends \Exception
{
    public static function missingField($name)
    {
        return new self("Missing the '$name' field in document response body.");
    }
}
