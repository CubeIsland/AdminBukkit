<?php
    Yii::import('application.components.Network.NetworkException');

    function HttpAutoloader($classname)
    {
        $path = null;
        if (substr($classname, 0, 4) == 'Http')
        {
            $path = $classname . '.php';
        }
        elseif (substr($classname, strlen($classname) - 13) == 'RequestMethod')
        {
            $path = 'RequestMethods/' . $classname . '.php';
        }
        elseif (substr($classname, strlen($classname) - 14) == 'Authentication')
        {
            $path = 'AuthenticationMethods/' . $classname . '.php';
        }
        if ($path)
        {
            $path = dirname(__FILE__) . '/' . $path;
            if (is_readable($path) && is_file($path));
            {
                include $path;
            }
        }
    }
    Yii::registerAutoloader('HttpAutoloader');

    class HttpClient implements Serializable
    {
        const LINE_ENDING       = "\r\n";

        protected $connection;
        protected $connected;
        protected $connectionKeepAlive;

        protected $debug;
        protected $ERRNO;
        protected $ERRSTR;
        protected $timeout;

        protected $host;
        protected $hostIp;
        protected $port;
        protected $file;
        protected $dir;
        protected $ssl;

        protected $authUse;
        protected $authMethod;
        protected $authUser;
        protected $authPass;

        protected $tryReconnectOnSendFailure;
        protected $handleRedirects;
        protected $useCookieRules;
        protected $cookies;
        protected $requestBody;
        protected $requestHeaders;
        protected $requestMethod;

        public function serialize()
        {
            $data = array(
                'connection' => array($this->timeout, $this->connectionKeepAlive, $this->tryReconnectOnSendFailure),
                'connectionData' => array($this->host, $this->hostIp, $this->port, $this->file, $this->dir, $this->ssl),
                'auth' => array($this->authUse, $this->authMethod, $this->authUser, $this->authPass),
                'requestData' => array($this->handleRedirects, $this->useCookieRules, $this->cookies, $this->requestBody, $this->requestHeaders, $this->requestMethod),
            );
            $this->__destruct();
            return serialize($data);
        }

        public function unserialize($serialized)
        {
            $this->__construct();
            $data = unserialize($serialized);

            $this->timeout = $data['connection'][0];
            $this->connectionKeepAlive = $data['connection'][1];
            $this->tryReconnectOnSendFailure = $data['connection'][2];

            $this->host = $data['connectionData'][0];
            $this->hostIp = $data['connectionData'][1];
            $this->port = $data['connectionData'][2];
            $this->file = $data['connectionData'][3];
            $this->dir = $data['connectionData'][4];
            $this->ssl = $data['connectionData'][5];

            $this->authUse = $data['auth'][0];
            $this->authMethod = $data['auth'][1];
            $this->authUser = $data['auth'][2];
            $this->authPass = $data['auth'][3];

            $this->handleRedirects = $data['requestData'][0];
            $this->useCookieRules = $data['requestData'][1];
            $this->cookies = $data['requestData'][2];
            $this->requestBody = $data['requestData'][3];
            $this->requestHeaders = $data['requestData'][4];
            $this->requestMethod = $data['requestData'][5];
        }

        /**
         * Creates a Http object and sets all the defaul values
         *
         * @access public
         */
        public function __construct()
        {
            $this->connection = null;
            $this->connected = false;
            $this->connectionKeepAlive = true;
            $this->tryReconnectOnSendFailure = false;

            $this->debug = false;
            $this->ERRNO = 0;
            $this->ERRSTR = '';
            $this->timeout = 3.0;

            $this->host = '';
            $this->hostIp = '';
            $this->port = 80;
            $this->file = '';
            $this->dir = '/';
            $this->ssl = false;

            $this->authUse = false;
            $this->authMethod = new BasicAuthentication();
            $this->authUser = '';
            $this->authPass = '';

            $this->handleRedirects = true;
            $this->requestBody = '';
            $this->useCookieRules = true;
            $this->cookies = array();
            $this->requestHeaders = array();
            $this->requestMethod = new GetRequestMethod();
        }

        /**
         * destructs the Http object and disconnects if necessary
         *
         * @access public
         */
        public function __destruct()
        {
            $this->disconnect(true);
        }

        /**
         * returns the response body of the last request when the object is in string context
         *
         * @access public
         * @return the response body of the last request
         */
        public function __toString()
        {
            $target = 'http';
            if ($this->ssl)
            {
                $target .= 's';
            }
            $target .= $this->host;
            if ($this->ssl && $this->port != 443)
            {
                $target .= ':' . $this->port;
            }
            elseif (!$this->ssl && $this->port != 80)
            {
                $target .= ':' . $this->port;
            }
            $target .= $this->file;
            return $target;
        }

        /**
         * Connects to the host IP if not already connected.
         * On error a Exception will be thrown.
         *
         * @access protected
         */
        protected function connect()
        {
            if (!$this->connected)
            {
                if ($this->ssl && !in_array('ssl', stream_get_transports()))
                {
                    throw new HttpException('This request requires a SSL connection, but the SSL stream transport was not found!');
                }
                $this->connection = @fsockopen(($this->ssl ? 'ssl://' : '') . $this->hostIp, $this->port, $this->ERRNO, $this->ERRSTR, $this->timeout);
                if ($this->connection === false)
                {
                    throw new NetworkException('Failed to connect to the remote host ' . $this->hostIp . '! Error: ' . $this->ERRSTR);
                }
                $this->connected = true;

                stream_set_write_buffer($this->connection, 0);
            }
        }

        /**
         * Checks whether the stream is in the end of file state
         *
         * @access protected
         * @return bool whether the connection is in the end of file state
         */
        protected function isEof()
        {
            if ($this->connected)
            {
                $meta = stream_get_meta_data($this->connection);
                return $meta['eof'];
            }
        }

        /**
         * Returns how many unread bytes are available
         *
         * @access protected
         * @return int unread bytes
         */
        protected function unreadBytes()
        {
            if ($this->connected)
            {
                $meta = stream_get_meta_data($this->connection);
                return $meta['unread_bytes'];
            }
        }

        /**
         * Disconnects from the host IP if connected and not in keep-alive mode
         *
         * @access protected
         * @param bool $force whether to ignore the keep-alive mode
         */
        protected function disconnect($force = false)
        {
            if ($this->connected && is_resource($this->connection) && (!$this->connectionKeepAlive || $force))
            {
                @fclose($this->connection);
                $this->connected = false;
            }
        }

        /**
         * Sends the given data to the remote host.
         * On error e Exception will be thrown.
         *
         * @access protected
         * @param string $data the data to send
         * @return int the count of the bytes sent
         */
        protected function send($data)
        {
            static $reconnectTried = false;
            $bytesSent = @fwrite($this->connection, $data);
            if ($bytesSent === false)
            {
                if ($this->tryReconnectOnSendFailure && !$reconnectTried)
                {
                    $reconnectTried = true;
                    $this->disconnect(true);
                    $this->connect();
                    return $this->send($data);
                }
                else
                {
                    $reconnectTried = false;
                    throw new NetworkException('Failed to send the given data!');
                }
            }
            $reconnectTried = false;
            return $bytesSent;
        }

        /**
         * Sets the state of the debug mode
         * unsed at the moment
         *
         * @access public
         * @param bool $state whether to enable or disable the debug mode
         * @return HttpClient fluent interface
         */
        public function setDebug($state)
        {
            $this->debug = ($state ? true : false);
            return $this;
        }

        /**
         * Return the state of the debug mode
         *
         * @access public
         * @return bool the state of the debug mode
         */
        public function getDebug()
        {
            return $this->debug;
        }

        /**
         * A wrapper of the executeRequest-method.
         * The given target will be set and a simple GET-request will be executed
         *
         * @access public
         * @param string $page the target
         * @return string the page
         */
        public function getPage($page = null)
        {
            if ($page !== null)
            {
                $this->setTarget($page);
            }
            $response = $this->executeRequest(new GetRequestMethod());
            return $response->getBody();
        }

        /**
         * Adds a HTTP header to the request data
         *
         * @access public
         * @param HttpHeader $header the header to add
         * @return HttpClient fluent interface
         */
        public function addHeader(HttpHeader $header)
        {
            $this->requestHeaders[strtolower($header->name)] = $header;
            return $this;
        }

        /**
         * Ads multiple HTTP headers to the request data
         *
         * @access public
         * @param HttpHeader[] $headers the headers to add
         * @return HttpClient fluent interface
         */
        public function addHeaders(array $headers)
        {
            foreach ($headers as $header)
            {
                if (is_object($header) && $header instanceof HttpHeader)
                {
                    $this->requestHeaders[strtolower($header->name)] = $header;
                }
            }
            return $this;
        }

        /**
         * Returns the named header or null if not found
         *
         * @access public
         * @param string $name the name
         * @return HttpHeader the header
         */
        public function getHeader($name)
        {
            $name = strtolower($name);
            if (isset($this->requestHeaders[$name]))
            {
                return $this->requestHeaders[$name];
            }
            else
            {
                return null;
            }
        }

        /**
         * Returns all the set HTTP headers
         *
         * @access public
         * @return HttpHeader[] all the set HTTP headers
         */
        public function getHeaders()
        {
            return $this->requestHeaders;
        }

        /**
         * Removes the named HTTP header
         *
         * @access public
         * @param string $name the name of the header to remove
         * @return HttpClient fluent interface
         */
        public function removeHeader($name)
        {
            unset($this->requestHeaders[strtolower($header->name)]);
            return $this;
        }

        /**
         * Removes multiple HTTP headers
         *
         * @access public
         * @param string[] $names the names of the headers to remove
         * @return HttpClient fluent interface
         */
        public function removeHeaders(array $names)
        {
            foreach ($names as $name)
            {
                unset($this->requestHeaders[strtolower($header->name)]);
            }
            return $this;
        }

        /**
         * Sets whether to follow the cookie rules (domain, path, secure)
         *
         * @access public
         * @param bool $state the state to set
         * @return HttpClient fluent interface
         */
        public function setUseCookieRules($state)
        {
            $this->useCookieRules = ($state ? true : false);
            return $this;
        }

        /**
         * Returns whether the cookie rules will be followed
         *
         * @access public
         * @return bool the current state
         */
        public function getUseCookieRules()
        {
            return $this->useCookieRules;
        }

        /**
         * Creates a cookie like the setcookie function and adds it to the request data
         *
         * @access public
         * @param string $name the name
         * @param string $value the value
         * @param long $expire the UNIX timestamp of the expire date
         * @param string $path the path in which the cookie is valid
         * @param string $domain the domain on which the cookie is valid
         * @param bool $secure whether the cookie is only valid on secure connections
         * @param bool $httponly whether the cookie can only be used for HTTP connections (the Http class ingnores this)
         * @return HttpClient fluent interface
         */
        public function createCookie($name, $value = '', $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false)
        {
            if ($path === null)
            {
                $path = $this->getDir();
            }
            if ($domain === null)
            {
                $domain = $this->getHost();
            }

            $cookie = new HttpCookie(
                strval($name),
                strval($value),
                date('D, d-M-Y H:i:s', intval($expire)) . ' GMT',
                strval($path),
                strval($domain),
                ($secure ? true : false),
                ($httponly ? true : false)
            );
            $this->cookies[$cookie->get('name')] = $cookie;
            return $this;
        }

        /**
         * Adds a cookie to the request data
         *
         * @access public
         * @param HttpCookie $cookie the cookie to add
         * @return HttpClient fluent interface
         */
        public function addCookie(HttpCookie $cookie)
        {
            $this->cookies[$cookie->get('name')] = $cookie;
            return $this;
        }

        /**
         * Adds multiple cookies to the request data
         *
         * @access public
         * @param HttpCookie[] $cookies the cookies to add
         * @return HttpClient fluent interface
         */
        public function addCookies(array $cookies)
        {
            foreach ($cookies as $cookie)
            {
                if (is_object($cookie) && $cookie instanceof HttpCookie)
                {
                    $this->cookies[$cookie->get('name')] = $cookie;
                }
            }
            return $this;
        }

        /**
         * Returns the named cookie or null if it does not exist
         *
         * @access public
         * @param string $name the name
         * @return HttpCookie the cookie
         */
        public function getCookie($name)
        {
            if (isset($this->cookies[$name]))
            {
                return $this->cookies[$name];
            }
            else
            {
                return null;
            }
        }

        /**
         * Returns all cookies
         *
         * @access public
         * @return HttpCookie[] all the cookies
         */
        public function getCookies()
        {
            return $this->cookies;
        }

        /**
         * Counts all set cookies
         *
         * @access public
         * @return int the count of the cookies
         */
        public function countCookies()
        {
            return count($this->cookies);
        }

        /**
         * Removes the named cookie
         *
         * @access public
         * @param string $name the name
         * @return HttpClient fluent interface
         */
        public function removeCookie($name)
        {
            unset($this->cookies[$name]);
            return $this;
        }

        /**
         * Removes all named cookies
         *
         * @access public
         * @param string[] $names the names
         * @return HttpClient fluent interface
         */
        public function removeCookies(array $names)
        {
            foreach ($names as $name)
            {
                unset($this->cookies[$name]);
            }
            return $this;
        }
        
        public function isCookieValid($cookie)
        {
            if (!$cookie instanceof HttpCookie)
            {
                $cookie = $this->getCookie(strval($cookie));
                if (!$cookie instanceof HttpCookie)
                {
                    return false;
                }
            }
            if ($this->useCookieRules)
            {
                $expires = $cookie->get('expires');
                $domain  = $cookie->get('domain');
                $path    = $cookie->get('path');
                if ($expires !== null)
                {
                    if ($cookie->getExpiresAsLong() <= time())
                    {
                        return false;
                    }
                }
                if ($domain !== null)
                {
                    $regex = '/^';
                    if ($domain[0] == '.')
                    {
                        $regex .= '([a-z0-9-]+\.)?';
                        $domain = substr($domain, 1);
                    }
                    $regex .= preg_quote($domain, '/') . '/i';
                    if (!preg_match($regex, $this->host))
                    {
                        return false;
                    }
                }
                if ($path !== null)
                {
                    $pathregex = '/^' . preg_quote($path, '/') . '/';
                    if (!preg_match($pathregex, $this->dir))
                    {
                        return false;
                    }
                }
                if ($cookie->get('secure') && !$this->ssl)
                {
                    return false;
                }
            }
            return true;
        }

        /**
         * Sets whether to keep the connection alive
         *
         * @access public
         * @param bool $state the state to set
         * @return HttpClient fluent interface
         */
        public function setConnectionKeepAlive($state)
        {
            $this->connectionKeepAlive = ($state ? true : false);
            return $this;
        }

        /**
         * Returnes whether the connections will be kept alive
         *
         * @access public
         * @return bool the current state
         */
        public function getConnectionKeepAlive()
        {
            return $this->connectionKeepAlive;
        }
        
        /**
         * Sets whether to try to reconnect on a send failure
         *
         * @access public
         * @param bool $state the state to set
         * @return HttpClient fluent interface
         */
        public function setTryReconnectOnSendFailure($state)
        {
            $this->tryReconnectOnSendFailure = ($state ? true : false);
            return $this;
        }
        
        /**
         * Returns whether to try to reconnect on a send failure
         *
         * @access public
         */
        public function getTryReconnectOnSendFailure()
        {
            return $this->tryReconnectOnSendFailure;
        }

        /**
         * Sets the conntection timeout
         *
         * @access public
         * @param int $timeout the timeout
         * @return HttpClient fluent interface
         */
        public function setTimeout($timeout)
        {
            $this->timeout = floatval($timeout);
            return $this;
        }

        /**
         * Returns the current connection timeout
         *
         * @access public
         * @return int the current timeout
         */
        public function getTimeout()
        {
            return $this->timeout;
        }

        /**
         * Sets the hostname to connect to
         *
         * @access public
         * @param string $host the hostname
         * @return HttpClient fluent interface
         */
        public function setHost($host)
        {
            $this->host = trim($host);
            $this->hostIp = gethostbyname(trim($host));
            return $this;
        }

        /**
         * Returns the current hostname
         *
         * @access public
         * @return string the current hostname
         */
        public function getHost()
        {
            return $this->host;
        }
        
        public function getHostIp()
        {
            return $this->hostIp;
        }

        /**
         * Sets the port to connect to
         *
         * @access public
         * @param int $port the port
         * @return HttpClient fluent interface
         */
        public function setPort($port)
        {
            $port = intval($port);
            if ($port >= 0 && $port < 65537)
            {
                $this->port = $port;
            }
            return $this;
        }

        /**
         * Returns the current port
         *
         * @access public
         * @return int the current port
         */
        public function getPort()
        {
            return $this->port;
        }

        /**
         * Sets the absolute file path to request
         *
         * @access public
         * @param string $file the file path
         * @param bool $getDirFromFile whether to set the dir, too
         * @return HttpClient fluent interface
         */
        public function setFile($file, $getDirFromFile = false)
        {
            $file = trim($file);
            if (substr($file, 0, 1) != '/')
            {
                throw new HttpException('A absolute path is required to set the file!');
            }
            $this->file = $file;
            if ($getDirFromFile)
            {
                $this->dir = substr($this->file, 0, strrpos($this->file, '/') + 1);
            }
            return $this;
        }

        /**
         * Returns the current file path
         *
         * @access public
         * @return string the current file path
         */
        public function getFile()
        {
            return $this->file;
        }

        /**
         * Sets the directory for relativ targets.
         * If the directory is not given as a parameter, it will be taken from the file path.
         *
         * @access public
         * @param string $dir sets the directory
         * @return HttpClient fluent interface
         */
  
        public function setDir($dir = null)
        {
            if ($dir === null)
            {
                $this->dir = substr($this->file, 0, strrpos($this->file, '/') + 1);
            }
            else
            {
                $this->dir = trim($dir);
            }
            return $this;
        }

        /**
         * Returns the current directory for relative targets
         *
         * @access public
         * @return string the current directory
         */
        public function getDir()
        {
            return $this->dir;
        }

        /**
         * Sets whether to use a SLL secured connection.
         * This will NOT set the connection port!
         *
         * @access public
         * @param bool $ssl the state
         * @return HttpClient fluent interface
         */
        public function setSsl($ssl)
        {
            $this->ssl = ($ssl ? true : false);
            return $this;
        }

        /**
         * Returns whether SSL will be used.
         *
         * @access public
         * @return bool the current state
         */
        public function getSsl()
        {
            return $this->ssl;
        }

        /**
         * This method tries to get as much informations from the given link as possible.
         * On error a Exception will be thrown.
         *
         * @access public
         * @param string $target the target URL
         * @return HttpClient fluent interface
         */
        public function setTarget($target)
        {
            if (preg_match('/^https?:\/\//si', trim($target)))
            {  // absolute
                $target = strtolower(trim($target));
                $pos = strpos($target, '://');
                $this->port = 80;
                if (substr($target, 0, $pos) == 'https')
                {
                    $this->ssl = true;
                    $this->port = 443;
                }
                $target = substr($target, $pos + 3);
                if (!strrpos($target, '/'))
                {
                    $target .= '/';
                }
                $pos = strpos($target, '/');
                $tmp = explode(':', substr($target, 0, $pos));
                $this->host = $tmp[0];
                if (count($tmp) > 1)
                {
                    $port = intval($tmp[1]);
                    if ($port > 0)
                    {
                        $this->port = $port;
                    }
                }
                $this->hostIp = gethostbyname($this->host);
                $this->file = substr($target, $pos);
                $this->dir = substr($this->file, 0, strrpos($this->file, '/') + 1);
            }
            elseif (strpos(trim($target), '/') === 0)
            { // host relative
                if (!$this->host || !$this->hostIp)
                {
                    throw new HttpException('There was no host set while setting a absolute target without protocol or host!');
                }
                $this->file = trim($target);
                $this->dir = substr($this->file, 0, strrpos($this->file, '/') + 1);
            }
            else // path relative
            {
                if ($this->dir)
                {
                    $this->file = $this->dir . $target;
                }
                else
                {
                    throw new HttpException('There was no path set while setting a relative target!');
                }
            }
            return $this;
        }

        /**
         * Sets the request method to use
         *
         * @access public
         * @param HttpRequestMethod $method the request method
         * @return HttpClient fluent interface
         */
        public function setMethod(HttpRequestMethod $method)
        {
            $this->requestMethod = $method;
            return $this;
        }

        /**
         * Returns the current request method
         *
         * @access public
         * @return HttpRequestMethod the current request method
         */
        public function getMethod()
        {
            return $this->requestMethod;
        }

        /**
         * Sets whether to handle redirects automaticly
         *
         * @access public
         * @param bool $state the state
         * @return HttpClient fluent interface
         */
        public function setHandleRedirects($state)
        {
            $this->handleRedirects = ($state ? true : false);
            return $this;
        }

        /**
         * Returns whether redirects will be handled automaticly
         *
         * @access public
         * @return bool the current state
         */
        public function getHandleRedirects()
        {
            return $this->handleRedirects;
        }

        /**
         * Sets whether to use authentication
         *
         * @access public
         * @param bool $state the state
         * @return HttpClient fluent interface
         */
        public function setAuthUse($state)
        {
            $this->authUse = ($state ? true : false);
            return $this;
        }

        /**
         * Returns whether authentication will be used
         *
         * @access public
         * @return bool the current state
         */
        public function getAuthUse()
        {
            return $this->authUse;
        }

        /**
         * Sets the authentication method to use
         *
         * @access public
         * @param HttpAuthentication $method the authentication method
         * @return HttpClient fluent interface
         */
        public function setAuthMethod(HttpAuthentication $method)
        {
            if ($method !== null)
            {
                $this->authMethod = $method;
            }
            return $this;
        }

        /**
         * Returns the current authentication method
         *
         * @access public
         * @return AbstractHttpAuthenticationMethod the current authentication method
         */
        public function getAuthMethod()
        {
            return $this->authMethod;
        }

        /**
         * Sets the username for authentication
         *
         * @access public
         * @param string $user the username
         * @return HttpClient fluent interface
         */
        public function setAuthUser($user)
        {
            $this->authUser = strval($user);
            return $this;
        }

        /**
         * Returns the current username for authentication
         *
         * @access public
         * @return string the current username
         */
        public function getAuthUser()
        {
            return $this->authUser;
        }

        /**
         * Sets the password for authentication
         *
         * @access public
         * @param string $pass the password
         * @return HttpClient fluent interface
         */
        public function setAuthPass($pass)
        {
            $this->authPass = strval($pass);
            return $this;
        }

        /**
         * Returns the current password for authentication
         *
         * @access public
         * @return string the current password
         */
        public function getAuthPass()
        {
            return $this->authPass;
        }


        /**
         * Sets the data to send with the request
         *
         * @access public
         * @param string $data the data to set
         * @return HttpClient fluent interface
         */
        public function setRequestBody($data)
        {
            $this->requestBody = $data;
            return $this;
        }

        /**
         * Returns the currenly set data.
         *
         * @access public
         * @return string the request data
         */
        public function getRequestBody()
        {
            return $this->requestBody;
        }
        
        /**
         *
         * @param string $name the name of the array
         * @param array $array the array itself
         * @param type $indexes the indexes
         * @return string[] a list of querystring variables 
         */
        private function arrayToQueryString($name, array $array, $indexes = '')
        {
            $vars = array();
            foreach ($array as $index => $value)
            {
                if (is_int($index))
                {
                    $index = '';
                }
                else
                {
                    $index = urlencode(strval($index));
                }
                if (is_array($value))
                {
                    $vars = array_merge($vars, $this->arrayToQueryString($name, $value, $indexes . "[$index]" ));
                }
                else
                {
                    $vars[] = "{$name}{$indexes}[$index]=" . urlencode(strval($value));
                }
            }

            return $vars;
        }

        /**
         * Converts an associated array to a URL encoded string of kay-value-pairs
         *
         * @access public
         * @param mixed[] $data the array to convert
         * @return string the converted array
         */
        public function generateQueryString(array $data)
        {
            $vars = array();
            foreach ($data as $index => $value)
            {
                $index = strval($index);
                if (is_array($value))
                {
                    $vars = array_merge($vars, $this->arrayToQueryString($index, $value));
                }
                else
                {
                    $vars[] = rawurlencode($index) . '=' . rawurlencode(strval($value));
                }
            }

            return implode('&', $vars);
        }

        /**
         * Checks whether all informations for a request is provided.
         *
         * @access protected
         * @return bool whether all needed informatinos were found
         */
        protected function validateVars()
        {
            if (
                empty($this->host)   ||
                empty($this->hostIp) ||
                $this->port == 0     ||
                empty($this->file)   ||
                empty($this->dir)
            )
            {
                return false;
            }
            return true;
        }

        /**
         * Reads the response Header
         *
         * @access protected
         * @return string the response head
         */
        protected function readResponseHeader()
        {
            $responseHead = '';
            while (($tmp = fgets($this->connection, 256)))
            {
                $responseHead .= $tmp;
                if (trim($tmp) == '')
                {
                    break;
                }
            }
            return $responseHead;
        }

        /**
         * Creates HttpCookie-objects from the set-cookie headers
         *
         * @access protected
         * @param string[] $cookieHeaders the values of the set-cookie headers
         */
        public function parseCookies($cookieHeaders)
        {
            $cookies = array();
            foreach ($cookieHeaders as $line)
            {
                $parts = explode(';', $line);
                $firstpart = true;
                $cookie = new HttpCookie();
                foreach ($parts as $part)
                {
                    $part = trim($part);
                    $equalPos = strpos($part, '=');
                    if ($equalPos === false)
                    {
                        if (strcasecmp($part, 'secure') == 0)
                        {
                            $cookie->set('secure', true);
                        }
                        elseif (strcasecmp($part, 'httponly') == 0)
                        {
                            $cookie->set('httponly', true);
                        }
                    }
                    else
                    {
                        $name = trim(substr($part, 0, $equalPos));
                        $value = trim(substr($part, $equalPos + 1));
                        if ($firstpart)
                        {
                            $cookie->set('name', $name);
                            $cookie->set('value', $value);
                            $firstpart = false;
                        }
                        else
                        {
                            $name = strtolower($name);
                            $cookie->set($name, $value);
                        }
                    }
                }
                $cookies[$cookie->get('name')] = $cookie;
            }
            return $cookies;
        }

        /**
         * Parses the response header
         *
         * @access protected
         */
        protected function parseResponseHeader()
        {
            $rawResponseHead = $this->readResponseHeader();
            $responseHeaderLines = explode(HttpClient::LINE_ENDING, trim($rawResponseHead));

            $responseHeaders = array();
            $cookieHeaders = array();
            $count = count($responseHeaderLines);
            for ($i = 1; $i < $count; $i++)
            {
                $strpos = strpos($responseHeaderLines[$i], ':');
                if ($strpos ===  false)
                {
                    continue;
                }
                $name = strtolower(trim(substr($responseHeaderLines[$i], 0, $strpos)));
                $value = trim(substr($responseHeaderLines[$i], $strpos + 1));
                if ($name == 'set-cookie')
                {
                    $cookieHeaders[] = $value;
                }
                else
                {
                    $responseHeaders[strtolower($name)] = new HttpHeader($name, $value);
                }
            }
            
            $cookies = $this->parseCookies($cookieHeaders);
            $this->cookies = array_merge($this->cookies, $cookies);

            $proto_end = @strpos($responseHeaderLines[0], ' ');
            if ($proto_end === false)
            {
                throw new HttpException("Failed to parse the protocol header (proto_end not found)\n" . trim($rawResponseHead));
            }
            $code_end = @strpos($responseHeaderLines[0], ' ', $proto_end + 1);
            if ($code_end === false)
            {
                throw new HttpException("Failed to parse the protocol header (code_end not found)\n" . trim($rawResponseHead));
            }

            return array(
                // Protocol
                substr($responseHeaderLines[0], 0, $proto_end),
                // Status
                intval(substr($responseHeaderLines[0], $proto_end + 1, $code_end - $proto_end)),
                // Message
                trim(substr($responseHeaderLines[0], $code_end + 1)),
                // Headers
                $responseHeaders,
                // Cookies
                $cookies
            );
        }

        /**
         * reads the response body
         *
         * @param &string[] a reference to the response head array returned by HttpClient::parseResponseHeader
         * @access protected
         * @return string the response body
         */
        protected function readResponseBody(&$responseHead)
        {
            static $redirectCodes = array(300, 301, 302, 303, 305, 307);

            $responseBody = '';
            $BUFSIZE = 4096;
            if (!in_array($responseHead[1], $redirectCodes))
            { // Read
                if (isset($responseHead[3]['content-length']))
                { // content-length -> read the given length
                    if ($this->connectionKeepAlive)
                    { // set non blocking to avoid dead locks
                        stream_set_blocking($this->connection, 0);
                    }

                    $responseBody = stream_get_contents($this->connection, intval($responseHead[3]['content-length']->value));
                }
                elseif (isset($responseHead[3]['transfer-encoding']) && strcasecmp ($responseHead[3]['transfer-encoding']->value, 'chunked') === 0)
                { // transfer-encoding: chunked -> read chunk for chunk
                    stream_set_blocking($this->connection, 1);
                    $chunkLength = 0;
                    do
                    {
                        $chunkLength = hexdec(trim(fgets($this->connection, 32)));
                        $tmp = '';
                        $tmpLength = 0;
                        while ($tmpLength < $chunkLength)
                        {
                            $tmp .= fgets($this->connection, $chunkLength);
                            $tmpLength = strlen($tmp);
                        }
                        if (substr($tmp, $tmpLength - 2) == HttpClient::LINE_ENDING)
                        {
                            $tmp = substr($tmp, 0, $tmpLength - 2);
                        }
                        $responseBody .= $tmp;

                        //$responseBody .= stream_get_contents($this->connection, $chunkLength);
                    }
                    while ($chunkLength > 0);
                }
                else
                { // no known headers -> read everything
                    if ($this->connectionKeepAlive)
                    { // set non blocking to avoid dead locks
                        stream_set_blocking($this->connection, 0);
                    }
                    
                    $responseBody = stream_get_contents($this->connection);
                }
            }
            else
            { // Redirect
                if ($this->handleRedirects)
                {
                    if (isset($responseHead[3]['location']))
                    {
                        $this->setTarget($responseHead[3]['location']->value);
                        $this->executeRequest();
                    }
                    else
                    {
                        throw new HttpException('Tried to redirect, but there was no Location-header in the response.');
                    }
                }
            }

            if (!@stream_set_blocking($this->connection, 1))
            { // force-disconnect
                $this->disconnect(true);
            }

            if (isset($responseHead[3]['content-encoding']) && strcasecmp($responseHead[3]['content-encoding']->value, 'gzip') === 0)
            { // content-encoding: gzip -> decode
                $responseBody = gzinflate($responseBody);
            }
            
            return $responseBody;
        }

        /**
         * Performes the request with the set data
         *
         * @access public
         * @return HttpResponse a response object
         */
        public function executeRequest(HttpRequestMethod $method = null)
        {
            if (!$this->validateVars())
            {
                throw new HttpException('Not all needed informations were found.');
            }

            if ($method !== null)
            {
                $this->setMethod($method);
            }

            $request = $this->requestMethod->getHeader($this) . HttpClient::LINE_ENDING . HttpClient::LINE_ENDING;

            $this->connect();
            $this->send($request);
            $responseHead = $this->parseResponseHeader();
            
            $responseBody = null;
            if ($this->requestMethod->hasContent())
            {
                $responseBody = $this->readResponseBody($responseHead);
            }

            $this->disconnect();

            return new HttpReply($responseHead[0], $responseHead[1], $responseHead[2], $responseBody, $responseHead, $responseHead[3], $responseHead[4]);
        }
    }
?>
