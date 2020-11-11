<?php

namespace MicroFramework\Model;

use MicroFramework\Functions\Config;
use PDO;

class Connection 
{
    public static function connect()
    {
        $config = new Config;
        $pdo = new PDO("{$config->getDb()['db']['DRIVE']}:host={$config->getDb()['db']['HOST']};
                   dbname={$config->getDb()['db']['DBNAME']};
                   charset={$config->getDb()['db']['CHARSET']}", 
                   $config->getDb()['db']['USERNAME'], 
                   $config->getDb()['db']['PASSWORD']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        return $pdo;
    }
}