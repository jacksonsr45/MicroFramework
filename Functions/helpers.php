<?php

function assets($file)
{
    $__proto = strtolower(preg_replace("/[^a-zA-Z\s]/",'', $_SERVER["SERVER_PROTOCOL"]));
    $__server_name = $_SERVER["SERVER_NAME"];
    $__port = $_SERVER["SERVER_PORT"] == "80" ? "" : ":" .$_SERVER["SERVER_PORT"];
    $__script_name = str_replace("/index.php", "", $_SERVER["SCRIPT_NAME"]);
    $__request_uri = $_SERVER["REQUEST_URI"];

    return "{$__proto}://{$__server_name}{$__port}{$__script_name}/{$file}";
}

function dd($dump)
{
    var_dump($dump);

    die();
}