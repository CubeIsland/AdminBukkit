<?php
    error_reporting(-1);

    defined('DS')               or define('DS',             DIRECTORY_SEPARATOR);
    defined('BACKEND_PATH')     or define('BACKEND_PATH',   dirname(__FILE__));
    defined('RESOURCE_PATH')    or define('RESOURCE_PATH',  BACKEND_PATH . DS . '..' . DS . 'res');
    defined('INCLUDE_PATH')     or define('INCLUDE_PATH',   BACKEND_PATH . DS . 'includes');
    defined('TEMPLATE_PATH')    or define('TEMPLATE_PATH',  BACKEND_PATH . DS . 'templates');
    defined('PAGE_PATH')        or define('PAGE_PATH',      BACKEND_PATH . DS . 'pages');
    defined('CONFIG_PATH')      or define('CONFIG_PATH',    BACKEND_PATH . DS . 'configs');
    defined('LANG_PATH')        or define('LANG_PATH',      BACKEND_PATH . DS . 'language');
    defined('LOG_PATH')         or define('LOG_PATH',       BACKEND_PATH . DS . 'logs');
    defined('DOWNLOAD_PATH')    or define('DOWNLOAD_PATH',  BACKEND_PATH . DS . 'downloads');

    function __autoload($classname)
    {
        static $classmap = array(
            'Template'                          => 'Template.php',
            'Design'                            => 'Design.php',
            'Config'                            => 'Config.php',
            'Minecraft'                         => 'Minecraft.php',
            'Router'                            => 'Router.php',
            'View'                              => 'View.php',
            'Filter'                            => 'Filter.php',
            'Registry'                          => 'Registry.php',
            'Page'                              => 'Page.php',
            'WhitespaceFilter'                  => 'WhitespaceFilter.php',
            'TidyFilter'                        => 'TidyFilter.php',
            'Router'                            => 'Router.php',
            'HttpClient'                        => 'Http/HttpClient.php',
            'HttpHeader'                        => 'Http/HttpHeader.php',
            'HttpCookie'                        => 'Http/HttpCookie.php',
            'HttpReply'                         => 'Http/HttpReply.php',
            'AbstractHttpRequestMethod'         => 'Http/AbstractHttpRequestMethod.php',
            'AbstractHttpAuthentication'        => 'Http/AbstractHttpAuthentication.php',
            'BasicAuthentication'               => 'Http/AuthenticationMethods/BasicAuthentication.php',
            'GetRequestMethod'                  => 'Http/RequestMethods/GetRequestMethod.php',
            'PostRequestMethod'                 => 'Http/RequestMethods/PostRequestMethod.php',
            'OptionsRequestMethod'              => 'Http/RequestMethods/OptionsRequestMethod.php',
            'TraceRequestMethod'                => 'Http/RequestMethods/TraceRequestMethod.php',
            'HeadRequestMethod'                 => 'Http/RequestMethods/HeadRequestMethod.php',
            'AESCrypter'                        => 'AESCrypter.php',
            'User'                              => 'User.php',
            'Text'                              => 'Text.php',
            'ApiValidator'                      => 'ApiValidator.php',
            'SessIDFilter'                      => 'SessIDFilter.php',
            'Request'                           => 'Request.php',
            'Lang'                              => 'Lang.php',
            'Logger'                            => 'Logger.php',
            'Statistics'                        => 'Statistics.php',
            'ApiBukkit'                         => 'ApiBukkit.php',
            'Database'                          => 'Database.php',
            'DatabaseManager'                   => 'DatabaseManager.php',
            'DatabaseException'                 => 'DatabaseException.php',
            'LinkGenerator'                     => 'LinkGenerator.php',
            'DefaultLinkGenerator'              => 'DefaultLinkGenerator.php',
            'ModRewriteLinkGenerator'           => 'ModRewriteLinkGenerator.php'
        );
        
        if (isset($classmap[$classname]))
        {
            require_once INCLUDE_PATH . DS . $classmap[$classname];
        }
    }
    
    function onError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (error_reporting() == 0)
        {
            return;
        }
        $logger = Logger::instance('error');
        $errstr = strip_tags($errstr);
        $errfile = (isset($errfile) ? basename($errfile) : 'unknown');
        $errline = (isset($errline) ? $errline : '?');

        $errortype = '';
        switch ($errno)
        {
            case E_ERROR:
                $errortype = 'error';
                break;
            case E_WARNING:
                $errortype = 'warning';
                break;
            case E_NOTICE:
                $errortype = 'notice';
                break;
            case E_STRICT:
                $errortype = 'strict';
                break;
            case E_DEPRECATED:
                $errortype = 'deprecated';
                break;
            case E_RECOVERABLE_ERROR:
                $errortype = 'recoverable error';
                break;
            case E_USER_ERROR:
                $errortype = 'usererror';
                break;
            case E_USER_WARNING:
                $errortype = 'user warning';
                break;
            case E_USER_NOTICE:
                $errortype = 'user notice';
                break;
            case E_USER_DEPRECATED:
                $errortype = 'user deprecated';
                break;
            default:
                $errortype = 'unknown';
        }

        $logger->write(0, $errortype, '[' . $errfile . ':' . $errline . '] ' . $errstr);
        if (Config::instance('bukkitweb')->get('displayErrors', false))
        {
            echo "$errortype occurrered in [$errfile:$errline]:<br />\nMessage: $errstr<br />";
        }
    }
    
    function onException($e)
    {
        if ($e instanceof CriticalException)
        {
            die('An critical exception was not caught, ending the script here!<br>Message: ' . $e->getMessage());
        }
        else
        {
            $logger = Logger::instance('error');
            $type = get_class($e);
            $logger->write(0, $type, '[' . basename($e->getFile()) . ':' . $e->getLine() . '] ' . $e->getMessage());
            
            if (Config::instance('bukkitweb')->get('displayErrors', false))
            {
                echo 'An uncaught ' . $type . " occurred!<br />\nMessage: " . $e->getMessage();
            }
        }
    }

    function onShutdown()
    {
        $error = error_get_last();
        if ($error !== null)
        {
            onError($error['type'], $error['message'], $error['file'], $error['line'], array());
        }
    }

    $config = Config::instance('bukkitweb');
    
    set_error_handler('onError', -1);
    set_exception_handler('onException');
    register_shutdown_function('onShutdown');
    date_default_timezone_set($config->get('timezone', 'Europe/Berlin'));

    session_name($config->get('sessionName', 'sid'));
    session_set_cookie_params($config->get('sessionCookieLiftime', 3600));
    session_start();
?>
