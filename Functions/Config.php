<?php

namespace MicroFramework\Functions;

class Config
{
    private $envs;

    public function __construct()
    {
        $this->envs = parse_ini_file("../.env");
    }

    public function run()
    {
        $this->getEnvs();
    }

    private function getEnvs()
    {
        foreach ($this->envs as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    public function getDb()
    {
        return [
            'db' => [
                'DRIVE'     => $_ENV['DRIVE'],
                'HOST'      => $_ENV['HOST'],
                'DBNAME'    => $_ENV['DBNAME'],
                'USERNAME'  => $_ENV['USERNAME'],
                'PASSWORD'  => $_ENV['PASSWORD'],
                'CHARSET'   => $_ENV['CHARSET'],
            ]
        ];
    }
}
