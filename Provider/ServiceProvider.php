<?php

namespace MicroFramework\Provider;

use MicroFramework\DataCollection\DataCollection;
use MicroFramework\Response\AbstractResponse;
use MicroFramework\Request\Request;
use MicroFramework\Validator\Validator;

class ServiceProvider
{

    protected $request;

    protected $response;

    protected $session_id;
    protected $layout;
    protected $view;
    protected $shared_data;

    public function __construct(Request $request = null, AbstractResponse $response = null)
    {
        // Bind our objects
        $this->bind($request, $response);

        // Instantiate our shared data collection
        $this->shared_data = new DataCollection();
    }

    public function bind(Request $request = null, AbstractResponse $response = null)
    {
        // Keep references
        $this->request  = $request  ?: $this->request;
        $this->response = $response ?: $this->response;

        return $this;
    }

    public function sharedData()
    {
        return $this->shared_data;
    }

    public function startSession()
    {
        if (session_id() === '') {
            // Attempt to start a session
            session_start();

            $this->session_id = session_id() ?: false;
        }

        return $this->session_id;
    }

    public function flash($msg, $type = 'info', $params = null)
    {
        $this->startSession();
        if (is_array($type)) {
            $params = $type;
            $type = 'info';
        }
        if (!isset($_SESSION['__flashes'])) {
            $_SESSION['__flashes'] = array($type => array());
        } elseif (!isset($_SESSION['__flashes'][$type])) {
            $_SESSION['__flashes'][$type] = array();
        }
        $_SESSION['__flashes'][$type][] = $this->markdown($msg, $params);
    }

    public function flashes($type = null)
    {
        $this->startSession();

        if (!isset($_SESSION['__flashes'])) {
            return array();
        }

        if (null === $type) {
            $flashes = $_SESSION['__flashes'];
            unset($_SESSION['__flashes']);
        } else {
            $flashes = array();
            if (isset($_SESSION['__flashes'][$type])) {
                $flashes = $_SESSION['__flashes'][$type];
                unset($_SESSION['__flashes'][$type]);
            }
        }

        return $flashes;
    }

    public static function markdown($str, $args = null)
    {
        // Create our markdown parse/conversion regex's
        $md = array(
            '/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
            '/\*\*([^\*]++)\*\*/'          => '<strong>$1</strong>',
            '/\*([^\*]++)\*/'              => '<em>$1</em>'
        );

        // Let's make our arguments more "magical"
        $args = func_get_args(); // Grab all of our passed args
        $str = array_shift($args); // Remove the initial arg from the array (and set the $str to it)
        if (isset($args[0]) && is_array($args[0])) {
            /**
             * If our "second" argument (now the first array item is an array)
             * just use the array as the arguments and forget the rest
             */
            $args = $args[0];
        }

        // Encode our args so we can insert them into an HTML string
        foreach ($args as &$arg) {
            $arg = htmlentities($arg, ENT_QUOTES, 'UTF-8');
        }

        // Actually do our markdown conversion
        return vsprintf(preg_replace(array_keys($md), $md, $str), $args);
    }

    public static function escape($str, $flags = ENT_QUOTES)
    {
        return htmlentities($str, $flags, 'UTF-8');
    }

    public function refresh()
    {
        $this->response->redirect(
            $this->request->uri()
        );

        return $this;
    }

    public function back()
    {
        $referer = $this->request->server()->get('HTTP_REFERER');

        if (null !== $referer) {
            $this->response->redirect($referer);
        } else {
            $this->refresh();
        }

        return $this;
    }

    public function layout($layout = null)
    {
        if (null !== $layout) {
            $this->layout = $layout;

            return $this;
        }

        return $this->layout;
    }

    public function yieldView()
    {
        require $this->view;
    }

    public function render($view, array $data = array())
    {
        $original_view = $this->view;

        if (!empty($data)) {
            $this->shared_data->merge($data);
        }

        $this->view = $view;

        if (null === $this->layout) {
            $this->yieldView();
        } else {
            require $this->layout;
        }

        if (false !== $this->response->chunked) {
            $this->response->chunk();
        }

        // restore state for parent render()
        $this->view = $original_view;
    }

    public function partial($view, array $data = array())
    {
        $layout = $this->layout;
        $this->layout = null;
        $this->render($view, $data);
        $this->layout = $layout;
    }

    public function addValidator($method, $callback)
    {
        Validator::addValidator($method, $callback);
    }

    public function validate($string, $err = null)
    {
        return new Validator($string, $err);
    }

    public function validateParam($param, $err = null)
    {
        return $this->validate($this->request->param($param), $err);
    }


    public function __isset($key)
    {
        return $this->shared_data->exists($key);
    }

    public function __get($key)
    {
        return $this->shared_data->get($key);
    }

    public function __set($key, $value)
    {
        $this->shared_data->set($key, $value);
    }

    public function __unset($key)
    {
        $this->shared_data->remove($key);
    }
}
