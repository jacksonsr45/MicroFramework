<?php

namespace MicroFramework\Response;

use MicroFramework\Exceptions\ResponseAlreadySentException;
use RuntimeException;

class Response extends AbstractResponse
{
    public function chunk($str = null)
    {
        parent::chunk();

        if (null !== $str) {
            printf("%x\r\n", strlen($str));
            echo "$str\r\n";
            flush();
        }

        return $this;
    }

    public function dump($obj)
    {
        if (is_array($obj) || is_object($obj)) {
            $obj = print_r($obj, true);
        }

        $this->append('<pre>' .  htmlentities($obj, ENT_QUOTES) . "</pre><br />\n");

        return $this;
    }

    public function file($path, $filename = null, $mimetype = null)
    {
        if ($this->sent) {
            throw new ResponseAlreadySentException('Response has already been sent');
        }

        $this->body('');
        $this->noCache();

        if (null === $filename) {
            $filename = basename($path);
        }
        if (null === $mimetype) {
            $mimetype = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
        }

        $this->header('Content-type', $mimetype);
        $this->header('Content-Disposition', 'attachment; filename="'.$filename.'"');

        // If the response is to be chunked, then the content length must not be sent
        // see: https://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
        if (false === $this->chunked) {
            $this->header('Content-length', filesize($path));
        }

        // Send our response data
        $this->sendHeaders();

        $bytes_read = readfile($path);

        if (false === $bytes_read) {
            throw new RuntimeException('The file could not be read');
        }

        $this->sendBody();

        // Lock the response from further modification
        $this->lock();

        // Mark as sent
        $this->sent = true;

        // If there running FPM, tell the process manager to finish the server request/response handling
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        return $this;
    }

    public function json($object, $jsonp_prefix = null)
    {
        $this->body('');
        $this->noCache();

        $json = json_encode($object);

        if (null !== $jsonp_prefix) {
            // Should ideally be application/json-p once adopted
            $this->header('Content-Type', 'text/javascript');
            $this->body("$jsonp_prefix($json);");
        } else {
            $this->header('Content-Type', 'application/json');
            $this->body($json);
        }

        $this->send();

        return $this;
    }
}
