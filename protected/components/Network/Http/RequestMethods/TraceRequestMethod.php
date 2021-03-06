<?php
    final class TraceRequestMethod extends HttpRequestMethod
    {
        public function __toString()
        {
            return 'TRACE';
        }

        public function getHeader(HttpClient $http)
        {
            $http->setConnectionKeepAlive(false);
            $headerLines = array();
            $headerLines[] = 'TRACE ' . $http->getFile() . ' HTTP/1.1';
            $headerLines[] = 'Host: ' . $http->getHost();
            $headerLines[] = 'Connection: close';

            return implode(HttpClient::LINE_ENDING, $headerLines);
        }

        public function hasContent()
        {
            return true;
        }
    }
?>
