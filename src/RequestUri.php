<?php

namespace Grendizer\HttpMessage;

class RequestUri extends Uri
{
    public function __construct(Request $request)
    {
        $server = $request->getServerParamsBag();
        $headers = $request->getHeadersBag();

        $requestUri = '';

        if ($headers->has('X_ORIGINAL_URL')) {
            // IIS with Microsoft Rewrite Module
            $requestUri = $headers->get('X_ORIGINAL_URL');
            $headers->remove('X_ORIGINAL_URL');
            $server->remove('HTTP_X_ORIGINAL_URL');
            $server->remove('UNENCODED_URL');
            $server->remove('IIS_WasUrlRewritten');
        } elseif ($headers->has('X_REWRITE_URL')) {
            // IIS with ISAPI_Rewrite
            $requestUri = $headers->get('X_REWRITE_URL');
            $headers->remove('X_REWRITE_URL');
        } elseif ($server->get('IIS_WasUrlRewritten') == '1' && $server->get('UNENCODED_URL') != '') {
            // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
            $requestUri = $server->get('UNENCODED_URL');
            $server->remove('UNENCODED_URL');
            $server->remove('IIS_WasUrlRewritten');
        } elseif ($server->has('REQUEST_URI')) {
            $requestUri = $server->get('REQUEST_URI');
            // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path, only use URL path
            $port = $this->getPort() ? ':'.$this->getPort() : '';
            $schemeAndHttpHost = $this->getScheme().'://'.$this->getHost().$port;
            if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
            }
        } elseif ($server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $requestUri = $server->get('ORIG_PATH_INFO');
            if ('' != $server->get('QUERY_STRING')) {
                $requestUri .= '?'.$server->get('QUERY_STRING');
            }
            $server->remove('ORIG_PATH_INFO');
        }

        // normalize the request URI to ease creating sub-requests from this request
        $server->set('REQUEST_URI', $requestUri);
        
        $script = $server->get('SCRIPT_NAME', $server->get('ORIG_SCRIPT_NAME', ''));
        if ($script && strpos($requestUri, $script) === 0) {
            $requestUri = substr($requestUri, strlen($script));
        }
        
        $parts = parse_url($requestUri);
        
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $password = isset($parts['pass']) ? $parts['pass'] : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? $parts['port'] : null;
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';
        
        parent::__construct($scheme, $host, $port, $path, $query, $fragment, $user, $password);
        
        $this->basePath = $script;
    }
}