<?php

namespace MicroFramework\DataCollection;

class ServerDataCollection extends DataCollection
{

    protected static $http_header_prefix = 'HTTP_';

    protected static $http_nonprefixed_headers = array(
        'CONTENT_LENGTH',
        'CONTENT_TYPE',
        'CONTENT_MD5',
    );


    public static function hasPrefix($string, $prefix)
    {
        if (strpos($string, $prefix) === 0) {
            return true;
        }

        return false;
    }

    public function getHeaders()
    {
        // Define a headers array
        $headers = array();

        foreach ($this->attributes as $key => $value) {
            // Does our server attribute have our header prefix?
            if (self::hasPrefix($key, self::$http_header_prefix)) {
                // Add our server attribute to our header array
                $headers[
                    substr($key, strlen(self::$http_header_prefix))
                ] = $value;

            } elseif (in_array($key, self::$http_nonprefixed_headers)) {
                // Add our server attribute to our header array
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
