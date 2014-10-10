<?php

namespace Doctrine\CouchDB\Replicator\Exception;

class DatabaseException extends \Exception
{
    public static function mustCreate($name)
    {
        return new self("Database '$name' must be created.");
    }

    public static function missingField($name)
    {
        return new self("Missing the '$name' field in database response body.");
    }
}
